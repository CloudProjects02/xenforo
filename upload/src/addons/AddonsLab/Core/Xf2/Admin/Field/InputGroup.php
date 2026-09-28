<?php


namespace AddonsLab\Core\Xf2\Admin\Field;


class InputGroup extends CustomRow implements RowContainerInterface
{
    /**
     * @var AbstractRow
     */
    protected $fields;

    public function __construct($id, $title, array $fields, $description = '')
    {
        parent::__construct($id, $title, $description);

        $this->template_name = 'input_group';

        $this->fields = $fields;

        $this->skip_template_generation = false;
    }

    public function getInnerRows()
    {
        return $this->fields;
    }
}