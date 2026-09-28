<?php

namespace AddonsLab\Core\Xf2\Admin;

use AddonsLab\Core\Xf2\Admin\Field\AbstractRow;
use AddonsLab\Core\Xf2\AdminManagerInterface;
use AddonsLab\Core\Xf2\ControllerTrait\CrudControllerTrait;
use AddonsLab\Core\Xf2\ControllerTrait\AdminAccessTrait;
use AddonsLab\Core\Xf2\CrudControllerInterface;
use AddonsLab\Core\Xf2\CrudEntityInterface;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View;

abstract class CrudController extends AbstractController implements AdminManagerInterface, CrudControllerInterface
{
    use CrudControllerTrait;
    use AdminAccessTrait;

    /**
     * @param $action
     * @param ParameterBag $params
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        parent::preDispatchController($action, $params);

        if ($this->config()->getAdminPermissionId())
        {
            $this->assertPageAccessAdminPermission($action, $params, $this->_getActionAdminPermission(
                $this->config()->getAdminPermissionId(),
                $action, $params
            ));
        }
        if ($parent = $this->_getParentEntity())
        {
            $this->config()->setParentItem($parent);
        }
    }
}
