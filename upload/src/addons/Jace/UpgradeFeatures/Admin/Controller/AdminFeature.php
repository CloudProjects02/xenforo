<?php

namespace Jace\UpgradeFeatures\Admin\Controller;

use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class AdminFeature extends \XF\Admin\Controller\AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('upgradeFeatures');
    }

    public function actionIndex(ParameterBag $params)
    {
        $page = $this->filterPage();
        $perPage = 20;

        if ($this->isPost())
        {
            $featureIds = $this->filter('feature_ids', 'array-uint');
            if ($featureIds && $this->filter('delete', 'bool'))
            {
                return $this->actionDelete(new ParameterBag(['feature_id' => $featureIds]));
            }
        }

        $featureRepo = $this->getFeatureRepo();
        $categoryRepo = $this->getCategoryRepo();

        $categories = $categoryRepo->findCategoriesForList(true);

        $featureFinder = $featureRepo->finder('Jace\UpgradeFeatures:UpgradeFeature')
            ->with('Category')
            ->order(['Category.display_order', 'display_order']);

        $filterInput = $this->filter([
            'text' => 'str',
            'category_id' => 'uint'
        ]);

        if ($filterInput['text'] !== '')
        {
            $featureFinder->where('title', 'like', $featureFinder->escapeLike($filterInput['text'], '%?%'));
        }

        if ($filterInput['category_id'])
        {
            $featureFinder->where('category_id', $filterInput['category_id']);
        }

        $features = $featureFinder->fetch();

        // Group features by category
        $featuresByCategory = [];
        $totalFeatures = 0;

        foreach ($features as $feature)
        {
            $categoryId = $feature->category_id ?: 0;
            if (!isset($featuresByCategory[$categoryId]))
            {
                $featuresByCategory[$categoryId] = [];
            }
            $featuresByCategory[$categoryId][$feature->feature_id] = $feature;
            $totalFeatures++;
        }

        // Ensure all categories are represented
        $featureCategories = [];
        foreach ($categories as $category)
        {
            $featureCategories[$category->category_id] = $category;
        }

        $viewParams = [
            'featureData' => [
                'features' => $featuresByCategory,
                'featureCategories' => $featureCategories,
                'totalFeatures' => $totalFeatures
            ],
            'filters' => $filterInput,
            'total' => $totalFeatures,
            'page' => $page,
            'perPage' => $perPage
        ];

        return $this->view('Jace\UpgradeFeatures:UpgradeFeature\Listing', 'upgrade_feature_list', $viewParams);
    }

    public function featureAddEdit(\Jace\UpgradeFeatures\Entity\UpgradeFeature $feature)
    {
        $categoryRepo = $this->getCategoryRepo();
        $categoryOptions = $categoryRepo->getCategoryTitlePairs();
        $categoryOptions = [0 => \XF::phrase('upgrade_features_cat_title.uncategorized')] + $categoryOptions;

        $viewParams = [
            'feature' => $feature,
            'categoryOptions' => $categoryOptions
        ];
        return $this->view('Jace\UpgradeFeatures:UpgradeFeature\Edit', 'upgrade_feature_edit', $viewParams);
    }

    public function actionEdit(ParameterBag $params)
    {
        $feature = $this->assertFeatureExists($params['feature_id']);
        return $this->featureAddEdit($feature);
    }

    public function actionAdd()
    {
        $feature = $this->em()->create('Jace\UpgradeFeatures:UpgradeFeature');
        return $this->featureAddEdit($feature);
    }

    protected function featureSaveProcess(\Jace\UpgradeFeatures\Entity\UpgradeFeature $feature)
    {
        $entityInput = $this->filter([
            'category_id' => 'uint',
            'title' => 'str',
            'description' => 'str',
            'feature_type' => 'str',
            'display_order' => 'uint',
            'active' => 'bool'
        ]);

        $form = $this->formAction();
        $form->basicEntitySave($feature, $entityInput);
        return $form;
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params['feature_id'])
        {
            $feature = $this->assertFeatureExists($params['feature_id']);
        }
        else
        {
            $feature = $this->em()->create('Jace\UpgradeFeatures:UpgradeFeature');
        }

        $this->featureSaveProcess($feature)->run();

        return $this->redirect($this->buildLink('upgrade-features') . $this->buildLinkHash($feature->feature_id));
    }

    public function actionDelete(ParameterBag $params)
    {
        $feature = $this->assertFeatureExists($params['feature_id']);

        /** @var \XF\ControllerPlugin\Delete $plugin */
        $plugin = $this->plugin('XF:Delete');
        return $plugin->actionDelete(
            $feature,
            $this->buildLink('upgrade-features/delete', $feature),
            $this->buildLink('upgrade-features/edit', $feature),
            $this->buildLink('upgrade-features'),
            $feature->title
        );
    }

    public function actionToggle()
    {
        /** @var \XF\ControllerPlugin\Toggle $plugin */
        $plugin = $this->plugin('XF:Toggle');
        return $plugin->actionToggle('Jace\UpgradeFeatures:UpgradeFeature');
    }

    protected function assertFeatureExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('Jace\UpgradeFeatures:UpgradeFeature', $id, $with, $phraseKey);
    }

    protected function getFeatureRepo()
    {
        return $this->repository('Jace\UpgradeFeatures:UpgradeFeature');
    }

    protected function getCategoryRepo()
    {
        return $this->repository('Jace\UpgradeFeatures:UpgradeFeatureCategory');
    }
}