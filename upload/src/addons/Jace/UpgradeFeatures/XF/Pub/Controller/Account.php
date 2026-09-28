<?php

namespace Jace\UpgradeFeatures\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Account extends XFCP_Account
{
    public function actionUpgrades()
    {
        $response = parent::actionUpgrades();
        
        if ($response instanceof \XF\Mvc\Reply\View)
        {
            // Get existing upgrades from parent response
            $available = $response->getParam('available');
            $purchased = $response->getParam('purchased');
            
            // Initialize arrays
            $upgradeFeatures = [];
            $upgradeFeatureIcons = [];
            $upgradeFeatureUserbars = [];
            $comparisonData = []; // NEW: Pre-processed comparison data
            
            // Get all upgrade IDs we need to process
            $upgradeIds = [];
            if ($available) {
                foreach ($available as $upgrade) {
                    $upgradeIds[] = $upgrade->user_upgrade_id;
                }
            }
            if ($purchased) {
                foreach ($purchased as $purchasedUpgrade) {
                    $upgradeIds[] = $purchasedUpgrade->user_upgrade_id;
                }
            }
            
            if (!empty($upgradeIds)) {
                // Load all features and ORDER BY display_order
                $allFeatures = $this->em()->getFinder('Jace\UpgradeFeatures:UpgradeFeature')
                    ->where('active', 1)
                    ->order('display_order', 'ASC')
                    ->fetch();
                
                // Load existing feature values
                $existingValues = $this->em()->getFinder('Jace\UpgradeFeatures:UpgradeFeatureValue')
                    ->where('user_upgrade_id', $upgradeIds)
                    ->with('Feature')
                    ->fetch();
                
                // Load icons and userbars
                $icons = $this->em()->getFinder('Jace\UpgradeFeatures:UpgradeFeatureIcon')
                    ->where('user_upgrade_id', $upgradeIds)
                    ->fetch();
                
                $userbars = $this->em()->getFinder('Jace\UpgradeFeatures:UpgradeFeatureUserbar')
                    ->where('user_upgrade_id', $upgradeIds)
                    ->fetch();
                
                // Group icons and userbars by upgrade and feature
                $iconsByUpgradeFeature = [];
                foreach ($icons as $icon) {
                    $upgradeId = (int)$icon->user_upgrade_id;
                    $featureId = (int)$icon->feature_id;
                    $iconsByUpgradeFeature[$upgradeId][$featureId] = $icon;
                    $upgradeFeatureIcons[$upgradeId][$featureId] = $icon;
                }
                
                $userbarsByUpgradeFeature = [];
                foreach ($userbars as $userbar) {
                    $upgradeId = (int)$userbar->user_upgrade_id;
                    $featureId = (int)$userbar->feature_id;
                    $userbarsByUpgradeFeature[$upgradeId][$featureId] = $userbar;
                    $upgradeFeatureUserbars[$upgradeId][$featureId] = $userbar;
                }
                
                // Group existing values by upgrade ID and feature ID
                $valuesByUpgrade = [];
                foreach ($existingValues as $value) {
                    $upgradeId = (int)$value->user_upgrade_id;
                    $featureId = (int)$value->feature_id;
                    $valuesByUpgrade[$upgradeId][$featureId] = $value;
                }
                
                // Create feature arrays for each upgrade, including defaults for missing features
                foreach ($upgradeIds as $upgradeId) {
                    $upgradeId = (int)$upgradeId;
                    $upgradeFeatures[$upgradeId] = [];
                    
                    foreach ($allFeatures as $feature) {
                        $featureId = (int)$feature->feature_id;
                        
                        if (isset($valuesByUpgrade[$upgradeId][$featureId])) {
                            // Use existing value
                            $upgradeFeatures[$upgradeId][] = $valuesByUpgrade[$upgradeId][$featureId];
                        } else {
                            // Create a virtual feature value with default
                            $virtualValue = $this->em()->create('Jace\UpgradeFeatures:UpgradeFeatureValue');
                            $virtualValue->user_upgrade_id = $upgradeId;
                            $virtualValue->feature_id = $featureId;
                            $virtualValue->feature_value = $feature->default_value ?: '';
                            $virtualValue->hydrateRelation('Feature', $feature);
                            
                            $upgradeFeatures[$upgradeId][] = $virtualValue;
                        }
                    }
                    
                    // Convert to collection
                    $upgradeFeatures[$upgradeId] = $this->em()->getBasicCollection($upgradeFeatures[$upgradeId]);
                }
                
                // NEW: Pre-process comparison table data
                $comparisonData = $this->prepareComparisonData($allFeatures, $valuesByUpgrade, $upgradeIds, $iconsByUpgradeFeature, $userbarsByUpgradeFeature);
            }
            
            $response->setParams([
                'upgradeFeatures' => $upgradeFeatures,
                'upgradeFeatureIcons' => $upgradeFeatureIcons,
                'upgradeFeatureUserbars' => $upgradeFeatureUserbars,
                'comparisonData' => $comparisonData, // NEW: Pre-processed data for template
            ]);
        }
        
        return $response;
    }
    
