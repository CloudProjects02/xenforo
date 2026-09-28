<?php

namespace DBTech\Shop\InlineMod;

use XF\InlineMod\AbstractHandler;
use XF\Mvc\Entity\Entity;

/**
 * Class TradePost
 *
 * @package DBTech\Shop\InlineMod
 */
class TradePost extends AbstractHandler
{
	/**
	 * @return array|\XF\InlineMod\AbstractAction[]
	 */
	public function getPossibleActions(): array
	{
		$actions = [];

		$actions['delete'] = $this->getActionHandler('DBTech\Shop:TradePost\Delete');

		$actions['undelete'] = $this->getSimpleActionHandler(
			\XF::phrase('undelete_posts'),
			'canUndelete',
			function (Entity $entity)
			{
				/** @var \DBTech\Shop\Entity\TradePost $entity */
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
				/** @var \DBTech\Shop\Entity\TradePost $entity */
				if ($entity->message_state == 'moderated')
				{
					/** @var \DBTech\Shop\Service\TradePost\Approver $approver */
					$approver = \XF::service('DBTech\Shop:TradePost\Approver', $entity);
					$approver->approve();
				}
			}
		);

		$actions['unapprove'] = $this->getSimpleActionHandler(
			\XF::phrase('unapprove_posts'),
			'canApproveUnapprove',
			function (Entity $entity)
			{
				/** @var \DBTech\Shop\Entity\TradePost $entity */
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