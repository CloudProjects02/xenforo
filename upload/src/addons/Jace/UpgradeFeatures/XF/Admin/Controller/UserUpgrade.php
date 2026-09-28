<?php

namespace Jace\UpgradeFeatures\XF\Admin\Controller;

use XF\Mvc\ParameterBag;

class UserUpgrade extends XFCP_UserUpgrade
{
    public function upgradeAddEdit(\XF\Entity\UserUpgrade $upgrade)
    {
        $response = parent::upgradeAddEdit($upgrade);

        if ($response instanceof \XF\Mvc\Reply\View)
        {
            $featureRepo = $this->repository('Jace\UpgradeFeatures:UpgradeFeature');
            $availableFeatures = $featureRepo->getAvailableFeatures();
            $upgradeFeatures = $featureRepo->getFeaturesForUpgrade($upgrade->user_upgrade_id);
            
            // Load feature icons for this upgrade
            $featureIcons = [];
            if ($upgrade->user_upgrade_id)
            {
                $icons = $this->em()->getFinder('Jace\UpgradeFeatures:UpgradeFeatureIcon')
                    ->where('user_upgrade_id', $upgrade->user_upgrade_id)
                    ->keyedBy('feature_id')
                    ->fetch();
                $featureIcons = $icons->toArray();
            }
            
            $featureUserbars = [];
            if ($upgrade->user_upgrade_id)
            {
                $userbars = $this->em()->getFinder('Jace\UpgradeFeatures:UpgradeFeatureUserbar')
                    ->where('user_upgrade_id', $upgrade->user_upgrade_id)
                    ->keyedBy('feature_id')
                    ->fetch();
                $featureUserbars = $userbars->toArray();
            }

            $response->setParam('availableFeatures', $availableFeatures);
            $response->setParam('upgradeFeatures', $upgradeFeatures);
            $response->setParam('featureIcons', $featureIcons);
            $response->setParam('featureUserbars', $featureUserbars);
        }

        return $response;
    }    
    
    protected function upgradeSaveProcess(\XF\Entity\UserUpgrade $upgrade)
    {
        $form = parent::upgradeSaveProcess($upgrade);
        
        // Handle the is_featured field manually instead of using setupEntityInput
        $isFeatured = $this->filter('is_featured', 'bool');
        $upgrade->is_featured = $isFeatured;
        
        $featureData = $this->filter('features', 'array');

        $form->complete(function() use ($upgrade, $featureData)
        {
            $featureRepo = $this->repository('Jace\UpgradeFeatures:UpgradeFeature');

            // Process and flatten the feature data
            $processedFeatureData = [];
            foreach ($featureData as $featureId => $data)
            {
                if (is_array($data))
                {
                    if (isset($data['enabled']) && $data['enabled'] == 1)
                    {
                        // Boolean feature
                        $processedFeatureData[$featureId] = '1';
                    }
                    elseif (isset($data['value']) && $data['value'] !== '')
                    {
                        // Numeric feature
                        $processedFeatureData[$featureId] = $data['value'];
                    }
                }
                else
                {
                    // Direct value
                    $processedFeatureData[$featureId] = $data;
                }
            }

            // Save the processed feature data
            $featureRepo->saveUpgradeFeatures($upgrade->user_upgrade_id, $processedFeatureData);

            // Handle icon uploads for features that have icons
            $availableFeatures = $featureRepo->getAvailableFeatures();
            foreach ($availableFeatures as $feature)
            {
                if ($feature->feature_type === 'icon')
                {
                    $upload = $this->request->getFile('feature_icon_' . $feature->feature_id);
                    if ($upload && $upload->isValid())
                    {
                        // Find or create the UpgradeFeatureIcon entity
                        $featureIcon = $this->em()->findOne('Jace\UpgradeFeatures:UpgradeFeatureIcon', [
                            'feature_id' => $feature->feature_id,
                            'user_upgrade_id' => $upgrade->user_upgrade_id
                        ]);
                        
                        if (!$featureIcon)
                        {
                            $featureIcon = $this->em()->create('Jace\UpgradeFeatures:UpgradeFeatureIcon');
                            $featureIcon->feature_id = $feature->feature_id;
                            $featureIcon->user_upgrade_id = $upgrade->user_upgrade_id;
                        }
                        
                        $iconService = $this->service('Jace\UpgradeFeatures:FeatureIcon', $featureIcon);
                        $iconService->logIp(true);
                        
                        if ($iconService->setImageFromUpload($upload))
                        {
                            if (!$iconService->updateFeatureIcon())
                            {
                                \XF::logError('Failed to upload feature icon: ' . $iconService->getError());
                            }
                        }
                        else
                        {
                            \XF::logError('Failed to validate feature icon upload: ' . $iconService->getError());
                        }
                    }
                }
                elseif ($feature->feature_type === 'userbar')
                {
                    $upload = $this->request->getFile('feature_userbar_' . $feature->feature_id);
                    if ($upload && $upload->isValid())
                    {
                        // Find or create the UpgradeFeatureUserbar entity
                        $featureUserbar = $this->em()->findOne('Jace\UpgradeFeatures:UpgradeFeatureUserbar', [
                            'feature_id' => $feature->feature_id,
                            'user_upgrade_id' => $upgrade->user_upgrade_id
                        ]);

                        if (!$featureUserbar)
                        {
                            $featureUserbar = $this->em()->create('Jace\UpgradeFeatures:UpgradeFeatureUserbar');
                            $featureUserbar->feature_id = $feature->feature_id;
                            $featureUserbar->user_upgrade_id = $upgrade->user_upgrade_id;
                        }
                        
                        $userbarService = $this->service('Jace\UpgradeFeatures:FeatureUserbar', $featureUserbar);
                        $userbarService->logIp(true);
                        
                        if ($userbarService->setImageFromUpload($upload))
                        {
                            if (!$userbarService->updateFeatureUserbar())
                            {
                                \XF::logError('Failed to upload userbar: ' . $userbarService->getError());
                            }
                        }
                        else
                        {
                            \XF::logError('Failed to validate userbar upload: ' . $userbarService->getError());
                        }
                    }
                }
            }
        });

        return $form;
    }
}