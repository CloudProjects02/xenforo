<?php

namespace Jace\UpgradeFeatures\Admin\Controller;

use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class UpgradeFeatureCategory extends \XF\Admin\Controller\AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('upgradeFeatures');
    }

    public function actionIndex()
    {
        return $this->redirectPermanently($this->buildLink('upgrade-features'));
    }

    public function categoryAddEdit(\Jace\UpgradeFeatures\Entity\UpgradeFeatureCategory $category)
    {
        $viewParams = [
            'category' => $category
        ];
        return $this->view('Jace\UpgradeFeatures:UpgradeFeatureCategory\Edit', 'upgrade_feature_category_edit', $viewParams);
    }

    public function actionEdit(ParameterBag $params)
    {
        $category = $this->assertCategoryExists($params['category_id']);
        return $this->categoryAddEdit($category);
    }

    public function actionAdd()
    {
        $category = $this->em()->create('Jace\UpgradeFeatures:UpgradeFeatureCategory');
        return $this->categoryAddEdit($category);
    }

    protected function categorySaveProcess(\Jace\UpgradeFeatures\Entity\UpgradeFeatureCategory $category)
    {
        $entityInput = $this->filter([
            'display_order' => 'uint',
            'display_mode' => 'str',
            'overwrite' => 'uint'
        ]);

        $form = $this->formAction();
        $form->basicEntitySave($category, $entityInput);

        $phraseInput = $this->filter([
            'title' => 'str',
            'description' => 'str'
        ]);

        $form->validate(function(FormAction $form) use ($phraseInput)
        {
            if ($phraseInput['title'] === '')
            {
                $form->logError(\XF::phrase('please_enter_valid_title'), 'title');
            }
        });

        $form->apply(function() use ($phraseInput, $category)
        {
            $masterTitle = $category->getMasterTitlePhrase();
            $masterTitle->phrase_text = $phraseInput['title'];
            $masterTitle->save();

            $masterDescription = $category->getMasterDescriptionPhrase();
            $masterDescription->phrase_text = $phraseInput['description'];
            $masterDescription->save();
        });

        return $form;
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params['category_id'])
        {
            $category = $this->assertCategoryExists($params['category_id']);
        }
        else
        {
            $category = $this->em()->create('Jace\UpgradeFeatures:UpgradeFeatureCategory');
        }

        $this->categorySaveProcess($category)->run();

        return $this->redirect($this->buildLink('upgrade-features') . $this->buildLinkHash($category->category_id));
    }

    public function actionDelete(ParameterBag $params)
    {
        $category = $this->assertCategoryExists($params['category_id']);

        if ($this->isPost())
        {
            $childAction = $this->filter('child_action', 'str');
            $category->setOption('delete_features', ($childAction === 'delete'));
            $category->delete();

            return $this->redirect($this->buildLink('upgrade-features'));
        }
        else
        {
            $viewParams = [
                'category' => $category,
                'featureCount' => $category->Features->count()
            ];
            return $this->view('Jace\UpgradeFeatures:UpgradeFeatureCategory\Delete', 'upgrade_feature_category_delete', $viewParams);
        }
    }

    protected function assertCategoryExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('Jace\UpgradeFeatures:UpgradeFeatureCategory', $id, $with, $phraseKey);
    }

    protected function getCategoryRepo()
    {
        return $this->repository('Jace\UpgradeFeatures:UpgradeFeatureCategory');
    }
}