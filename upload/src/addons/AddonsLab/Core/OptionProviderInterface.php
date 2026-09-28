<?php
namespace AddonsLab\Core;

interface OptionProviderInterface
{
    public function getOption($optionId, $subOption = null);
    
    public function setOption($optionId, $subOption, $value = null);

    public function getBoardTitle();
    public function getBoardUrl();
}