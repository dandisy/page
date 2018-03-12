<?php

namespace Webcore\Page;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ColumnController extends Controller
{
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
    public function index(Request $request)
    {
        $data = NULL;

        if(!$request->model) {
            return [];
        }

        $modelName = $request->model;
        $modelFQNS = 'App\Models\\'.$modelName;

        $model = new $modelFQNS();
    
        $columns = $model->getTableColumns();
    
        if($request->joinModel) {
            $columns = array_map(function($value) use ($model) {
                return $model->table.'.'.$value;
            }, $columns);
    
            foreach($request->joinModel as $item) {
                $items = explode(',', $item);
                $joinModelName = $items[0];
                $joinModelFQNS = 'App\Models\\'.$joinModelName;

                $joinModel = new $joinModelFQNS();
    
                $joinColumns = $joinModel->getTableColumns();
    
                $joinColumns = array_map(function($value) use ($joinModel, $items) {
                    if(isset($items[3])) {
                        return $items[3].'.'.$value;
                    }

                    return $joinModel->table.'.'.$value;
                }, $joinColumns);
    
                $columns = array_merge($columns, $joinColumns);
            }
        }
    
        return $columns;
    }
}
