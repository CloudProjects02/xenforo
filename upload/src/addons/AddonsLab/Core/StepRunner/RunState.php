<?php

namespace AddonsLab\Core\StepRunner;
class RunState
{
    public function __construct($data = array())
    {
        foreach ($data AS $key => $value) {
            if (!property_exists($this, $key)) {
                throw new \RuntimeException('The data provider to run status can not contain key ' . $key);
            }

            $this->$key = $value;
        }
    }

    public function getAsArray()
    {
        return array(
            'step_id' => $this->step_id,
            'index' => $this->index,
            'total' => $this->total,
            'per_page' => $this->per_page,
            'step_data' => $this->step_data,
        );
    }

    public $step_id = false;
    public $index = 0; // the numeric index or ID of last imported item
    public $total = false; // total number of items to import
    public $per_page = 1000; // the number of items to import per page
    public $step_data = array(); // any additional data can be stored here by the steps
    public $messages = array(); // importer steps can put the messages they want here to persist during the entire import
}