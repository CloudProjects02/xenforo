<?php

namespace Jace\UpgradeFeatures\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

/**
 * @method \XF\Mvc\Entity\Finder|\UpgradeFeatures\Entity\UpgradeFeature[] findUpgradeFeatures()
 */
class UpgradeFeature extends Repository
{
    public function getAvailableFeatures($categoryId = null)
    {
        $finder = $this->finder('Jace\UpgradeFeatures:UpgradeFeature')
            ->where('active', 1)
            ->order('display_order');

        if ($categoryId !== null)
        {
            $finder->where('category_id', $categoryId);
        }

        return $finder->fetch();
    }

    public function getFeaturesByCategory()
    {
        $features = $this->getAvailableFeatures();
        $categorizedFeatures = [];

        foreach ($features as $feature)
        {
            $categoryId = $feature->category_id ?: 0;
            if (!isset($categorizedFeatures[$categoryId]))
            {
                $categorizedFeatures[$categoryId] = [];
            }
            $categorizedFeatures[$categoryId][] = $feature;
        }

        return $categorizedFeatures;
    }

    public function findUpgradeFeaturesForList()
    {
        return $this->finder('Jace\UpgradeFeatures:UpgradeFeature')
            ->where('active', 1)
            ->order('display_order', 'ASC');
    }

    public function getUpgradeFeatureOptions()
    {
        $features = $this->findUpgradeFeaturesForList()->fetch();
        $options = [];

        foreach ($features as $feature)
        {
            $options[$feature->feature_id] = $feature->title;
        }

        return $options;
    }    public function getFeaturesForUpgrade($userUpgradeId)
    {
        return $this->finder('Jace\UpgradeFeatures:UpgradeFeatureValue')
            ->with('Feature')
            ->where('user_upgrade_id', $userUpgradeId)
            ->keyedBy('feature_id')
            ->fetch();
    }

    // public function getAvailableFeatures()
    // {
    //     return $this->findUpgradeFeaturesForList()->keyedBy('feature_id')->fetch();
    // }

    public function saveUpgradeFeatures($userUpgradeId, array $featureData)
    {
        $db = $this->db();
        $db->beginTransaction();

        try
        {
            // Delete existing feature values
            $db->delete('xf_upgrade_feature_value', 'user_upgrade_id = ?', $userUpgradeId);

            // Insert new feature values
            foreach ($featureData as $featureId => $value)
            {
                if ($value !== null && $value !== '')
                {
                    /** @var \Jace\UpgradeFeatures\Entity\UpgradeFeatureValue $featureValue */
                    $featureValue = $this->em->create('Jace\UpgradeFeatures:UpgradeFeatureValue');
                    $featureValue->user_upgrade_id = $userUpgradeId;
                    $featureValue->feature_id = $featureId;
                    $featureValue->feature_value = $value;
                    $featureValue->save();
                }
            }

            $db->commit();
            return true;
        }
        catch (\Exception $e)
        {
            $db->rollback();
            \XF::logException($e);
            return false;
        }
    }

    public function saveUpgradeFeatureIcon($userUpgradeId, $featureId, $iconData, $filename)
    {
        /** @var \Jace\UpgradeFeatures\Entity\UpgradeFeatureIcon $icon */
        $icon = $this->em->findOne('Jace\UpgradeFeatures:UpgradeFeatureIcon', [
            'feature_id' => $featureId,
            'user_upgrade_id' => $userUpgradeId
        ]);

        if (!$icon)
        {
            $icon = $this->em->create('Jace\UpgradeFeatures:UpgradeFeatureIcon');
            $icon->feature_id = $featureId;
            $icon->user_upgrade_id = $userUpgradeId;
        }

        $icon->icon_data = $iconData;
        $icon->icon_filename = $filename;
        $icon->icon_size = strlen($iconData);
        $icon->upload_date = \XF::$time;

        return $icon->save();
    }

    protected function getCategoryRepo()
    {
        return $this->repository('Jace\UpgradeFeatures:UpgradeFeatureCategory');
    }
}
