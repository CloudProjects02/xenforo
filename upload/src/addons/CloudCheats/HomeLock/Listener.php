<?php

namespace CloudCheats\HomeLock;

class Listener
{
    public static function controllerPreDispatch(
        \XF\Mvc\Controller $controller,
        string $action,
        \XF\Mvc\ParameterBag $params
    ): void {
        if (\XF::visitor()->user_id > 0) {
            return;
        }

        $routePath = \XF::app()->request()->getRoutePath();

        // Routes guests are allowed to access
        $allowed = [
            '',
            'home',
            'login',
            'register',
            'lost-password',
            'two-step',
            'account/email-confirm',
            'misc',
        ];

        foreach ($allowed as $prefix) {
            if ($routePath === $prefix || str_starts_with($routePath, $prefix . '/')) {
                return;
            }
        }

        throw $controller->exception(
            $controller->redirect(
                \XF::app()->router('public')->buildLink('canonical:home')
            )
        );
    }
}
