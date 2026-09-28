<?php


namespace AddonsLab\Core\Xf2\Admin\Field;


class FormSection extends CustomRow implements RowContainerInterface
{
    /**
     * @var AbstractRow
     */
    protected $fields;
    public function __construct($id, $title, array $fields)
    {
        parent::__construct($id, $title, null);

        $this->template_name = 'form_section';

        $this->fields = $fields;

        $this->skip_template_generation = false;
    }

    public function getInnerRows()
    {
        return $this->fields;
    }
}