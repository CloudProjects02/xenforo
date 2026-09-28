<?php


namespace AddonsLab\Core\Xf2;


use AddonsLab\Core\RoutePrefixFetcherInterface;

class RoutePrefixFetcher implements RoutePrefixFetcherInterface
{
    public function getRoute($originalRoute)
    {
        $route = $originalRoute;
        $originalRoute = explode(':', $originalRoute);
        $type = 'public';

        if (in_array($originalRoute[0], ['admin', 'public']))
        {
            $type = array_shift($originalRoute);
            $route = implode(':', $originalRoute);
        }

        return \XF::app()->router($type)->applyRouteFilterToUrl($route, $route);
    }
}