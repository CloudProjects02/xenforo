<?php

namespace DBTech\Shop\FindNew;

use XF\Entity\FindNew;
use XF\FindNew\AbstractHandler;

/**
 * Class Item
 *
 * @package DBTech\Shop\FindNew
 */
class Item extends AbstractHandler
{
	/**
	 * @return string
	 */
	public function getRoute(): string
	{
		return 'whats-new/shop-items';
	}
	
	/**
	 * @param \XF\Mvc\Controller $controller
	 * @param FindNew $findNew
	 * @param array $results
	 * @param $page
	 * @param $perPage
	 *
	 * @return \XF\Mvc\Reply\View
	 */
	public function getPageReply(\XF\Mvc\Controller $controller, FindNew $findNew, array $results, $page, $perPage): \XF\Mvc\Reply\AbstractReply
	{
		$canInlineMod = false;

		/** @var \DBTech\Shop\Entity\Item $item */
		foreach ($results AS $item)
		{
			if ($item->canUseInlineModeration())
			{
				$canInlineMod = true;
				break;
			}
		}

		$viewParams = [
			'findNew' => $findNew,

			'page' => $page,
			'perPage' => $perPage,

			'items' => $results,
			'canInlineMod' => $canInlineMod
		];
		return $controller->view('DBTech\Shop:WhatsNew\Items', 'dbtech_shop_whats_new_items', $viewParams);
	}
	
	/**
	 * @param \XF\Http\Request $request
	 *
	 * @return array
	 */
	public function getFiltersFromInput(\XF\Http\Request $request): array
	{
		$filters = [];

		$visitor = \XF::visitor();

		$watched = $request->filter('watched', 'bool');
		if ($watched && $visitor->user_id)
		{
			$filters['watched'] = true;
		}

		return $filters;
	}
	
	/**
	 * @return array
	 */
	public function getDefaultFilters(): array
	{
		return [];
	}
	
	/**
	 * @param array $filters
	 * @param $maxResults
	 *
	 * @return array
	 * @throws \InvalidArgumentException
	 */
	public function getResultIds(array $filters, $maxResults): array
	{
		$visitor = \XF::visitor();

		/** @var \DBTech\Shop\Finder\Item $finder */
		$finder = \XF::finder('DBTech\Shop:Item')
			->with('Permissions|' . $visitor->permission_combination_id)
			->with('Category', true)
			->with('Category.Permissions|' . $visitor->permission_combination_id)
			->where('item_state', '<>', 'deleted')
			->where('last_update', '>', \XF::$time - (86400 * \XF::options()->readMarkingDataLifetime))
			->order('last_update', 'DESC');

		$this->applyFilters($finder, $filters);

		$items = $finder->fetch($maxResults);
		$items = $this->filterResults($items);

		// TODO: consider overfetching or some other permission limits within the query

		return $items->keys();
	}
	
	/**
	 * @param array $ids
	 *
	 * @return \XF\Mvc\Entity\AbstractCollection
	 */
	public function getPageResultsEntities(array $ids): \XF\Mvc\Entity\AbstractCollection
	{
		$visitor = \XF::visitor();

		$ids = array_map('intval', $ids);

		/** @var \DBTech\Shop\Finder\Item $finder */
		$finder = \XF::finder('DBTech\Shop:Item')
			->where('item_id', $ids)
			->with('fullCategory')
			->with('Permissions|' . $visitor->permission_combination_id)
			->with('Category.Permissions|' . $visitor->permission_combination_id);

		return $finder->fetch();
	}
	
	/**
	 * @param \XF\Mvc\Entity\AbstractCollection $results
	 *
	 * @return \XF\Mvc\Entity\AbstractCollection
	 */
	protected function filterResults(\XF\Mvc\Entity\AbstractCollection $results): \XF\Mvc\Entity\AbstractCollection
	{
		$visitor = \XF::visitor();

		return $results->filter(function (\DBTech\Shop\Entity\Item $item) use ($visitor): bool
		{
			return ($item->canView() && !$visitor->isIgnoring($item->user_id));
		});
	}
	
	/**
	 * @param \DBTech\Shop\Finder\Item $finder
	 * @param array $filters
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function applyFilters(\DBTech\Shop\Finder\Item $finder, array $filters)
	{
		$visitor = \XF::visitor();
		if (!empty($filters['watched']))
		{
			$finder->watchedOnly($visitor->user_id);
		}
	}
	
	/**
	 * @return int
	 */
	public function getResultsPerPage(): int
	{
		return 20;
	}
	
	/**
	 * @return bool
	 */
	public function isAvailable(): bool
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		return $visitor->canViewDbtechShopItems();
	}
}