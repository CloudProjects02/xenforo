<?php

namespace XenGenTr\XGTStyleV13\XF\Entity;

class Node extends XFCP_Node {
    public static function getStructure(\XF\Mvc\Entity\Structure $structure) {
        $structure = parent::getStructure($structure);
        $structure->columns['xgt_grid_etkin'] = ['type' => \XF\Mvc\Entity\Entity::BOOL, 'default' => false];
        $structure->columns['xgt_forum_renk'] = ['type' => \XF\Mvc\Entity\Entity::STR, 'default' => '', 'nullable' => true, 'maxLength' => 50];
        $structure->getters['xgt_mega_grid_etkin'] = true;
        return $structure;
    }

    public function getXgtMegaGridEtkin() {
        if ($this->node_type_id === 'Category') {
            return (bool)$this->xgt_grid_etkin;
        }
        if ($this->parent_node_id) {
            $parentNode = $this->em()->find('XF:Node', $this->parent_node_id);
            if ($parentNode && $parentNode->node_type_id === 'Category') {
                return (bool)$parentNode->xgt_grid_etkin;
            }
        }
        return false;
    }

}
