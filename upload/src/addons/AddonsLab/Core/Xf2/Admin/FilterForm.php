<?php

namespace AddonsLab\Core\Xf2\Admin;

use AddonsLab\Core\Xf2\Admin\Field\AbstractRow;

class FilterForm extends Form implements \ArrayAccess
{
    protected $filter_data = array();

    protected $field_options = [];

    public function __construct(EntityConfig $config)
    {
        parent::__construct($config);

        $this->setTemplatePrefix('filter')
            ->setItemName('form_filter')
            ->setNamePrefix('form_filter');
    }

    public function setFieldOptions($id, array $options)
    {
        if (!isset($this->fields[$id]))
        {
            throw new \InvalidArgumentException("Field '$id' does not exist. Make sure to add it to the filter form first.");
        }

        $this->field_options[$id] = $options;
    }

    public function getFieldOptions($id)
    {
        return $this->field_options[$id] ?? null;
    }

    public function getFieldValue($id, $filters)
    {
        $field = $this->fields[$id] ?? null;
        if (!isset($filters[$id]) || !$field)
        {
            return '';
        }
        switch ($field->getType())
        {
            case AbstractRow::TYPE_RADIO:
            case AbstractRow::TYPE_SELECT:
                $options = $this->getFieldOptions($id);
                $value = $filters[$id];
                if (isset($options[$value]))
                {
                    return $options[$value];
                }
                break;
        }

        // By default just render the value
        return $filters[$id];
    }

    public function getPublicFilters()
    {
        return array_filter($this->getFields(), function (AbstractRow $field)
        {
            return $field->isEnablePublic();
        });
    }

    public function getArray()
    {
        return $this->filter_data;
    }

    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->filter_data);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->filter_data[$offset] ?? null;
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->filter_data[$offset] = $value;
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->filter_data[$offset]);
    }
}