<?php

namespace DBTech\Shop\FindNew;

use XF\FindNew\AbstractHandler;
use XF\Entity\FindNew;

/**
 * Class TradePost
 *
 * @package DBTech\Shop\FindNew
 */
class TradePost extends AbstractHandler
{
	/**
	 * @return string
	 */
	public function getRoute(): string
	{
		return 'whats-new/dbtech-shop-trade-posts';
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
		/** @var \DBTech\Shop\Repository\TradePost $tradePostRepo */
		$tradePostRepo = \XF::repository('DBTech\Shop:TradePost');
		$tradePosts = $tradePostRepo->addCommentsToTradePosts($results);

		$canInlineMod = false;
		/** @var \DBTech\Shop\Entity\TradePost $tradePost */
		foreach ($tradePosts AS $tradePost)
		{
			if ($tradePost->canUseInlineModeration())
			{
				$canInlineMod = true;
				break;
			}
		}

		$viewParams = [
			'findNew' => $findNew,

			'page' => $page,
			'perPage' => $perPage,

			'tradePosts' => $tradePosts,
			'canInlineMod' => $canInlineMod
		];
		return $controller->view('DBTech\Shop:WhatsNew\TradePosts', 'whats_new_dbtech_shop_trade_posts', $viewParams);
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
		$followed = $request->filter('followed', 'bool');

		if ($followed && $visitor->user_id)
		{
			$filters['followed'] = true;
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
	 */
	public function getResultIds(array $filters, $maxResults): array
	{
		/** @var \DBTech\Shop\Finder\TradePost $tradePostFinder */
		$tradePostFinder = \XF::finder('DBTech\Shop:TradePost')
			->where('message_state', '<>', 'moderated')
			->where('message_state', '<>', 'deleted')
			->order('post_date', 'DESC');

		$this->applyFilters($tradePostFinder, $filters);

		$tradePosts = $tradePostFinder->fetch($maxResults);
		$tradePosts = $this->filterResults($tradePosts);

		// TODO: consider overfetching or some other permission limits within the query

		return $tradePosts->keys();
	}
	
	/**
	 * @param array $ids
	 *
	 * @return \XF\Mvc\Entity\AbstractCollection
	 */
	public function getPageResultsEntities(array $ids): \XF\Mvc\Entity\AbstractCollection
	{
		$ids = array_map('intval', $ids);

		/** @var \DBTech\Shop\Finder\TradePost $tradePostFinder */
		$tradePostFinder = \XF::finder('DBTech\Shop:TradePost')
			->where('trade_post_id', $ids)
			->with('fullTrade');

		return $tradePostFinder->fetch();
	}
	
	/**
	 * @param \XF\Mvc\Entity\AbstractCollection $results
	 *
	 * @return \XF\Mvc\Entity\AbstractCollection
	 */
	protected function filterResults(\XF\Mvc\Entity\AbstractCollection $results): \XF\Mvc\Entity\AbstractCollection
	{
		$visitor = \XF::visitor();

		return $results->filter(function (\DBTech\Shop\Entity\TradePost $tradePosts) use ($visitor): bool
		{
			return ($tradePosts->canView() && !$visitor->isIgnoring($tradePosts->user_id));
		});
	}
	
	/**
	 * @param \DBTech\Shop\Finder\TradePost $tradePostFinder
	 * @param array $filters
	 */
	protected function applyFilters(\DBTech\Shop\Finder\TradePost $tradePostFinder, array $filters)
	{
		$visitor = \XF::visitor();

		if (!empty($filters['followed']))
		{
			$following = $visitor->Profile->following;
			$following[] = $visitor->user_id;

			$tradePostFinder->where('user_id', $following);
		}
	}
	
	/**
	 * @return mixed
	 */
	public function getResultsPerPage()
	{
		return \XF::options()->messagesPerPage;
	}
	
	/**
	 * @return bool
	 */
	public function isAvailable(): bool
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		
		return $visitor->canViewDbtechShopTradePosts();
	}
}