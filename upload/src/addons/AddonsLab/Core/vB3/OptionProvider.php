<?php
namespace AddonsLab\Core\vB3;

use AddonsLab\Core\OptionProviderInterface;

class OptionProvider implements OptionProviderInterface
{
    /**
     * @var \vB_Registry
     */
    protected $vb;
    
    /**
     * OptionProvider constructor.
     */
    public function __construct()
    {
        $this->vb=$GLOBALS['vbulletin'];
    }

    public function getOption($optionId, $subOption = null)
    {
        $option=$this->vb->options[$optionId];
        
        if(!empty($subOption)) {
            return $option[$subOption];
        }
        
        return $option;
    }

    public function setOption($optionId, $subOption, $value = null)
    {
        if ($value === null) {
            $value = $subOption;
            $subOption = null;
            $this->vb->options[$optionId]=$value;
        } else {
            $option = $this->getOption($optionId);
            $option[$subOption] = $value;
            $this->vb->options[$optionId] = $option;
        }
    }

    public function getBoardTitle()
    {
        return $this->getOption('bbtitle');
    }
    
    public function getBoardUrl()
    {
        return $this->getOption('bburl');
    }
}