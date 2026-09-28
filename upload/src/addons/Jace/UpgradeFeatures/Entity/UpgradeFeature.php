<?php

namespace Jace\UpgradeFeatures\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/** * COLUMNS
 * @property int $feature_id
 * @property string $title
 * @property string $description
 * @property string $feature_type
 * @property string $default_value
 * @property int $display_order
 * @property bool $active
 * @property string $icon_ext
 * @property int $icon_date
 *
 * RELATIONS
 * @property \XF\Mvc\Entity\AbstractCollection|Jace\UpgradeFeatures\Entity\UpgradeFeatureValue[] $FeatureValues
 */
class UpgradeFeature extends Entity
{
    public function getFeatureTypes()
    {
        return [
            'boolean' => \XF::phrase('upgrade_feature_type_boolean'),
            'numeric' => \XF::phrase('upgrade_feature_type_numeric'),
            'icon' => \XF::phrase('upgrade_feature_type_icon'),
            'userbar' => \XF::phrase('upgrade_feature_type_userbar')
        ];
    }

    public function canEdit()
    {
        return \XF::visitor()->hasAdminPermission('userUpgrade');
    }    
    public function canDelete()
    {
        return \XF::visitor()->hasAdminPermission('userUpgrade');
    }

    public function getAbstractedFeatureIconPath($extension)
    {
        $featureId = $this->feature_id;

        return sprintf('data://jace/upgradefeatures/icons/%d.%s',
            $featureId,
            $extension
        );
    }

    protected function _preSave()
    {
        if ($this->isChanged('feature_type') && $this->feature_type === 'boolean')
        {
            $this->default_value = $this->default_value ? '1' : '0';
        }
    }    
    protected function _postDelete()
    {
        // Delete all feature values for this feature
        $this->db()->delete('xf_upgrade_feature_value', 'feature_id = ?', $this->feature_id);
        
        // Delete all feature icons for this feature
        $this->db()->delete('xf_upgrade_feature_icon', 'feature_id = ?', $this->feature_id);
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_upgrade_feature';
        $structure->shortName = 'Jace\UpgradeFeatures:UpgradeFeature';
        $structure->primaryKey = 'feature_id';        
        $structure->columns = [
            'feature_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'category_id' => ['type' => self::UINT, 'default' => 0],
            'title' => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
            'description' => ['type' => self::STR, 'default' => ''],
            'feature_type' => ['type' => self::STR, 'required' => true, 'allowedValues' => ['boolean', 'numeric', 'icon', 'userbar']],
            'default_value' => ['type' => self::STR, 'default' => '', 'nullable' => true],
            'display_order' => ['type' => self::UINT, 'default' => 10],
            'active' => ['type' => self::BOOL, 'default' => true]
        ];
        $structure->getters = [];
        $structure->relations = [
            'Category' => [
                'entity' => 'Jace\UpgradeFeatures:UpgradeFeatureCategory',
                'type' => self::TO_ONE,
                'conditions' => 'category_id',
                'primary' => true
            ],
            'FeatureValues' => [
                'entity' => 'Jace\UpgradeFeatures:UpgradeFeatureValue',
                'type' => self::TO_MANY,
                'conditions' => 'feature_id'
            ]
        ];

        return $structure;
    }
}
