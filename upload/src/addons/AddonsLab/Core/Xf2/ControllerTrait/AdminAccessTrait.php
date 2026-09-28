<?php

namespace AddonsLab\Core\Xf2\ControllerTrait;

use AddonsLab\Core\Xf2\Admin\AdminAccessException;
use XF\Mvc\ParameterBag;

trait AdminAccessTrait
{
    /**
     * @return void
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertPageAccessAdminPermission($action, ParameterBag $params, $permissionId)
    {
        try
        {
            $this->assertAdminPermission($permissionId);
        }
        catch (AdminAccessException $ex)
        {
            // Allows specific products to throw this exception as a custom way of handling access
            // to the page
            throw $this->exception($this->noPermission($ex->getMessage()));
        }
    }

    protected function _getActionAdminPermission(string $permissionId, string $action, ParameterBag $params): string
    {
        return $permissionId;
    }
}