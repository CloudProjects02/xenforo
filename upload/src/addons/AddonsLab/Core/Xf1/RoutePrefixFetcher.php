<?php


namespace AddonsLab\Core\Xf1;


use AddonsLab\Core\RoutePrefixFetcherInterface;

class RoutePrefixFetcher implements RoutePrefixFetcherInterface
{
    public function getRoute($originalRoute)
    {
        return $originalRoute;
    }

}