<?php

namespace AddonsLab\Core\Xf2;

use AddonsLab\Core\Xf2\Admin\EntityConfig;

interface CrudControllerInterface
{
    /**
     * @return EntityConfig
     */
    public function config();
}