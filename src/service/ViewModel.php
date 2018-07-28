<?php

namespace Webcore\Page;

class ViewModel
{
    public $model;
    private $data;
    private $columns;
    private $uniqueData = [];

    /**
     * Create a new DataTransfer instance.
     *
     * @return void
     */
    public function __construct($model)
    {
        $this->model = $model;
    }

    public function getData() {
        if(!$this->data) {
            $this->data = $this->model->get();
        }

        return $this->data;
    }

    public function getUniqueDataColumn($column) {
        if(empty($this->uniqueData[$column])) {
            $this->uniqueData[$column] = $this->model->select($column)->distinct($column)->pluck($column);
        }

        return $this->uniqueData[$column];
    }

    public function getUniqueData() {
        return collect($this->uniqueData);
    }

    public function getColumns() {
        if(!$this->columns) {
            $this->columns = $this->model->first();
        }

        return collect($this->columns)->keys();
    }
}
