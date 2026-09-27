<?php

namespace DBTech\Shop\ApprovalQueue;

use DBTech\Shop\Entity\TradePostComment;
use DBTech\Shop\Service\TradePostComment\ApproverService;
use XF\ApprovalQueue\AbstractHandler;
use XF\Mvc\Entity\Entity;
use XF\PrintableException;

class TradePostCommentHandler extends AbstractHandler
{
	/**
	 * @param Entity $content
	 * @param null $error
	 *
	 * @return bool
	 */
	protected function canActionContent(Entity $content, &$error = null): bool
	{
		/** @var $content \DBTech\Shop\Entity\TradePostComment */
		return $content->canApproveUnapprove($error);
	}

	/**
	 * @param TradePostComment $comment
	 *
	 * @throws PrintableException
	 */
	public function actionApprove(TradePostComment $comment): void
	{
		$approver = \XF::app()->service(ApproverService::class, $comment);
		$approver->approve();
	}

	/**
	 * @param TradePostComment $comment
	 */
	public function actionDelete(TradePostComment $comment): void
	{
		$this->quickUpdate($comment, 'message_state', 'deleted');
	}

	/**
	 * @param TradePostComment $comment
	 */
	public function actionSpamClean(TradePostComment $comment): void
	{
		if (!$comment->User)
		{
			return;
		}

		$this->_spamCleanInternal($comment->User);
	}
}