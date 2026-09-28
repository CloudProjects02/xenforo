<?php


namespace AddonsLab\Core\vB3;


use AddonsLab\Core\RoutePrefixFetcherInterface;

class RoutePrefixFetcher implements RoutePrefixFetcherInterface
{
    public function getRoute($originalRoute)
    {
        return $originalRoute;
    }

}