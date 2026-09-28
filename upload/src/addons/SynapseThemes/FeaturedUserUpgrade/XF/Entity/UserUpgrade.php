<?php

namespace SynapseThemes\FeaturedUserUpgrade\XF\Entity;

class UserUpgrade extends XFCP_UserUpgrade
{
    protected function _setupDefaults()
    {
        parent::_setupDefaults();
        $this->is_featured = false;
    }

    public static function getStructure(\XF\Mvc\Entity\Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['is_featured'] = ['type' => self::BOOL, 'default' => false];

        return $structure;
    }
} 