<?php
namespace AddonsLab\Core\Xf2\Service;

class OptionBuilder extends \AddonsLab\Core\Service\OptionBuilder
{
    public function convertToOptions($flatArray, $selectedValue)
    {
        // xenforo 2 does not accept the same format as xf1, but expects a simple key=>value array
        return $flatArray;
    }

}