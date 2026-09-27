<?php

namespace DBTech\Shop\InlineMod;

use DBTech\Shop\Entity\TradePost;
use DBTech\Shop\InlineMod\TradePost\Delete;
use DBTech\Shop\Service\TradePost\ApproverService;
use XF\InlineMod\AbstractHandler;
use XF\Mvc\Entity\Entity;

class TradePostHandler extends AbstractHandler
{
	/**
	 * @return array|\XF\InlineMod\AbstractAction[]
	 */
	public function getPossibleActions(): array
	{
		$actions = [];

		$actions['delete'] = $this->getActionHandler(Delete::class);

		$actions['undelete'] = $this->getSimpleActionHandler(
			\XF::phrase('undelete_posts'),
			'canUndelete',
			function (Entity $entity)
			{
				/** @var TradePost $entity */
				if ($entity->message_state == 'deleted')
				{
					$entity->message_state = 'visible';
					$entity->save();
				}
			}
		);

		$actions['approve'] = $this->getSimpleActionHandler(
			\XF::phrase('approve_posts'),
			'canApproveUnapprove',
			function (Entity $entity)
			{
				/** @var TradePost $entity */
				if ($entity->message_state == 'moderated')
				{
					$approver = \XF::app()->service(ApproverService::class, $entity);
					$approver->approve();
				}
			}
		);

		$actions['unapprove'] = $this->getSimpleActionHandler(
			\XF::phrase('unapprove_posts'),
			'canApproveUnapprove',
			function (Entity $entity)
			{
				/** @var TradePost $entity */
				if ($entity->message_state == 'visible')
				{
					$entity->message_state = 'moderated';
					$entity->save();
				}
			}
		);

		return $actions;
	}

	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return ['Trade'];
	}
}