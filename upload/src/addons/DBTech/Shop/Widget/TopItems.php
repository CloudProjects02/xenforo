<?php

namespace DBTech\Shop\Widget;

use DBTech\Shop\Entity\Category;
use DBTech\Shop\Entity\Item;
use DBTech\Shop\Finder\ItemFinder;
use DBTech\Shop\Repository\CategoryRepository;
use DBTech\Shop\Repository\ItemRepository;
use DBTech\Shop\XF\Entity\User;
use XF\Http\Request;
use XF\Widget\AbstractWidget;
use XF\Widget\WidgetRenderer;

class TopItems extends AbstractWidget
{
	/**
	 * @var array
	 */
	protected $defaultOptions = [
		'limit' => 5,
		'style' => 'simple',
		'category_ids' => [],
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
			$categoryRepo = \XF::app()->repository(CategoryRepository::class);
			$params['categoryTree'] = $categoryRepo->createCategoryTree($categoryRepo->findCategoryList()->fetch());
		}
		return $params;
	}

	/**
	 * @return string|WidgetRenderer
	 * @throws \InvalidArgumentException
	 */
	public function render(): string|WidgetRenderer
	{
		/** @var User $visitor */
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
			&& $this->contextParams['category'] instanceof Category
		);
		$useContext = false;

		if (!$hasCategoryIds && $hasCategoryContext)
		{
			/** @var Category $category */
			$category = $this->contextParams['category'];
			$viewableDescendents = $category->getViewableDescendants();
			$sourceCategoryIds = array_keys($viewableDescendents);
			$sourceCategoryIds[] = $category->category_id;

			$useContext = true;
		}
		else if ($hasCategoryIds)
		{
			$sourceCategoryIds = $categoryIds;
		}
		else
		{
			$sourceCategoryIds = null;
		}

		$itemRepo = \XF::app()->repository(ItemRepository::class);

		/** @var ItemFinder $finder */
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

		/** @var Item $item */
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
			'hasMore' => $total > $items->count(),
		];
		return $this->renderer('dbtech_shop_widget_top_items', $viewParams);
	}

	/**
	 * @param Request $request
	 * @param array $options
	 * @param null $error
	 *
	 * @return bool
	 */
	public function verifyOptions(Request $request, array &$options, &$error = null): bool
	{
		$options = $request->filter([
			'limit' => 'uint',
			'style' => 'str',
			'category_ids' => 'array-uint',
		]);
		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}

		return true;
	}
}