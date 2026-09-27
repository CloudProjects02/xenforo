<?php

namespace DBTech\Shop\FindNew;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Finder\ItemFinder;
use DBTech\Shop\Pub\View\WhatsNew\ItemsView;
use DBTech\Shop\XF\Entity\User;
use XF\Entity\FindNew;
use XF\FindNew\AbstractHandler;
use XF\Http\Request;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Reply\AbstractReply;

class ItemHandler extends AbstractHandler
{
	/**
	 * @return string
	 */
	public function getRoute(): string
	{
		return 'whats-new/shop-items';
	}

	/**
	 * @param Controller $controller
	 * @param FindNew $findNew
	 * @param array $results
	 * @param $page
	 * @param $perPage
	 *
	 * @return AbstractReply
	 */
	public function getPageReply(Controller $controller, FindNew $findNew, array $results, $page, $perPage): AbstractReply
	{
		$canInlineMod = false;

		/** @var Item $item */
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
			'canInlineMod' => $canInlineMod,
		];
		return $controller->view(
			ItemsView::class,
			'dbtech_shop_whats_new_items',
			$viewParams
		);
	}

	/**
	 * @param Request $request
	 *
	 * @return array
	 */
	public function getFiltersFromInput(Request $request): array
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

		/** @var ItemFinder $finder */
		$finder = \XF::app()->finder(ItemFinder::class)
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
	 * @return AbstractCollection
	 */
	public function getPageResultsEntities(array $ids): AbstractCollection
	{
		$visitor = \XF::visitor();

		$ids = array_map('intval', $ids);

		/** @var ItemFinder $finder */
		$finder = \XF::app()->finder(ItemFinder::class)
			->where('item_id', $ids)
			->with('fullCategory')
			->with('Permissions|' . $visitor->permission_combination_id)
			->with('Category.Permissions|' . $visitor->permission_combination_id);

		return $finder->fetch();
	}

	/**
	 * @param AbstractCollection $results
	 *
	 * @return AbstractCollection
	 */
	protected function filterResults(AbstractCollection $results): AbstractCollection
	{
		$visitor = \XF::visitor();

		return $results->filter(function (Item $item) use ($visitor): bool
		{
			return ($item->canView() && !$visitor->isIgnoring($item->user_id));
		});
	}

	/**
	 * @param ItemFinder $finder
	 * @param array $filters
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function applyFilters(ItemFinder $finder, array $filters): void
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
		/** @var User $visitor */
		$visitor = \XF::visitor();
		return $visitor->canViewDbtechShopItems();
	}
}