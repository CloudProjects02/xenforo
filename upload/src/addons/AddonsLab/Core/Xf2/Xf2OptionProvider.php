<?php

namespace AddonsLab\Core\Xf2;

use AddonsLab\Core\OptionProviderInterface;

class Xf2OptionProvider implements OptionProviderInterface
{
    public function getOption($optionId, $subOption = null)
    {
        $option = \XF::options()->offsetGet($optionId);

        if ($subOption !== null) {
            return $option[$subOption];
        }

        return $option;
    }

    public function setOption($optionId, $subOption, $value = null)
    {
        if ($value === null) {
            $value = $subOption;
            $subOption = null;
            \XF::options()->offsetSet($optionId, $value);
        } else {
            $option=$this->getOption($optionId);
            $option[$subOption]=$value;
            \XF::options()->offsetSet($optionId, $option);
        }
        
    }

    public function getBoardTitle()
    {
        return $this->getOption('boardTitle');
    }
    
    public function getBoardUrl()
    {
        return $this->getOption('boardUrl');
    }
}