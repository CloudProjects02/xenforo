<?php

namespace AddonsLab\Core\Xf2\Admin;

class FilterFormParams extends \ArrayObject
{
    protected $form;

    /**
     * @param FilterForm $form
     * @return FilterFormParams
     */
    public function setForm(FilterForm $form): FilterFormParams
    {
        $this->form = $form;
        return $this;
    }

    public function hasFieldParams($fieldId)
    {
        return $this->offsetExists($fieldId.'_options');
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($key)
    {
        if (substr($key, -8) === '_options')
        {
            // Support the new syntax set to additional options for the fields on filter form itself
            $fieldId = substr($key, 0, -8);
            if ($this->form)
            {
                $options = $this->form->getFieldOptions($fieldId);
                if ($options !== null)
                {
                    return $options;
                }
            }

            // Trigger an error if the key finishes with '_options'
            if (!$this->offsetExists($key) && \XF::$debugMode)
            {
                // Trigger warning visible in templates
                trigger_error("Accessed unknown getter '$key' on FilterForm. Please use CrudController::_prepareFormFilterParams to setup the params.", E_USER_WARNING);
            }
        }

        return parent::offsetGet($key);
    }

    public function setFieldOptions($field_id, array $options)
    {
        $this->offsetSet($field_id . '_options', $options);
    }

    public function getFieldOptions($field_id)
    {
        return $this->offsetGet($field_id . '_options');
    }
}