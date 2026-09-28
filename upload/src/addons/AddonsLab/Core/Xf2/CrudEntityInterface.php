<?php

namespace AddonsLab\Core\Xf2;

interface CrudEntityInterface
{
    /**
     * @return int|string
     * The value of the primary key for the entity being displayed
     */
    public function getItemPrimaryKey();

    public function getItemTitle();

    /**
     * @return string
     * The label used in the list of items
     */
    public function getItemListLabel();

    public function getItemListHint();

    /**
     * @return bool If true, the item will be marked as disabled in the list of items
     */
    public function getItemIsDisabled();
}