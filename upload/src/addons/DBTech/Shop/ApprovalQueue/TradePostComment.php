<?php

namespace DBTech\Shop\ApprovalQueue;

use XF\ApprovalQueue\AbstractHandler;
use XF\Mvc\Entity\Entity;

/**
 * Class TradePostComment
 *
 * @package DBTech\Shop\ApprovalQueue
 */
class TradePostComment extends AbstractHandler
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
	 * @param \DBTech\Shop\Entity\TradePostComment $comment
	 *
	 * @throws \XF\PrintableException
	 */
	public function actionApprove(\DBTech\Shop\Entity\TradePostComment $comment)
	{
		/** @var \DBTech\Shop\Service\TradePostComment\Approver $approver */
		$approver = \XF::service('DBTech\Shop:TradePostComment\Approver', $comment);
		$approver->approve();
	}
	
	/**
	 * @param \DBTech\Shop\Entity\TradePostComment $comment
	 */
	public function actionDelete(\DBTech\Shop\Entity\TradePostComment $comment)
	{
		$this->quickUpdate($comment, 'message_state', 'deleted');
	}
	
	/**
	 * @param \DBTech\Shop\Entity\TradePostComment $comment
	 */
	public function actionSpamClean(\DBTech\Shop\Entity\TradePostComment $comment)
	{
		if (!$comment->User)
		{
			return;
		}

		$this->_spamCleanInternal($comment->User);
	}
}