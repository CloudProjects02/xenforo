<?php

namespace DBTech\Shop\Finder;

use XF\Mvc\Entity\Finder;

class TradePostComment extends Finder
{
	/**
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 * @param array $limits
	 *
	 * @return $this
	 */
	public function forTradePost(\DBTech\Shop\Entity\TradePost $tradePost, array $limits = []): TradePostComment
	{
		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => true
		], $limits);

		$this->where('trade_post_id', $tradePost->trade_post_id);

		if ($limits['visibility'])
		{
			$this->applyVisibilityChecksForTradePost($tradePost, $limits['allowOwnPending']);
		}

		return $this;
	}
	
	/**
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 * @param bool $allowOwnPending
	 *
	 * @return $this
	 */
	public function applyVisibilityChecksForTradePost(\DBTech\Shop\Entity\TradePost $tradePost, bool $allowOwnPending = true): TradePostComment
	{
		$conditions = [];
		$viewableStates = ['visible'];

		if ($tradePost->canViewDeletedComments())
		{
			$viewableStates[] = 'deleted';
			$this->with('DeletionLog');
		}

		$visitor = \XF::visitor();
		if ($tradePost->canViewModeratedComments())
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
	public function newerThan(int $date): TradePostComment
	{
		$this->where('comment_date', '>', $date);

		return $this;
	}
}