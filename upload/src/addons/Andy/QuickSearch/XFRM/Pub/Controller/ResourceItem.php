<?php

namespace Andy\QuickSearch\XFRM\Pub\Controller;

use XF\Mvc\ParameterBag;

class ResourceItem extends XFCP_ResourceItem
{
    public function actionQuickSearch()
    {
		//############################################
		// show quick search in XFRM - all categories
		//############################################

		// get options
		$options = \XF::options();

		// get options from Admin CP -> Options -> Quick search -> Maximum results
		$maximumResults = $options->quickSearchMaximumResults;
		
		// get quickSearchTitle
		$quickSearchTitle = $this->filter('title', 'str');
		
		// check condition
		if (empty($quickSearchTitle))
		{
			return;
		}
		
		// get results with category information
		$finder = \XF::finder('XFRM:ResourceItem');
		$results = $finder
			->with(['Category'])
			->where('title', 'LIKE', $finder->escapeLike($quickSearchTitle, '%?%'))
			->where('resource_state', '=', 'visible')
			->limit($maximumResults)
			->order('title', 'ASC')
			->fetch()
			->filterViewable();
		
		// prepare viewParams
		$viewParams = [
			'results' => $results
		]; 
		
		// send to template	
		return $this->view('Andy\QuickSearch:XFRM', 'andy_quicksearch_xfrm', $viewParams);
	}
	
	public function actionQuickSearchCategories()
	{
		//############################################
		// get categories for modal grid
		//############################################
		
		$this->setResponseType('json');
		
		$categoryRepo = $this->repository('XFRM:Category');
		
		// Get viewable categories first
		$viewableCategories = $categoryRepo->getViewableCategories();
		
		// Filter only viewable categories
		$viewableCategoriesArray = [];
		foreach ($viewableCategories as $category)
		{
			// $category is now the actual entity object
			$viewableCategoriesArray[] = [
				'resource_category_id' => $category->resource_category_id,
				'title' => $category->title,
				'description' => $category->description,
				'resource_count' => $category->resource_count
			];
		}
		
		$viewParams = [
			'categories' => $viewableCategoriesArray
		];
		
		$view = $this->view('Andy\QuickSearch:XFRM\Categories', '');
		$view->setJsonParams($viewParams);
		return $view;
	}
}