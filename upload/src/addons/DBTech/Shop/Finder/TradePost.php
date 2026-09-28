<?php

namespace DBTech\Shop\Finder;

use XF\Mvc\Entity\Finder;

/**
 * Class TradePost
 *
 * @package DBTech\Shop\Finder
 */
class TradePost extends Finder
{
	/**
	 * @param \DBTech\Shop\Entity\Trade $trade
	 * @param array $limits
	 *
	 * @return $this
	 */
	public function onTrade(\DBTech\Shop\Entity\Trade $trade, array $limits = []): TradePost
	{
		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => true
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
	 * @param \DBTech\Shop\Entity\Trade $trade
	 * @param bool $allowOwnPending
	 *
	 * @return $this
	 */
	public function applyVisibilityChecksForTrade(\DBTech\Shop\Entity\Trade $trade, bool $allowOwnPending = true): TradePost
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
		elseif ($visitor->user_id && $allowOwnPending)
		{
			$conditions[] = [
				'message_state' => 'moderated',
				'user_id' => $visitor->user_id
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
	public function newerThan(int $date): TradePost
	{
		$this->where('post_date', '>', $date);

		return $this;
	}
}