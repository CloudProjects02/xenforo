<?php

namespace DBTech\Shop\ApprovalQueue;

use XF\ApprovalQueue\AbstractHandler;
use XF\Mvc\Entity\Entity;

/**
 * Class TradePost
 *
 * @package DBTech\Shop\ApprovalQueue
 */
class TradePost extends AbstractHandler
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
	 * @param \DBTech\Shop\Entity\TradePost $profilePost
	 *
	 * @throws \XF\PrintableException
	 */
	public function actionApprove(\DBTech\Shop\Entity\TradePost $profilePost)
	{
		/** @var \DBTech\Shop\Service\TradePost\Approver $approver */
		$approver = \XF::service('DBTech\Shop:TradePost\Approver', $profilePost);
		$approver->approve();
	}
	
	/**
	 * @param \DBTech\Shop\Entity\TradePost $profilePost
	 */
	public function actionDelete(\DBTech\Shop\Entity\TradePost $profilePost)
	{
		$this->quickUpdate($profilePost, 'message_state', 'deleted');
	}
	
	/**
	 * @param \DBTech\Shop\Entity\TradePost $profilePost
	 */
	public function actionSpamClean(\DBTech\Shop\Entity\TradePost $profilePost)
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