<?php

namespace AddonsLab\Core\Xf2\Admin\Field;

class CustomRow extends AbstractRow
{
    protected $type = self::TYPE_CUSTOM;

    public function __construct($id, $title = null, $explain = null)
    {
        parent::__construct($id, $title, $explain);

        $this->skip_template_generation = true;
    }
}