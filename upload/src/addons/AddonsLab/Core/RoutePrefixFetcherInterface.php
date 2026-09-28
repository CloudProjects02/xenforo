<?php
namespace AddonsLab\Core;

interface RoutePrefixFetcherInterface
{
    public function getRoute($originalRoute);
}