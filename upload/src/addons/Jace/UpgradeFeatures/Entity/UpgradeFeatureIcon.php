<?php

namespace Jace\UpgradeFeatures\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $feature_id
 * @property int $user_upgrade_id
 * @property string $icon_ext
 * @property int $icon_date
 * @property int $upload_date
 *
 * RELATIONS
 * @property \Jace\UpgradeFeatures\Entity\UpgradeFeature $Feature
 * @property \XF\Entity\UserUpgrade $UserUpgrade
 */
class UpgradeFeatureIcon extends Entity
{       
    public function getIconUrl($sizeCode = null, $canonical = false)
    {
        $app = $this->app();

        if ($this->icon_date)
        {
            $extension = $this->icon_ext;

            return $app->applyExternalDataUrl(
                "jace/upgradefeatures/icons/{$this->feature_id}_{$this->user_upgrade_id}.{$extension}?{$this->icon_date}",
                $canonical
            );
        }
        else
        {
            return null;
        }
    }

    public function getAbstractedFeatureIconPath($extension = null)
    {
        if ($extension === null)
        {
            $extension = $this->icon_ext;
        }

        return sprintf('data://jace/upgradefeatures/icons/%d_%d.%s',
            $this->feature_id,
            $this->user_upgrade_id,
            $extension
        );
    }

    protected function _postDelete()
    {
        $this->deleteIconFile();
    }    

    protected function deleteIconFile()
    {
        if ($this->icon_ext)
        {
            $iconFile = $this->getAbstractedFeatureIconPath();
            \XF\Util\File::deleteFromAbstractedPath($iconFile);
        }
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_upgrade_feature_icon';
        $structure->shortName = 'Jace\UpgradeFeatures:UpgradeFeatureIcon';
        $structure->primaryKey = ['feature_id', 'user_upgrade_id'];
        $structure->columns = [
            'feature_id' => ['type' => self::UINT, 'required' => true],
            'user_upgrade_id' => ['type' => self::UINT, 'required' => true],
            'upload_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'icon_ext' => ['type' => self::STR, 'maxLength' => 10, 'default' => ''],
            'icon_date' => ['type' => self::UINT, 'default' => 0]
        ];
        $structure->getters = [];
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