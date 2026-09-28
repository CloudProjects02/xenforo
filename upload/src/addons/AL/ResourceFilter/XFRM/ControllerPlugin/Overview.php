<?php
/** 
* @package [AddonsLab] Resource Filter
* @author AddonsLab
* @license https://addonslab.com/
* @link https://addonslab.com/
* @version 3.6.1
This software is furnished under a license and may be used and copied
only  in  accordance  with  the  terms  of such  license and with the
inclusion of the above copyright notice.  This software  or any other
copies thereof may not be provided or otherwise made available to any
other person.  No title to and  ownership of the  software is  hereby
transferred.                                                         
                                                                     
You may not reverse  engineer, decompile, defeat  license  encryption
mechanisms, or  disassemble this software product or software product
license.  AddonsLab may terminate this license if you don't comply with
any of these terms and conditions.  In such event,  licensee  agrees 
to return licensor  or destroy  all copies of software  upon termination 
of the license.
*/


namespace AL\ResourceFilter\XFRM\ControllerPlugin;

use AL\FilterFramework\FilterApp;
use AL\FilterFramework\RootFinder;
use AL\FilterFramework\Service\TotalCountCalculator;
use XF\Mvc\Reply\Redirect;
use AL\ResourceFilter\App;
use AL\ShowcaseFilter\XenAddons\Showcase\Entity\Category;
use XF\Mvc\Reply\View;

class  Overview extends XFCP_Overview
{
    protected $category;
    protected $all_categories;

    /**
     * @var \XF\Tree
     */
    protected $categoryTree;

    // try to catch the categories and store it to use later
    public function getCategoryListData(\XFRM\Entity\Category $category = null)
    {
        $data = parent::getCategoryListData($category);
        if ($category)
        {
            $this->category = $category;
        }
        else if (isset($data['categories']))
        {
            $this->all_categories = $data['categories'];
            if (is_object($this->all_categories) && $this->all_categories instanceof \XF\Mvc\Entity\AbstractCollection)
            {
                $this->all_categories = $this->all_categories->toArray();
            }
        }

        if (isset($data['categoryTree']))
        {
            $this->categoryTree = $data['categoryTree'];
        }

        return $data;
    }


    // try to catch the current category and use it later
    public function getCoreListData(array $sourceCategoryIds, \XFRM\Entity\Category $category = null)
    {
        if ($category)
        {
            $this->category = $category;
        }

        return parent::getCoreListData($sourceCategoryIds, $category);
    }


    public function actionFilters(\XFRM\Entity\Category $category = null)
    {
        // store to use later to fetch custom fields associated with the categories
        if ($category)
        {
            $this->category = $category;
        }


        $reply = parent::actionFilters($category);

        if ($reply instanceof View)
        {
            $viewParams = $reply->getParams();

            if ($category === null)
            {
                $viewParams['categories'] = $this->getCategoryRepo()->getViewableCategories($category);
            }

            $filterInput = $this->getResourceFilterInput();

            if (empty($filterInput['order']))
            {
                unset($viewParams['filters']['order']);
                unset($viewParams['filters']['direction']);
            }
            $viewParams['category'] ? $viewParams['baseLinkPathClearButton'] = 'resources/categories' : $viewParams['baseLinkPathClearButton'] = 'resources';

            $reply->setParams(App::getContextProvider()->setupViewParams($viewParams, $this->controller), false);
        }

        if (App::getContentTypeProvider()->getTotalCountIndicatorSetting())
        {
            // Total items are missing from context params, meaning actionFilters is not called from the main action
            /** @var TotalCountCalculator $service */
            $service = $this->service('AL\FilterFramework:TotalCountCalculator');

            if ($reply instanceof Redirect)
            {
                $reply->setJsonParam('filterInfo', [
                    'total' => $service->getTotalCount($reply->getUrl())
                ]);
            }
            elseif ($reply instanceof View){
                $total = $service->getTotalCount($this->request->getReferrer());
                $reply->setParam('total', $total);
            }
        }

        return $reply;
    }

    public function getResourceFilterInput()
    {
        $filters = parent::getResourceFilterInput();

        $filterName = App::getContentTypeProvider()->getFilterName();

        $filters[$filterName] = $this->filter($filterName, 'array');

        if (empty($filters[$filterName]))
        {
            unset($filters[$filterName]);
        }
        else
        {
            FilterApp::getInputTransformer(App::getContentTypeProvider())->normalizeInput($filters[$filterName]);
        }

        return $filters;
    }

    public function applyResourceFilters(\XFRM\Finder\ResourceItem $resourceFinder, array $filters)
    {
        RootFinder::setRootFinder($resourceFinder);
        if ($this->category)
        {
            $categoryIds = [];
            if ($this->categoryTree)
            {
                $categoryIds = array_keys($this->categoryTree->getDescendants($this->category->resource_category_id));
            }
            $categoryIds[] = $this->category->resource_category_id;
            $fieldCache = App::getContentTypeProvider()->getFieldCacheForCategory($this->category);
        }
        else if ($this->all_categories)
        {
            $categoryIds = array_map(static function (\AL\ResourceFilter\XFRM\Entity\Category $category)
            {
                return $category->resource_category_id;
            }, $this->all_categories);
            $categoryIds = array_values($categoryIds);
            $fieldCache = $this->getCommonFieldsInCategories($this->all_categories);
        }
        else
        {
            $categoryIds = [];
            $fieldCache = [];
        }

        $sortOptions = App::getContextProvider()->getSortOptions($fieldCache);

        if (!empty($filters['order']) && isset($sortOptions[$filters['order']]))
        {

            $fieldId = str_replace(App::getContentTypeProvider()->getFilterName() . '_', '', $filters['order']);
            $indexRelationName = "CustomFieldIndex|$fieldId";
            $resourceFinder->with($indexRelationName, true);
        }

        parent::applyResourceFilters($resourceFinder, $filters);

        App::getContextProvider()->applyCategoryFilters($resourceFinder, $filters, $categoryIds, $fieldCache);

        App::getContextProvider()->executeFacetedSearch(
            $resourceFinder,
            $fieldCache
        );
    }

    public function getAvailableResourceSorts()
    {
        $sorts = parent::getAvailableResourceSorts();

        if ($this->category)
        {
            $fieldCache = App::getContentTypeProvider()->getFieldCacheForCategory($this->category);
        }
        else if ($this->all_categories)
        {
            $fieldCache = $this->getCommonFieldsInCategories($this->all_categories);
        }
        else
        {
            $this->all_categories = $this->getCategoryRepo()->getViewableCategories();
            $fieldCache = $this->getCommonFieldsInCategories($this->all_categories);
        }

        $sorts += App::getContextProvider()->getSortOptions($fieldCache);

        return $sorts;
    }

    protected function getCommonFieldsInCategories($categories)
    {

        return App::getContextProvider()->getCommonFieldsInCategories($categories);
    }
}