    /**
     * NEW: Pre-process comparison data for easy template consumption
     */
    protected function prepareComparisonData($allFeatures, $valuesByUpgrade, $upgradeIds, $iconsByUpgradeFeature, $userbarsByUpgradeFeature)
    {
        $comparisonData = [];
        
        foreach ($allFeatures as $feature) {
            $featureId = (int)$feature->feature_id;
            
            $featureRow = [
                'feature_id' => $featureId,
                'title' => $feature->title,
                'description' => $feature->description,
                'feature_type' => $feature->feature_type,
                'values' => []
            ];
            
            // Get values for each upgrade
            foreach ($upgradeIds as $upgradeId) {
                $upgradeId = (int)$upgradeId;
                
                if (isset($valuesByUpgrade[$upgradeId][$featureId])) {
                    // Use existing value
                    $featureValue = $valuesByUpgrade[$upgradeId][$featureId];
                    $value = $featureValue->feature_value;
                } else {
                    // Use default value
                    $value = $feature->default_value ?: '';
                }
                
                // Process value based on type for display
                $displayValue = $this->processFeatureValueForDisplay($feature->feature_type, $value);
                $displayType = $this->getDisplayType($feature->feature_type, $value);
                
                // Add image data for icons and userbars
                $imageData = null;
                if ($feature->feature_type == 'icon' && isset($iconsByUpgradeFeature[$upgradeId][$featureId])) {
                    $icon = $iconsByUpgradeFeature[$upgradeId][$featureId];
                    // Use the getIconUrl() method instead of icon_url property
                    $iconUrl = $icon->getIconUrl();
                    if ($iconUrl) {
                        $imageData = [
                            'type' => 'icon',
                            'url' => $iconUrl,
                            'alt' => 'Award Icon'
                        ];
                    }
                } elseif ($feature->feature_type == 'userbar' && isset($userbarsByUpgradeFeature[$upgradeId][$featureId])) {
                    $userbar = $userbarsByUpgradeFeature[$upgradeId][$featureId];
                    // Use the getIconUrl() method instead of userbar_url property
                    $userbarUrl = $userbar->getIconUrl();
                    if ($userbarUrl) {
                        $imageData = [
                            'type' => 'userbar',
                            'url' => $userbarUrl,
                            'alt' => 'Custom Userbar'
                        ];
                    }
                }
                
                $featureRow['values'][$upgradeId] = [
                    'raw_value' => $value,
                    'display_value' => $displayValue,
                    'display_type' => $displayType,
                    'image_data' => $imageData
                ];
            }
            
            $comparisonData[] = $featureRow;
        }
        
        return $comparisonData;
    }
    
    /**
     * NEW: Process feature value for display
     */
    protected function processFeatureValueForDisplay($featureType, $value)
    {
        switch ($featureType) {
            case 'boolean':
                return $value == '1' || $value === true || $value === 1;
                
            case 'numeric':
                return (int)$value;
                
            case 'userbar':
            case 'icon':
                return true; // These will show images or checkmarks
                
            default:
                return $value ?: '';
        }
    }
    
    /**
     * NEW: Get display type for template rendering
     */
    protected function getDisplayType($featureType, $value)
    {
        switch ($featureType) {
            case 'boolean':
                return ($value == '1' || $value === true || $value === 1) ? 'checkmark' : 'cross';
                
            case 'numeric':
                return 'number';
                
            case 'userbar':
                return 'userbar';
                
            case 'icon':
                return 'icon';
                
            default:
                return 'text';
        }
    }
}