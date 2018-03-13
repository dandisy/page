<?php

namespace Webcore\Page;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Page;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;
// additional
use App\Repositories\DataQueryRepository;

class PageController extends Controller
{
    private $relations = [];
    private $dataAliasColumn = [];
    private $dataEditColumn = [];
    private $dataEditColumnRelation = [];
    private $dataAddColumn = [];
    private $dataFilterColumn = [];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, DataQueryRepository $dataQueryRepository)
    {
        // start slug
        $uri = null;
        foreach($request->segments() as $segment)
        {
            if($segment)
            {
                $uri .= $segment.'/';
            }
        }    
        $uri = rtrim($uri, '/');
        // end slug

        $menu = MenuItem::nested()->get();

        $page = Page::with('presentations')
            ->with('presentations.component')
            ->with('presentations.component.dataSource')
            ->with('presentations.component.dataSource.dataQueries')
            ->with('presentations.component.dataSource.dataColumns')
            ->where('slug', $uri)
            ->where('status', 'publish')
            ->first();

        $pageWithWidget = NULL;
        if ($page) {
            $pageContent = $page->description ? \Widget::run('\Webcore\Page\Widgets\Page', ['pageContent' => $page->description]) : NULL;

            $pageWithWidget = $page->toArray();

            $pageWithWidget['description'] = $pageContent;

            if(!$pageWithWidget['presentations']) {
                //return view('themes::' . $page->template)
                return view('themes::'.str_replace('/', '.', $page->template))
                    ->with('items', ['menu' => $menu, 'page' => $pageWithWidget]);
            }

            $presentations =  $this->getPresentations($pageWithWidget, $dataQueryRepository);
            $presentations['menu'] = $menu;
    
            if($presentations) {
                //return view('vendor.themes.'.str_replace('/', '.', $page->template))
                return view('themes::'.str_replace('/', '.', $page->template))
                    ->with('items', $presentations)
                    ->with('display', $request->display)
                    ->with('key', $request->key ? : NULL);
            }
        }

        abort(404);
    }

    private function getPresentations($page, DataQueryRepository $dataQueryRepository, $filter = NULL) {
        $presentations = NULL;

        if($page['presentations']) {
            $model = NULL;
            $modelData = NULL;
            $data = NULL;
            $columnsUniqueData = [];

            foreach($page['presentations'] as $key => $presentation) {
                $component = $presentation['component'];

                if(isset($component['data_source'])) {
                    $dataSource = $component['data_source'];

                    if($dataSource) {
                        if($dataSource['model']) {
                            $queryData = $this->getQueryData($dataSource, $dataQueryRepository, $filter);
                            $model = $queryData['model'];
//                            if(method_exists($model, 'get')) {
                                $modelData = $model->get();
//                            } else {
//                                $modelData = $model;
//                            }
                            $modelColumns = $queryData['columns'];

                            // collecting column alias & column edit for datatable
                            $this->getColumnsDataTable($modelColumns, $dataSource);
                        } else {
                            // not working, corection for $model->connection undefined if $dataSource['model'] is false
                            /*if(isset($dataSource['dataQuery'])) {
                                $dataQueries = $dataSource['dataQueries'];
                                if(isset($dataQueries[0]['command'])) {
                                    if($dataQueries[0]['command'] === 'raw') {
                                        $modelData = DB::connection($model->connection ? : 'mysql')->select(
                                            DB::raw(trim(preg_replace('/\s+/', ' ', $dataQueries[0]['value'])))
                                        );
                                    }
                                }
                            }*/
                        }
                    }
                }

                $data[$presentation['component_id']] = $modelData;
            }

            // get unique columns data for datatable column filter dropdown
            // note : for now only support one datatable in a page
            foreach(array_column($this->dataAliasColumn, 'title') as $item) {
                $columnsUniqueData[$item] = array_unique(array_column($modelData->toArray(), $item));
            }

            $dataTable = [
                'model' => $model,
                'columns' => $this->dataAliasColumn,
                'editColumns' => $this->dataEditColumn,
                'editColumnsRelation' => $this->dataEditColumnRelation,
                'addColumns' => $this->dataAddColumn,
                'columnsUniqueData' => $columnsUniqueData
            ];

            $presentations = [
                'data' => collect($data),
                'dataTable' => collect($dataTable),
                'page' => collect($page)
            ];
        }

        return collect($presentations);
    }

    private function getQueryData($dataSource, DataQueryRepository $dataQueryRepository, $filter = NULL) {
        $columns = [];
        $hasSubQuery = NULL;
        $asSubQuery = NULL;

        if(stristr($dataSource['model'], '/')) {
            $modelName = str_replace('/', '\\', $dataSource['model']);
        } else {
            $modelName = $dataSource['model'];
        }

        $modelFQNS = 'App\Models\\'.$modelName;

        $data = new $modelFQNS();

        $dataQuery = $filter ? : $dataSource['data_queries'];

        foreach($dataQuery as $query) {
            if(array_key_exists('id', $query)) {
                $id = $query['id'];
                $hasSubQuery = $dataQueryRepository->findWhere(['parent' => $id]);
            }
            if(array_key_exists('parent', $query)) {
                $asSubQuery = $query['parent'];
            }

            $command = $query['command'];

            if (
                $command === 'first' ||
                $command === 'count' ||
                $command === 'latest' ||
                $command === 'distinct' ||
                $command === 'inRandomOrder'
            ) {
                $data = $data->$command();
            } else if(
                $command === 'select' ||
                $command === 'addSelect' ||
                $command === 'groupBy' ||
                $command === 'whereNull' ||
                $command === 'whereNotNull' ||
                $command === 'sum' ||
                $command === 'avg' ||
                $command === 'max' ||
                $command === 'min'
            ) {
                // for relevant command as sub query, don't process command, sub query command will be handle separately
                if(!$asSubQuery) {
                    if (isset($query['column'])) {
                        $columns = explode(',', $query['column']);
                    }

                    if (empty($query['column']) && $command === 'select') {
                        // get all column name from selected model
                        $columns = $this->getColumnsName($dataSource['model']);
                    }

                    if($command === 'select') {
                        $columnsAlias = array_pluck($dataSource['data_columns'], 'alias', 'name');

                        $columnsSelect = array_map(function ($value) use ($columnsAlias) {
                            if (isset($columnsAlias[$value])) {
                                if ($columnsAlias[$value]) {
                                    return $value . ' AS ' . $columnsAlias[$value];
                                }
                            }

                            return $value;
                        }, $columns);
                    } else {
                        $columnsSelect = $columns;
                    }

                    $data = $data->$command($columnsSelect);
                }
            } else if(
                $command === 'where' ||
                $command === 'orWhere' ||
                $command === 'whereDate' ||
                $command === 'whereMonth' ||
                $command === 'whereDay' ||
                $command === 'whereYear' ||
                $command === 'whereTime' ||
                $command === 'whereColumn' ||
                $command === 'having'
            ) {
                // for relevant command as sub query, don't process command, sub query command will be handle separately
                if(!$asSubQuery) {
                    $data = $data->$command(
                        $query['column'],
                        $query['operator'],
                        $query['value']
                    );
                }
            } else if($command === 'orderBy') {
                // for relevant command as sub query, don't process command, sub query command will be handle separately
                if(!$asSubQuery) {
                    if($query['value']) {
                        $data = $data->$command($query['column'], $query['value']);
                    } else {
                        $data = $data->$command($query['column']);
                    }
                }
            } else if(
                $command === 'whereIn' ||
                $command === 'whereNotIn' ||
                $command === 'whereBetween' ||
                $command === 'whereNotBetween'
            ) {
                // for relevant command as sub query, don't process command, sub query command will be handle separately
                if(!$asSubQuery) {
                    $value = explode(',', $query['value']);

                    $data = $data->$command($query['column'], $value);
                }
            } else if(
                $command === 'from' ||
                $command === 'offset' ||
                $command === 'limit' ||
                $command === 'whereRaw' ||
                $command === 'orWhereRaw' ||
                $command === 'orderByRaw' ||
                $command === 'havingRaw'
            ) {
                // for relevant command as sub query, don't process command, sub query command will be handle separately
                if(!$asSubQuery) {
                    $data = $data->$command($query['value']);
                }
            } else if($command === 'selectRaw') {
                // for relevant command as sub query, don't process command, sub query command will be handle separately
                if(!$asSubQuery) {
                    $data = $data->$command($query['value']);

                    // preparing add column on datatable
                    $addition = explode(',', $query['value']);

                    foreach ($addition as $item) {
                        // if using AS (column alias)
                        $addItem = explode(' ', $item);

                        if(isset($addItem[2])) {
                            $this->dataAddColumn[$addItem[2]] = $addItem[0];
                        } else {
                            $this->dataAddColumn[$addItem[0]] = $addItem[0];
                        }
                    }
                }
            } else if($command === 'with') {
                // for relevant command as sub query, don't process command, sub query command will be handle separately
                if(!$asSubQuery) {
                    if (!$hasSubQuery) {
                        $data = $data->$command($query['value']);
                    } else {
                        // handling sub query of with command
                        $data = $data->$command($query['value'], function ($query) use ($hasSubQuery) {
                            foreach ($hasSubQuery as $sub) {
                                $subCommand = $sub['command'];

                                if (
                                    $subCommand === 'latest' ||
                                    $subCommand === 'count'
                                ) {
                                    $query = $query->$subCommand();
                                } else if (
                                    $subCommand === 'select' ||
                                    $subCommand === 'addSelect' ||
                                    $subCommand === 'groupBy' ||
                                    $subCommand === 'whereNull' ||
                                    $subCommand === 'whereNotNull' ||
                                    $subCommand === 'sum' ||
                                    $subCommand === 'avg' ||
                                    $subCommand === 'max' ||
                                    $subCommand === 'min'
                                ) {
                                    $query = $query->$subCommand($sub['column']);
                                } else if (
                                    $subCommand === 'on' ||
                                    $subCommand === 'orOn' ||
                                    $subCommand === 'where' ||
                                    $subCommand === 'orWhere' ||
                                    $subCommand === 'whereDate' ||
                                    $subCommand === 'whereMonth' ||
                                    $subCommand === 'whereDay' ||
                                    $subCommand === 'whereYear' ||
                                    $subCommand === 'whereTime' ||
                                    $subCommand === 'whereColumn' ||
                                    $subCommand === 'having'
                                ) {
                                    $query = $query->$subCommand($sub['column'], $sub['operator'], $sub['value']);
                                } else if (
                                    $subCommand === 'whereIn' ||
                                    $subCommand === 'whereNotIn' ||
                                    $subCommand === 'whereBetween' ||
                                    $subCommand === 'whereNotBetween'
                                ) {
                                    $query = $query->$subCommand($sub['column'], $sub['value']);
                                } else if (
                                    $subCommand === 'whereRaw' ||
                                    $subCommand === 'orWhereRaw'
                                ) {
                                    $query = $query->$subCommand($sub['value']);
                                }
                            }
                        });
                    }
                }
            } else if($command === 'join' || $command === 'leftJoin') {
                // for relevant command as sub query, don't process command, sub query command will be handle separately
                if(!$asSubQuery) {
                    $value = explode(',', $query['value']);

                    if(stristr($value[0], '/')) {
                        $joinModule = explode('/', $value[0]);
                        $joinModelNS = $joinModule[0];
                        $joinModelName = $joinModule[1];
                    } else {
                        $joinModelName = $value[0];
                    }

                    if(stristr($joinModelName, ' AS ')) {
                        // if using AS (table alias)
                        $joinNM = explode(' ', $joinModelName);

                        $joinModelName = $joinNM[0];
                    }

                    if(isset($joinModelNS)) {
                        $joinModelName = $joinModelNS.'\\'.$joinModelName;
                    }

                    $JoinModelFQNS = 'App\Models\\'.$joinModelName;

                    $joinModel = new $JoinModelFQNS();

                    $joinTable = $joinModel->table;

                    if(isset($joinNM[2])) {
                        $tableAlias = $joinNM[2];

                        $joinTable .= ' AS ' . $tableAlias;
                    }

                    if (isset($value[3])) {
                        $tableAlias = $value[3];

                        $joinTable .= ' AS ' . $tableAlias;
                    }

                    if (!$hasSubQuery) {
                        $data = $data->$command($joinTable, $value[1], '=', $value[2]);
                    } else {
                        // handling sub query of join and leftJoin command
                        $data = $data->$command($joinTable, function ($query) use ($hasSubQuery, $value) {
                            $query = $query->on($value[1], '=', $value[2]);

                            foreach ($hasSubQuery as $sub) {
                                $subCommand = $sub['command'];

                                if(
                                    $subCommand === 'latest' ||
                                    $subCommand === 'count'
                                ) {
                                    $query = $query->$subCommand();
                                } else if (
                                    $subCommand === 'select' ||
                                    $subCommand === 'addSelect' ||
                                    $subCommand === 'groupBy' ||
                                    $subCommand === 'whereNull' ||
                                    $subCommand === 'whereNotNull' ||
                                    $subCommand === 'sum' ||
                                    $subCommand === 'avg' ||
                                    $subCommand === 'max' ||
                                    $subCommand === 'min'
                                ) {
                                    $query = $query->$subCommand($sub['column']);
                                } else if (
                                    $subCommand === 'where' ||
                                    $subCommand === 'orWhere' ||
                                    $subCommand === 'whereDate' ||
                                    $subCommand === 'whereMonth' ||
                                    $subCommand === 'whereDay' ||
                                    $subCommand === 'whereYear' ||
                                    $subCommand === 'whereTime' ||
                                    $subCommand === 'whereColumn' ||
                                    $subCommand === 'having'
                                ) {
                                    $query = $query->$subCommand($sub['column'], $sub['operator'], $sub['value']);
                                } else if (
                                    $subCommand === 'whereIn' ||
                                    $subCommand === 'whereNotIn' ||
                                    $subCommand === 'whereBetween' ||
                                    $subCommand === 'whereNotBetween'
                                ) {
                                    $query = $query->$subCommand($sub['column'], $sub['value']);
                                } else if (
                                    $subCommand === 'whereRaw' ||
                                    $subCommand === 'orWhereRaw'
                                ) {
                                    $query = $query->$subCommand($sub['value']);
                                }
                            }
                        });
                    }
                    // end handling sub query of join and leftJoin command

                    // preparing to get all column name of join table
                    array_push($this->relations, $query['value']);
                }
            } else if($command === 'raw') {
                // for relevant command as sub query, don't process command, sub query command will be handle separately
                if(!$asSubQuery) {
                    $data = DB::connection($data->connection ?: 'mysql')->select(
                        DB::raw(trim(preg_replace('/\s+/', ' ', $query['value'])))
                    );
                }
            }
        }

        return [
            'columns' => $columns,
            'model' => $data
        ];
    }

    private function getColumnsName($modelName) {
        if(stristr($modelName, '/')) {
            $modelName = str_replace('/', '\\', $modelName);
        }

        // get all column name from selected model
        $modelFQNS = 'App\Models\\'.$modelName;

        $model = new $modelFQNS();

        $columns = $model->getTableColumns();
        // end get all column name from selected model

        // get all column name of join table
        if($this->relations) {
            $columns = array_map(function($value) use ($model) {
                return $model->table.'.'.$value;
            }, $columns);

            foreach($this->relations as $val) {
                $value = explode(',', $val);

                if(stristr($value[0], '/')) {
                    $joinModule = explode('/', $value[0]);
                    $joinModelNS = $joinModule[0];
                    $joinModelName = $joinModule[1];
                } else {
                    $joinModelName = $value[0];
                }

                if(stristr($joinModelName, ' AS ')) {
                    // if using AS (table alias)
                    $joinNM = explode(' ', $joinModelName);

                    $joinModelName = $joinNM[0];
                }

                if(isset($joinModelNS)) {
                    $joinModelName = $joinModelNS.'\\'.$joinModelName;
                }
                
                $JoinModelFQNS = 'App\Models\\'.$joinModelName;

                $joinModel = new $JoinModelFQNS();

                $joinColumns = $joinModel->getTableColumns();

                $joinColumns = array_map(function($value) use ($joinModel) {
                    return $joinModel->table.'.'.$value;
                }, $joinColumns);

                $columns = array_merge($columns, $joinColumns);
            }
        }
        // end get all column name of join table

        return $columns;
    }

    private function getColumnsDataTable($columns, $dataSource) {
        if($columns) {
            // collecting column alias & column edit for datatable
            $alias = $dataSource['data _columns']->pluck('alias', 'name')->toArray();
            $edit = $dataSource['data_columns']->pluck('edit', 'name')->toArray();

            foreach ($columns as $value) {
                if (strpos($value, '.')) {
                    $columnName = trim(substr($value, strrpos($value, '.') + 1));
                    if (array_key_exists($value, $alias)) {
                        if ($alias[$value]) {
                            $this->dataAliasColumn[$alias[$value]] = [
                                'data' => $alias[$value],
                                'name' => $alias[$value],
                                'title' => $alias[$value]
                            ];
                        } else {
                            $this->dataAliasColumn[$value] = [
                                'data' => $columnName,
                                'name' => $value,
                                'title' => $columnName
                            ];
                        }
                    }
                } else {
                    if (array_key_exists($value, $alias)) {
                        if ($alias[$value]) {
                            $this->dataAliasColumn[$alias[$value]] = [
                                'data' => $alias[$value],
                                'name' => $alias[$value],
                                'title' => $alias[$value]
                            ];
                        } else {
                            array_push($this->dataAliasColumn, $value);
                        }
                    } else {
                        array_push($this->dataAliasColumn, $value);
                    }
                }

                /*if(array_key_exists($value, $edit)) {
                    if($edit[$value] && $edit[$value] != 'null') {
                        if(!array_search($edit[$value], $dataAliasColumn)) {
                            $this->dataEditColumn[$columnName] = $edit[$value];
                        } else {
                            // $this->dataAddColumn[$columnName] = $edit[$value];
                        }
                    }
                }*/
            }
            // end collecting column alias & column edit for datatable

            // collecting additional column for datatable
            if ($this->dataAddColumn) {
                foreach ($this->dataAddColumn as $key => $item) {
                    $this->dataAliasColumn[$key] = ['data' => $key, 'name' => $item, 'title' => $key];
                }
            }

            // collecting edit column for datatable
            foreach ($edit as $key => $item) {
                /*if(strpos($key, '.')) {
                    $columnName = trim(substr($key, strrpos($key, '.') + 1));
                } else {*/
                $columnName = $key;
                //}

                if (!array_key_exists($item, $this->dataAliasColumn) && $item && $item != 'null') {
                    $this->dataEditColumn[isset($alias[$columnName]) ? $alias[$columnName] : $columnName] = $item;
                } else if (array_key_exists($item, $this->dataAliasColumn)) {
                    $this->dataEditColumnRelation[$item] = $key;

                    $this->dataAliasColumn[$item]['name'] = $key;
                }
            }
        } else {
            // get all columns name and alias if no select command
            // get all column name from selected model
            if(stristr($dataSource['model'], '/')) {
                $modelName = str_replace('/', '\\', $dataSource['model']);
            } else {
                $modelName = $dataSource['model'];
            }

            $modelFQNS = 'App\Models\\'.$modelName;

            $model = new $modelFQNS();

            $columns = $model->getTableColumns();
            // end all column name from selected model

            // get all column name of join table
            if ($this->relations) {
                $columns = array_map(function ($value) use ($model) {
                    return $model->table . '.' . $value;
                }, $columns);

                foreach ($this->relations as $val) {
                    $value = explode(',', $val);

                    if(stristr($value[0], '/')) {
                        $joinModule = explode('/', $value[0]);
                        $joinModelNS = $joinModule[0];
                        $joinModelName = $joinModule[1];
                    } else {
                        $joinModelName = $value[0];
                    }
    
                    if(stristr($joinModelName, ' AS ')) {
                        // if using AS (table alias)
                        $joinNM = explode(' ', $joinModelName);
    
                        $joinModelName = $joinNM[0];
                    }
    
                    if(isset($joinModelNS)) {
                        $joinModelName = $joinModelNS.'\\'.$joinModelName;
                    }
                    
                    $JoinModelFQNS = 'App\Models\\'.$joinModelName;

                    $joinModel = new $JoinModelFQNS();

                    $joinColumns = $joinModel->getTableColumns();

                    $joinColumns = array_map(function ($value) use ($joinModel) {
                        return $joinModel->table . '.' . $value;
                    }, $joinColumns);

                    $columns = array_merge($columns, $joinColumns);
                }
            }
            // end get all column name of join table
            // end get all columns name and alias if no select command

            foreach ($columns as $value) {
                if (strpos($value, '.')) {
                    $columnName = trim(substr($value, strrpos($value, '.') + 1));

                    array_push($this->dataAliasColumn, $columnName);
                } else {
                    array_push($this->dataAliasColumn, $value);
                }
            }
        }
    }

    // for handling datatable
    public function getDataTable(Request $request, DataQueryRepository $dataQueryRepository)
    {
        $page = Page::with('presentations')
            ->with('presentations.component')
            ->with('presentations.component.dataSource')
            ->with('presentations.component.dataSource.dataQueries')
            ->with('presentations.component.dataSource.dataColumns')
            ->where('slug', $request->slug)
            ->where('status', 'publish')
            ->first();

        $presentations =  $this->getPresentations($page, $dataQueryRepository);

        $data = $presentations['dataTable']['model'];

        if($request->filter) {
            foreach ($request->filter as $filter) {
                $command = $filter['command'];

                if(
                    $command === 'whereNull' ||
                    $command === 'whereNotNull' ||
                    $command === 'groupBy'
                ) {
                    $data->$command($filter['column']);
                } else if(
                    $command === 'whereIn' ||
                    $command === 'whereNotIn' ||
                    $command === 'whereBetween' ||
                    $command === 'whereNotBetween'
                ) {
                    $data->$command($filter['column'], $filter['value']);
                } else if(
                    $command === 'whereRaw'
                ) {
                    $data->$command($filter['value']);
                } else {
                    $data->$command($filter['column'], $filter['operator'], $filter['value']);
                }
            }
        }

        return \Yajra\DataTables\Facades\DataTables::of($data)->make(true);
    }
}
