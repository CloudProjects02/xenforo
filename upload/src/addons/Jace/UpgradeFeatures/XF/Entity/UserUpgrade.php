<?php

namespace Jace\UpgradeFeatures\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * RELATIONS
 * @property \XF\Mvc\Entity\AbstractCollection|\Jace\UpgradeFeatures\Entity\UpgradeFeatureValue[] $UpgradeFeatures
 * @property \XF\Mvc\Entity\AbstractCollection|\Jace\UpgradeFeatures\Entity\UpgradeFeatureIcon[] $UpgradeFeatureIcons
 */
class UserUpgrade extends XFCP_UserUpgrade
{
    public function getUpgradeFeatures()
    {
        $features = [];
        foreach ($this->UpgradeFeatures as $feature) {
            $features[$feature->feature_id] = $feature;
        }
        return $features;
    }

    public function getFeatureValue($featureId, $default = null)
    {
        $features = $this->getUpgradeFeatures();
        return isset($features[$featureId]) ? $features[$featureId]->feature_value : $default;
    }

    public function hasFeature($featureId)
    {
        $features = $this->getUpgradeFeatures();
        return isset($features[$featureId]);
    }

    public function getFeatureIcon($featureId)
    {
        foreach ($this->UpgradeFeatureIcons as $icon)
        {
            if ($icon->feature_id == $featureId)
            {
                return $icon;
            }
        }
        return null;
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        // Clean up feature values and icons
        $this->db()->delete('xf_upgrade_feature_value', 'user_upgrade_id = ?', $this->user_upgrade_id);
        
        $icons = $this->finder('Jace\UpgradeFeatures:UpgradeFeatureIcon')
            ->where('user_upgrade_id', $this->user_upgrade_id)
            ->fetch();
        foreach ($icons as $icon)
        {
            $icon->delete();
        }
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);
        $structure->columns['is_featured'] = ['type' => self::BOOL, 'default' => false];

        $structure->relations['UpgradeFeatures'] = [
            'entity' => 'Jace\UpgradeFeatures:UpgradeFeatureValue',
            'type' => self::TO_MANY,
            'conditions' => 'user_upgrade_id'
        ];

        $structure->relations['UpgradeFeatureIcons'] = [
            'entity' => 'Jace\UpgradeFeatures:UpgradeFeatureIcon',
            'type' => self::TO_MANY,
            'conditions' => 'user_upgrade_id'
        ];

        return $structure;
    }
}
