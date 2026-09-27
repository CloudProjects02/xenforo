<?php

namespace DBTech\Shop\FindNew;

use DBTech\Shop\Entity\TradePost;
use DBTech\Shop\Finder\TradePostFinder;
use DBTech\Shop\Pub\View\WhatsNew\TradePostsView;
use DBTech\Shop\Repository\TradePostRepository;
use DBTech\Shop\XF\Entity\User;
use XF\Entity\FindNew;
use XF\FindNew\AbstractHandler;
use XF\Http\Request;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Reply\AbstractReply;

class TradePostHandler extends AbstractHandler
{
	/**
	 * @return string
	 */
	public function getRoute(): string
	{
		return 'whats-new/dbtech-shop-trade-posts';
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
		$tradePostRepo = \XF::app()->repository(TradePostRepository::class);
		$tradePosts = $tradePostRepo->addCommentsToTradePosts(
			\XF::app()->em()->getBasicCollection($results)
		);

		$canInlineMod = false;
		/** @var TradePost $tradePost */
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
			'canInlineMod' => $canInlineMod,
		];
		return $controller->view(
			TradePostsView::class,
			'whats_new_dbtech_shop_trade_posts',
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
		/** @var TradePostFinder $tradePostFinder */
		$tradePostFinder = \XF::app()->finder(TradePostFinder::class)
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
	 * @return AbstractCollection
	 */
	public function getPageResultsEntities(array $ids): AbstractCollection
	{
		$ids = array_map('intval', $ids);

		/** @var TradePostFinder $tradePostFinder */
		$tradePostFinder = \XF::app()->finder(TradePostFinder::class)
			->where('trade_post_id', $ids)
			->with('fullTrade');

		return $tradePostFinder->fetch();
	}

	/**
	 * @param AbstractCollection $results
	 *
	 * @return AbstractCollection
	 */
	protected function filterResults(AbstractCollection $results): AbstractCollection
	{
		$visitor = \XF::visitor();

		return $results->filter(function (TradePost $tradePosts) use ($visitor): bool
		{
			return ($tradePosts->canView() && !$visitor->isIgnoring($tradePosts->user_id));
		});
	}

	/**
	 * @param TradePostFinder $tradePostFinder
	 * @param array $filters
	 */
	protected function applyFilters(TradePostFinder $tradePostFinder, array $filters): void
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
	public function getResultsPerPage(): mixed
	{
		return \XF::options()->messagesPerPage;
	}

	/**
	 * @return bool
	 */
	public function isAvailable(): bool
	{
		/** @var User $visitor */
		$visitor = \XF::visitor();

		return $visitor->canViewDbtechShopTradePosts();
	}
}