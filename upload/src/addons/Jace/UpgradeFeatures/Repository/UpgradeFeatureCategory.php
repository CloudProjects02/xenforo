<?php

namespace Jace\UpgradeFeatures\Repository;

use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class UpgradeFeatureCategory extends Repository
{
    public function getDefaultCategory()
    {
        $category = $this->em->create('Jace\UpgradeFeatures:UpgradeFeatureCategory');
        $category->setTrusted('category_id', 0);
        $category->setTrusted('display_order', 0);
        $category->setReadOnly(true);

        return $category;
    }

    public function findCategoriesForList($getDefault = false, $categoryIds = null)
    {
        $categoriesFinder = $this->finder('Jace\UpgradeFeatures:UpgradeFeatureCategory')
            ->order(['display_order']);

        if (isset($categoryIds))
        {
            $categoriesFinder->where('category_id', $categoryIds);
        }

        $categories = $categoriesFinder->fetch();

        if ($getDefault)
        {
            $defaultCategory = $this->getDefaultCategory();
            $categoryArray = $categories->toArray();
            $categoryArray = [$defaultCategory] + $categoryArray;
            $categories = $this->em->getBasicCollection($categoryArray);
        }

        return $categories;
    }

    public function getCategoryTitlePairs()
    {
        $categories = $this->finder('Jace\UpgradeFeatures:UpgradeFeatureCategory')
            ->order('display_order');

        return $categories->fetch()->pluckNamed('title', 'category_id');
    }

    public function getCategoryList()
    {
        return $this->findCategoriesForList(true);
    }
}