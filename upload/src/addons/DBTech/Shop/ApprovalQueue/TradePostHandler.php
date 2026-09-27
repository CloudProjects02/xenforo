<?php

namespace DBTech\Shop\ApprovalQueue;

use DBTech\Shop\Entity\TradePost;
use DBTech\Shop\Service\TradePost\ApproverService;
use XF\ApprovalQueue\AbstractHandler;
use XF\Mvc\Entity\Entity;
use XF\PrintableException;

class TradePostHandler extends AbstractHandler
{
	/**
	 * @param Entity $content
	 * @param null $error
	 *
	 * @return bool
	 */
	protected function canActionContent(Entity $content, &$error = null): bool
	{
		/** @var $content \DBTech\Shop\Entity\TradePost */
		return $content->canApproveUnapprove($error);
	}

	/**
	 * @param TradePost $profilePost
	 *
	 * @throws PrintableException
	 */
	public function actionApprove(TradePost $profilePost): void
	{
		$approver = \XF::app()->service(ApproverService::class, $profilePost);
		$approver->approve();
	}

	/**
	 * @param TradePost $profilePost
	 */
	public function actionDelete(TradePost $profilePost): void
	{
		$this->quickUpdate($profilePost, 'message_state', 'deleted');
	}

	/**
	 * @param TradePost $profilePost
	 */
	public function actionSpamClean(TradePost $profilePost): void
	{
		if (!$profilePost->User)
		{
			return;
		}

		$this->_spamCleanInternal($profilePost->User);
	}

	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return ['Trade'];
	}
}