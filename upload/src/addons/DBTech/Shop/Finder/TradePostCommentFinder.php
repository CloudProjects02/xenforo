<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Shop\Finder;

use DBTech\Shop\Entity\TradePost;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Shop\Entity\TradePostComment> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Shop\Entity\TradePostComment> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Shop\Entity\TradePostComment|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Shop\Entity\TradePostComment>
 */
class TradePostCommentFinder extends Finder
{
	/**
	 * @param TradePost $tradePost
	 * @param array $limits
	 *
	 * @return $this
	 */
	public function forTradePost(TradePost $tradePost, array $limits = []): TradePostCommentFinder
	{
		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => true,
		], $limits);

		$this->where('trade_post_id', $tradePost->trade_post_id);

		if ($limits['visibility'])
		{
			$this->applyVisibilityChecksForTradePost($tradePost, $limits['allowOwnPending']);
		}

		return $this;
	}

	/**
	 * @param TradePost $tradePost
	 * @param bool $allowOwnPending
	 *
	 * @return $this
	 */
	public function applyVisibilityChecksForTradePost(TradePost $tradePost, bool $allowOwnPending = true): TradePostCommentFinder
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
	public function newerThan(int $date): TradePostCommentFinder
	{
		$this->where('comment_date', '>', $date);

		return $this;
	}
}