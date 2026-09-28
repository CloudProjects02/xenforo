<?php

namespace Jace\UpgradeFeatures\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $category_id
 * @property int $display_order
 * @property string $display_mode
 * @property bool $overwrite
 * 
 * GETTERS
 * @property string $title
 * @property string $description
 * @property array $display_mode_options
 * 
 * RELATIONS
 * @property \XF\Entity\Phrase $MasterTitle
 * @property \XF\Entity\Phrase $MasterDescription
 * @property \XF\Mvc\Entity\AbstractCollection|\Jace\UpgradeFeatures\Entity\UpgradeFeature[] $Features
 */
class UpgradeFeatureCategory extends Entity
{
    public function getTitle()
    {
        return \XF::phrase($this->getTitlePhraseName());
    }

    public function getDescription()
    {
        return \XF::phrase($this->getDescriptionPhraseName());
    }

    public function getTitlePhraseName()
    {
        if ($this->category_id)
        {
            return 'upgrade_features_cat_title.' . $this->category_id;
        }
        else
        {
            return 'upgrade_features_cat_title.uncategorized';
        }
    }

    public function getDescriptionPhraseName()
    {
        if ($this->category_id)
        {
            return 'upgrade_features_cat_desc.' . $this->category_id;
        }
        else
        {
            return 'upgrade_features_cat_desc.uncategorized';
        }
    }

    public function getMasterTitlePhrase()
    {
        $phrase = $this->MasterTitle;
        if (!$phrase)
        {
            $phrase = $this->_em->create('XF:Phrase');
            $phrase->title = $this->_getDeferredValue(function() { return $this->getTitlePhraseName(); });
            $phrase->language_id = 0;
            $phrase->addon_id = 'Jace/UpgradeFeatures';
        }

        return $phrase;
    }

    public function getMasterDescriptionPhrase()
    {
        $phrase = $this->MasterDescription;
        if (!$phrase)
        {
            $phrase = $this->_em->create('XF:Phrase');
            $phrase->title = $this->_getDeferredValue(function() { return $this->getDescriptionPhraseName(); });
            $phrase->language_id = 0;
            $phrase->addon_id = 'Jace/UpgradeFeatures';
        }

        return $phrase;
    }

    public function isModeStep()
    {
        return $this->isMode('step');
    }

    public function isMode($modes)
    {
        if (!is_array($modes))
        {
            $modes = [$modes];
        }

        return in_array($this->display_mode, $modes);
    }

    protected function _postDelete()
    {
        if ($this->MasterTitle)
        {
            $this->MasterTitle->delete();
        }
        if ($this->MasterDescription)
        {
            $this->MasterDescription->delete();
        }

        if ($this->getOption('delete_features'))
        {
            $features = $this->finder('Jace\UpgradeFeatures:UpgradeFeature')
                ->where('category_id', $this->category_id)->fetch();

            foreach ($features as $feature)
            {
                $feature->delete();
            }
        }
        else
        {
            $this->db()->update('xf_upgrade_feature', [
                'category_id' => 0
            ], 'category_id = ?', $this->category_id);
        }
    }

    public function getDisplayModeOptions()
    {
        $options = [];

        if (isset($this->_structure->columns['display_mode']['allowedValues']))
        {
            $modes = $this->_structure->columns['display_mode']['allowedValues'];
            foreach ($modes as $mode)
            {
                $options[$mode] = \XF::phrase('upgrade_features_cat_display_mode_' . $mode);
            }
        }

        return $options;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_upgrade_feature_category';
        $structure->shortName = 'Jace\UpgradeFeatures:UpgradeFeatureCategory';
        $structure->primaryKey = 'category_id';
        $structure->columns = [
            'category_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true,
                'unique' => 'category_ids_must_be_unique'
            ],
            'display_order' => ['type' => self::UINT, 'default' => 0],
            'display_mode' => ['type' => self::STR, 'default' => 'visible',
                'allowedValues' => ['visible', 'step', 'hidden']
            ],
            'overwrite' => ['type' => self::BOOL, 'default' => false],
        ];
        $structure->getters = [
            'title' => true,
            'description' => true,
            'display_mode_options' => true,
        ];

        $structure->options = [
            'delete_features' => false,
        ];

        $structure->relations = [
            'MasterTitle' => [
                'entity' => 'XF:Phrase',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['language_id', '=', 0],
                    ['title', '=', 'upgrade_features_cat_title.', '$category_id']
                ]
            ],
            'MasterDescription' => [
                'entity' => 'XF:Phrase',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['language_id', '=', 0],
                    ['title', '=', 'upgrade_features_cat_desc.', '$category_id']
                ]
            ],
            'Features' => [
                'entity' => 'Jace\UpgradeFeatures:UpgradeFeature',
                'type' => self::TO_MANY,
                'conditions' => [
                    ['category_id', '=', '$category_id']
                ]
            ],
        ];

        return $structure;
    }
}