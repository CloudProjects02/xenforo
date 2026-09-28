<?php

namespace DBTech\Shop\Widget;

use XF\Widget\AbstractWidget;

/**
 * Class TopItems
 *
 * @package DBTech\Shop\Widget
 */
class TopItems extends AbstractWidget
{
	/**
	 * @var array
	 */
	protected $defaultOptions = [
		'limit' => 5,
		'style' => 'simple',
		'category_ids' => []
	];
	
	/**
	 * @param string $context
	 *
	 * @return array
	 */
	protected function getDefaultTemplateParams($context): array
	{
		$params = parent::getDefaultTemplateParams($context);
		if ($context == 'options')
		{
			/** @var \DBTech\Shop\Repository\Category $categoryRepo */
			$categoryRepo = $this->app->repository('DBTech\Shop:Category');
			$params['categoryTree'] = $categoryRepo->createCategoryTree($categoryRepo->findCategoryList()->fetch());
		}
		return $params;
	}
	
	/**
	 * @return string|\XF\Widget\WidgetRenderer
	 * @throws \InvalidArgumentException
	 */
	public function render()
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		if (!method_exists($visitor, 'canViewDbtechShopItems') || !$visitor->canViewDbtechShopItems())
		{
			return '';
		}

		$options = $this->options;
		$limit = $options['limit'];
		$categoryIds = $options['category_ids'];

		$hasCategoryIds = ($categoryIds && !in_array(0, $categoryIds));
		$hasCategoryContext = (
			isset($this->contextParams['category'])
			&& $this->contextParams['category'] instanceof \DBTech\Shop\Entity\Category
		);
		$useContext = false;

		if (!$hasCategoryIds && $hasCategoryContext)
		{
			/** @var \DBTech\Shop\Entity\Category $category */
			$category = $this->contextParams['category'];
			$viewableDescendents = $category->getViewableDescendants();
			$sourceCategoryIds = array_keys($viewableDescendents);
			$sourceCategoryIds[] = $category->category_id;

			$useContext = true;
		}
		elseif ($hasCategoryIds)
		{
			$sourceCategoryIds = $categoryIds;
		}
		else
		{
			$sourceCategoryIds = null;
		}

		/** @var \DBTech\Shop\Repository\Item $itemRepo */
		$itemRepo = $this->repository('DBTech\Shop:Item');
		
		/** @var \DBTech\Shop\Finder\Item $finder */
		$finder = $itemRepo->findTopItems($sourceCategoryIds);
		$finder->with('Permissions|' . $visitor->permission_combination_id);

		if (!$useContext)
		{
			// with the context, we already fetched the category and permissions
			$finder->with('Category.Permissions|' . $visitor->permission_combination_id);
		}

		if ($options['style'] == 'full')
		{
			$finder->with('fullCategory');
		}

		$items = $finder->fetch(max($limit * 2, 10));

		/** @var \DBTech\Shop\Entity\Item $item */
		foreach ($items AS $itemId => $item)
		{
			if (!$item->canView() || $visitor->isIgnoring($item->user_id))
			{
				unset($items[$itemId]);
			}
		}

		$total = $items->count();
		$items = $items->slice(0, $limit);

		$viewParams = [
			'title' => $this->getTitle(),
			'items' => $items,
			'style' => $options['style'],
			'hasMore' => $total > $items->count()
		];
		return $this->renderer('dbtech_shop_widget_top_items', $viewParams);
	}
	
	/**
	 * @param \XF\Http\Request $request
	 * @param array $options
	 * @param null $error
	 *
	 * @return bool
	 */
	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null): bool
	{
		$options = $request->filter([
			'limit' => 'uint',
			'style' => 'str',
			'category_ids' => 'array-uint'
		]);
		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}

		return true;
	}
}