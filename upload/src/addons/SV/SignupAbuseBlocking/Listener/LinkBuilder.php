<?php

namespace SV\SignupAbuseBlocking\Listener;

use XF\Mvc\RouteBuiltLink;
use XF\Mvc\Router;

abstract class LinkBuilder
{
    private function __construct() { }

    public static function publicLinkBuilder(\SV\StandardLib\Repository\LinkBuilder $linkBuilder, Router $router): void
    {
        $linkBuilder->injectLinkBuilderCallbackForSubsection($router, 'register', '', [self::class, 'rewriteRegisterLink']);
        $linkBuilder->injectLinkBuilderCallbackForSubsection($router, 'register', 'connected-accounts', [self::class, 'rewriteExternalAssociateRegisterLink']);
        $linkBuilder->injectLinkBuilderCallback($router, 'logout', [self::class, 'rewriteLogoutLink']);
    }

    /**
     * @param string $prefix
     * @param array  $route
     * @param string $action
     * @param mixed  $data
     * @param array  $params
     * @param Router $router
     * @param bool   $suppressDefaultCallback
     * @return RouteBuiltLink|string|false|null
     * @noinspection PhpUnusedParameterInspection
     */
    public static function rewriteLogoutLink(string &$prefix, array &$route, string &$action, &$data, array &$params, Router $router, bool $suppressDefaultCallback)
    {
        if ($prefix === 'logout')
        {
            if ($action === '')
            {
                $prefix = 'login';
                $action = 'logout';
            }
        }

        return null;
    }

    /**
     * @param string $prefix
     * @param array  $route
     * @param string $action
     * @param mixed  $data
     * @param array  $params
     * @param Router $router
     * @param bool   $suppressDefaultCallback
     * @return RouteBuiltLink|string|false|null
     * @noinspection PhpUnusedParameterInspection
     */
    public static function rewriteRegisterLink(string &$prefix, array &$route, string &$action, &$data, array &$params, Router $router, bool $suppressDefaultCallback)
    {
        if ($prefix === 'register')
        {
            if ($action === '')
            {
                $prefix = 'login';
                $action = 'register';
            }
            else if ($action === 'register')
            {
                $prefix = 'login';
                $action = 'register-register';
            }
        }

        return null;
    }

    /**
     * @param string $prefix
     * @param array  $route
     * @param string $action
     * @param mixed  $data
     * @param array  $params
     * @param Router $router
     * @param bool   $suppressDefaultCallback
     * @return RouteBuiltLink|string|false|null
     * @noinspection PhpUnusedParameterInspection
     */
    public static function rewriteExternalAssociateRegisterLink(string &$prefix, array &$route, string &$action, &$data, array &$params, Router $router, bool $suppressDefaultCallback)
    {
        if ($prefix === 'register' && in_array($action, ['', 'associate', 'register'], true))
        {
            $prefix = 'login/register';
        }

        return null;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
