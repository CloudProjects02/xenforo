<?php

namespace AddonsLab\Core;

/**
 * Class LazyLoadList
 * @package AddonsLab\Core
 * Allows adding any number of items to the list which will be imploded only when the list is used as a string
 */
class LazyLoadList
{
    protected $list = array();

    protected $delimiter;

    /**
     * LazyLoadList constructor.
     * @param string $delimiter
     */
    public function __construct($delimiter)
    {
        $this->delimiter = $delimiter;
    }


    public function addItem($item)
    {
        $this->list[] = $item;

        return $this;
    }

    public function __toString()
    {
        return implode($this->delimiter, $this->list);
    }


}