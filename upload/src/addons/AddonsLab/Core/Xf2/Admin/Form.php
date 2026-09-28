<?php

namespace AddonsLab\Core\Xf2\Admin;

use AddonsLab\Core\Xf2\Admin\Field\AbstractRow;
use AddonsLab\Core\Xf2\Admin\Field\RowContainerInterface;
use DBTech\Credits\Entity\Field;

class Form
{
    /**
     * @var EntityConfig
     */
    protected $config;
    /**
     * @var AbstractRow[]
     */
    protected $fields = array();

    protected $inner_fields = [];

    /**
     * @var string The prefix to use for auto-generated field templates
     */
    protected $template_prefix = 'row';

    /**
     * @var string The name to use for $item which should provide the value and other fields of the option
     */
    protected $item_name = 'item';

    protected $name_prefix = ''; // if empty, entity/relation name is used

    /**
     * Form constructor.
     * @param EntityConfig $config
     */
    public function __construct(EntityConfig $config)
    {
        $this->config = $config;
    }

    public function addField(AbstractRow $field)
    {
        $field->setForm($this);
        $this->fields[$field->getId()] = $field;

        if ($field instanceof RowContainerInterface)
        {
            foreach ($field->getInnerRows() as $innerRow)
            {
                $this->inner_fields[] = $innerRow->getId();
                $this->addField($innerRow);
            }
        }

        return $this;
    }

    public function addFields(array $fields)
    {
        foreach ($fields as $field)
        {
            $this->addField($field);
        }

        return $this;
    }

    public function isInnerField(AbstractRow $field)
    {
        return in_array($field->getId(), $this->inner_fields, true);
    }

    public function getFields()
    {
        return $this->fields;
    }

    public function getTopLevelFields()
    {
        return array_filter($this->fields, function (AbstractRow $field)
        {
            return !$this->isInnerField($field);
        });
    }

    public function getFormatParams($fieldId, $paramName, $default = '')
    {
        return $this->fields[$fieldId]->getFormatParam($paramName) ?: $default;
    }

    public function getFieldLabel($fieldId)
    {
        return $this->config->getPhrase($fieldId);
    }

    public function getFieldExplain($fieldId)
    {
        return $this->config->getPhrase($fieldId . '_explain');
    }

    /**
     * @return string
     */
    public function getTemplatePrefix()
    {
        return $this->template_prefix;
    }

    /**
     * @param string $template_prefix
     */
    public function setTemplatePrefix($template_prefix)
    {
        $this->template_prefix = $template_prefix;
        return $this;
    }

    /**
     * @return string
     */
    public function getItemVariableName()
    {
        return $this->item_name;
    }

    /**
     * @param string $item_name
     * @return Form
     */
    public function setItemName($item_name)
    {
        $this->item_name = $item_name;
        return $this;
    }

    /**
     * @return EntityConfig
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return string
     */
    public function getNamePrefix()
    {
        return $this->name_prefix;
    }

    /**
     * @param string $name_prefix
     */
    public function setNamePrefix($name_prefix)
    {
        $this->name_prefix = $name_prefix;
    }

}
