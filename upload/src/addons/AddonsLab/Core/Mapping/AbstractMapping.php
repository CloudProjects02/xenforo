<?php

namespace AddonsLab\Core\Mapping;

abstract class AbstractMapping
{
    protected $_mapping = array(); // child classes should populate this array with list of properties

    /**
     * @param array $data
     * @return $this
     * Sets up the object based on array
     */
    public function setup(array $data)
    {
        foreach ($data AS $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        return $this;
    }

    public function getMapping($skipColumns = array())
    {
        $mapping = array();

        foreach ($this->_mapping AS $column) {
            if (in_array($column, $skipColumns)) {
                continue;
            }

            $mapping[$column] = $this->$column;
        }

        return $mapping;
    }

    public function mergeWith(AbstractMapping $mapping)
    {
        foreach ($mapping->getMapping() AS $key => $value) {
            if ($this->$key !== null && $value === null) {
                continue;
            }

            $this->$key = $value;
        }
    }

    public function equalsTo(AbstractMapping $mapping)
    {
        if (get_class($mapping) !== get_class($this)) {
            return false;
        }
        
        foreach ($this->_mapping AS $column) {
            if($this->$column!==$mapping->$column) {
                return false;
            }
        }
        
        return true;
    }
}