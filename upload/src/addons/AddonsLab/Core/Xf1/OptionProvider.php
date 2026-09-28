<?php
namespace AddonsLab\Core\Xf1;

use AddonsLab\Core\OptionProviderInterface;

class OptionProvider implements OptionProviderInterface
{
    public function getOption($optionId, $subOption = null)
    {
        return \XenForo_Application::getOptions()->get($optionId, $subOption);
    }

    public function setOption($optionId, $subOption, $value = null)
    {
        \XenForo_Application::getOptions()->set($optionId, $subOption, $value);
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