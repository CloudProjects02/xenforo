<?php

namespace Jace\UpgradeFeatures\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $user_upgrade_id
 * @property int $feature_id
 * @property string $feature_value
 *
 * RELATIONS
 * @property \Jace\UpgradeFeatures\Entity\UpgradeFeature $Feature
 * @property \XF\Entity\UserUpgrade $UserUpgrade
 */
class UpgradeFeatureValue extends Entity
{
    public function getFormattedValue()
    {
        switch ($this->Feature->feature_type)
        {
            case 'boolean':
                return $this->feature_value ? \XF::phrase('yes') : \XF::phrase('no');
            
            case 'numeric':
                return number_format($this->feature_value);
            
            case 'icon':
                return $this->feature_value ?: \XF::phrase('none');

            case 'userbar':
                return $this->feature_value ?: \XF::phrase('none');
            
            default:
                return $this->feature_value;
        }
    }

    public function getBooleanValue()
    {
        return (bool)$this->feature_value;
    }

    public function getNumericValue()
    {
        return (int)$this->feature_value;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_upgrade_feature_value';
        $structure->shortName = 'Jace\UpgradeFeatures:UpgradeFeatureValue';
        $structure->primaryKey = ['user_upgrade_id', 'feature_id'];
        $structure->columns = [
            'user_upgrade_id' => ['type' => self::UINT, 'required' => true],
            'feature_id' => ['type' => self::UINT, 'required' => true],
            'feature_value' => ['type' => self::STR, 'default' => '']
        ];
        $structure->relations = [
            'Feature' => [
                'entity' => 'Jace\UpgradeFeatures:UpgradeFeature',
                'type' => self::TO_ONE,
                'conditions' => 'feature_id',
                'primary' => true
            ],
            'UserUpgrade' => [
                'entity' => 'XF:UserUpgrade',
                'type' => self::TO_ONE,
                'conditions' => 'user_upgrade_id',
                'primary' => true
            ]
        ];

        return $structure;
    }
}
