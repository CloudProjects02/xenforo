<?php

namespace AddonsLab\Core\Xf2\Pub;

use AddonsLab\Core\Xf2\Admin\EntityConfig;
use AddonsLab\Core\Xf2\ControllerTrait\CrudControllerTrait;
use AddonsLab\Core\Xf2\CrudControllerInterface;
use AddonsLab\Core\Xf2\PublicManagerInterface;
use XF\Mvc\Entity\Finder;
use XF\Pub\Controller\AbstractController;

abstract class CrudController extends AbstractController implements PublicManagerInterface, CrudControllerInterface
{
    use CrudControllerTrait;
}