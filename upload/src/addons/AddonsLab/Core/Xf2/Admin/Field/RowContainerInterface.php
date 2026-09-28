<?php

namespace AddonsLab\Core\Xf2\Admin\Field;

interface RowContainerInterface
{
    /**
     * @return AbstractRow[]
     */
    public function getInnerRows();
}