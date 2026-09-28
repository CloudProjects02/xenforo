<?php

namespace Andy\QuickSearch\XFRM\Pub\Controller;

use XF\Mvc\ParameterBag;

class Category extends XFCP_Category
{
    public function actionQuickSearch()
    {
		//###############################################
		// show quick search in XFRM - selected category
		//###############################################
	
		// get options
		$options = \XF::options();

		// get options from Admin CP -> Options -> Quick search -> Maximum results
		$maximumResults = $options->quickSearchMaximumResults;
	
		// get quickSearchTitle
		$quickSearchTitle = $this->filter('title', 'str');
		
		// get resourceCategoryId
		$resourceCategoryId = $this->filter('category_id', 'uint');
		
		// If no category ID provided, try to get from route
		if (!$resourceCategoryId) {
			$category = $this->assertViewableCategory($this->filter('resource_category_id', 'uint'));
			$resourceCategoryId = $category->resource_category_id;
		}
		
		// check condition - allow search even without title if category is specified
		if (empty($quickSearchTitle) && empty($resourceCategoryId))
		{
			return;
		}

		// get conditions
		$conditions[] = ['resource_category_id', '=', $resourceCategoryId];

		// get results
		$finder = \XF::finder('XFRM:Category');
		$results = $finder
			->where('parent_category_id', $resourceCategoryId)
			->fetch();
		
		// foreach condition
		foreach ($results AS $k => $v)
		{
			// get conditions - sub category
			$conditions[] = ['resource_category_id', '=', $v['resource_category_id']];

			// get results2
			$finder = \XF::finder('XFRM:Category');
			$results2 = $finder
				->where('parent_category_id', $v['resource_category_id'])
				->fetch();
			
			// foreach condition
			foreach ($results2 AS $k2 => $v2)
			{
				// get conditions - sub sub category
				$conditions[] = ['resource_category_id', '=', $v2['resource_category_id']];
			}
		}		

		// get results with category information
		$finder = \XF::finder('XFRM:ResourceItem');
		$query = $finder
			->with(['Category'])
			->where('resource_state', '=', 'visible')
			->whereOr($conditions)
			->limit($maximumResults)
			->order('title', 'ASC');
			
		// Add title filter only if provided
		if (!empty($quickSearchTitle)) {
			$query->where('title', 'LIKE', $finder->escapeLike($quickSearchTitle, '%?%'));
		}
		
		$results = $query->fetch()->filterViewable();
		
		// prepare viewParams
		$viewParams = [
			'results' => $results,
			'categoryId' => $resourceCategoryId
		]; 
		
		// send to template	
		return $this->view('Andy\QuickSearch:XFRM', 'andy_quicksearch_xfrm', $viewParams);
	}
}