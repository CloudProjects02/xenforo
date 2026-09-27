<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Shop\Finder;

use DBTech\Shop\Entity\Trade;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Shop\Entity\TradePost> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Shop\Entity\TradePost> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Shop\Entity\TradePost|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Shop\Entity\TradePost>
 */
class TradePostFinder extends Finder
{
	/**
	 * @param Trade $trade
	 * @param array $limits
	 *
	 * @return $this
	 */
	public function onTrade(Trade $trade, array $limits = []): TradePostFinder
	{
		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => true,
		], $limits);

		$this->where('trade_id', $trade->trade_id);

		if ($limits['visibility'])
		{
			$this->applyVisibilityChecksForTrade($trade, $limits['allowOwnPending']);
		}

		$this->with('full');

		return $this;
	}

	/**
	 * @param Trade $trade
	 * @param bool $allowOwnPending
	 *
	 * @return $this
	 */
	public function applyVisibilityChecksForTrade(Trade $trade, bool $allowOwnPending = true): TradePostFinder
	{
		$conditions = [];
		$viewableStates = ['visible'];

		if ($trade->canViewDeletedPostsInTrade())
		{
			$viewableStates[] = 'deleted';
			$this->with('DeletionLog');
		}

		$visitor = \XF::visitor();
		if ($trade->canViewModeratedPostsInTrade())
		{
			$viewableStates[] = 'moderated';
		}
		else if ($visitor->user_id && $allowOwnPending)
		{
			$conditions[] = [
				'message_state' => 'moderated',
				'user_id' => $visitor->user_id,
			];
		}

		$conditions[] = ['message_state', $viewableStates];

		$this->whereOr($conditions);

		return $this;
	}

	/**
	 * @param int $date
	 *
	 * @return $this
	 */
	public function newerThan(int $date): TradePostFinder
	{
		$this->where('post_date', '>', $date);

		return $this;
	}
}