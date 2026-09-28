<?php
namespace AddonsLab\Core\Xf2\Service;

use XF\Mvc\Entity\Structure;

class CrudEntity
{
    public function setupCrudGetters(Structure $structure)
    {
        $structure->getters += array(
            'item_list_hint' => true,
            'item_is_disabled' => true,
            'item_list_label' => true,
            'item_primary_key' => true,
            'item_title' => true,
        );
    }
}