<?php

namespace AddonsLab\Core\Xf2\Admin\Field;

use AddonsLab\Core\Xf2\Admin\Form;

abstract class AbstractRow
{
    protected $skip_template_generation = false;

    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_ONOFF = 'onoff';
    public const TYPE_ONOFFTEXTBOX = 'onofftextbox';
    public const TYPE_RADIO = 'radio';
    public const TYPE_SELECT = 'select';
    public const TYPE_SPINBOX = 'spinbox';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_TEXTBOX = 'textbox';
    public const TYPE_EDITORROW = 'editorrow';
    public const TYPE_CODEEDITOR = 'codeeditor';
    public const TYPE_DATEBOX = 'datebox';
    public const TYPE_USERNAME = 'username';

    /**
     * @var Form
     */
    protected $form;

    /**
     * For rows that only have a label and custom content
     */
    public const TYPE_INFO = 'info';
    public const TYPE_CUSTOM = 'custom';

    protected $id;

    protected $type;

    protected $template_name;

    protected $title;

    protected $explain;

    protected $relation_name;

    protected $getter_name;

    protected $options_name;

    protected $format_params = [];

    protected $skip_row_wrapper = false;

    protected $conditional = '';

    protected $enable_public = false;

    /**
     * Custom input name to be used instead of the entity name
     * @var string
     */
    protected $input_name = '';

    protected $input_type = '';

    protected $append_html = '';

    protected $attributes = [];

    /**
     * AbstractRow constructor.
     * @param $type
     */
    public function __construct($id, $title = null, $explain = null)
    {
        $this->id = $id;
        $this->title = $title;
        $this->explain = $explain;
    }

    public function getLabel()
    {
        if (!$this->form) {
            return $this->title ?? $this->id;
        }

        return $this->form->getFieldLabel($this->id);
    }


    /**
     * @param string $conditional
     */
    public function setConditional(string $conditional)
    {
        $this->conditional = $conditional;
        return $this;
    }

    /**
     * @return string
     */
    public function getConditional(): string
    {
        return $this->conditional;
    }

    public function getFormatParam($paramName)
    {
        return isset($this->format_params[$paramName]) ? $this->format_params[$paramName] : '';
    }

    public function setFormatParam($paramName, $value)
    {
        $this->format_params[$paramName] = $value;
        return $this;
    }

    public function setRowType($type)
    {
        return $this->setFormatParam('rowtype', $type);
    }

    public function setRowClass($class)
    {
        return $this->setFormatParam('class', $class);
    }


    /**
     * @param string $relation_name
     * The name of entity in format "TestEntity" or "Provider\Id:TestEntity".
     * Will be used for saving info into a relation instead of the main entity
     */
    public function setRelationName($relation_name)
    {
        $this->relation_name = $relation_name;
        return $this;
    }

    /**
     * @param string $getter_name
     * @return AbstractRow
     * Full getter string that returns the current value for the field. Should include relation name if needed
     */
    public function setGetterName($getter_name)
    {
        $this->getter_name = $getter_name;
        return $this;
    }

    /**
     * @return string
     * The name of getter that returns the value of the field. Can be name of DB column,
     * or full getter name like Relation.getter_in_relation
     */
    public function getGetterName()
    {
        if ($this->getter_name) {
            $getter = $this->getter_name;
        } else {
            $getter = $this->id;
        }

        if (strpos($getter, '.') === false) {
            if ($this->relation_name || $this->input_name) {
                $getter = ($this->relation_name ?: $this->input_name) . '.' . $getter;
            }
        }


        return $getter;
    }

    public function getTemplate()
    {
        if ($this->type === self::TYPE_CUSTOM) {
            if (!$this->template_name) {
                return $this->id; // by default we will look for a template with the same name as the ID of the field
            }

            $template_name = $this->template_name;
        } else {
            $template_name = $this->type;
        }

        if ($this->skip_row_wrapper) {
            $template_name .= '_no_wrapper';
        }

        return $template_name;
    }

    public function setTemplateName($templateName)
    {
        if ($this->type !== self::TYPE_CUSTOM) {
            throw new \RuntimeException("Custom template name is supported only for custom field type.");
        }

        $this->template_name = $templateName;

        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param mixed $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return mixed
     */
    public function getExplain()
    {
        return $this->explain;
    }

    /**
     * @param mixed $explain
     */
    public function setExplain($explain)
    {
        $this->explain = $explain;
    }

    /**
     * @return string
     */
    public function getRelationName()
    {
        return $this->relation_name;
    }

    /**
     * @return Form
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * @param Form $form
     * @return AbstractRow
     */
    public function setForm($form)
    {
        $this->form = $form;
        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     * @param bool $skip_row_wrapper
     * @return $this
     */
    public function setSkipRowWrapper(bool $skip_row_wrapper)
    {
        $this->skip_row_wrapper = $skip_row_wrapper;
        return $this;
    }

    /**
     * @return string
     */
    public function getInputName(): string
    {
        return $this->input_name;
    }

    /**
     * @return string
     */
    public function getInputType(): string
    {
        return $this->input_type;
    }

    /**
     * @param string $input_name
     */
    public function setCustomInput(string $input_name, string $input_type)
    {
        $this->input_name = $input_name;
        $this->input_type = $input_type;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOptionsName()
    {
        return $this->options_name;
    }

    /**
     * @param mixed $options_name
     * @return AbstractRow
     */
    public function setOptionsName($options_name)
    {
        $this->options_name = $options_name;
        return $this;
    }

    /**
     * @param bool $skip_template_generation
     * @return $this
     */
    public function skipTemplateGeneration($skip = true)
    {
        $this->skip_template_generation = $skip;
        return $this;
    }

    public function getSkipTemplateGeneration()
    {
        return $this->skip_template_generation;
    }

    /**
     * @return bool
     */
    public function isEnablePublic(): bool
    {
        return $this->enable_public;
    }

    /**
     * @param bool $enable_public
     * @return AbstractRow
     */
    public function setEnablePublic(bool $enable_public): AbstractRow
    {
        $this->enable_public = $enable_public;
        return $this;
    }

    public function appendHtml($html)
    {
        $this->append_html = $html;
        return $this;
    }

    public function getAppendHtml()
    {
        return $this->append_html;
    }

    /**
     * @param string $name
     * @param string $value
     * @param string $duplicate = 'append' | 'replace'
     * @return $this
     */
    public function addAttribute($name, $value, $duplicate = 'append')
    {
        if (!isset($this->attributes[$name])) {
            $this->attributes[$name] = $value;
        } else {
            if ($duplicate === 'append') {
                $this->attributes[$name] .= ' ' . $value;
            } else {
                $this->attributes[$name] = $value;
            }
        }
        return $this;
    }

    public function addAttributes(array $attributes, $duplicate = 'append')
    {
        foreach ($attributes as $name => $value) {
            $this->addAttribute($name, $value, $duplicate);
        }
        return $this;
    }

    public function getAttributeList()
    {
        $list = [];

        foreach ($this->attributes as $name => $value) {
            $list[] = $name . '="' . $value . '"';
        }

        return implode(' ', $list);
    }
}