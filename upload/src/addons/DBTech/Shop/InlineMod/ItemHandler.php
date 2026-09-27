<?php

namespace DBTech\Shop\InlineMod;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\InlineMod\Item\ApplyPrefix;
use DBTech\Shop\InlineMod\Item\Delete;
use DBTech\Shop\InlineMod\Item\Move;
use DBTech\Shop\InlineMod\Item\Reassign;
use DBTech\Shop\Service\Item\ApproveService;
use DBTech\Shop\Service\Item\DeleteService;
use XF\InlineMod\AbstractHandler;
use XF\Mvc\Entity\Entity;

class ItemHandler extends AbstractHandler
{
	/**
	 * @return array|\XF\InlineMod\AbstractAction[]
	 * @throws \Exception
	 * @throws \LogicException
	 */
	public function getPossibleActions(): array
	{
		$actions = [];

		$actions['delete'] = $this->getActionHandler(Delete::class);

		$actions['undelete'] = $this->getSimpleActionHandler(
			\XF::phrase('dbtech_shop_undelete_items'),
			'canUndelete',
			function (Entity $entity)
			{
				$deleter = \XF::app()->service(DeleteService::class, $entity);
				$deleter->unDelete();
			}
		);

		$actions['approve'] = $this->getSimpleActionHandler(
			\XF::phrase('dbtech_shop_approve_items'),
			'canApproveUnapprove',
			function (Entity $entity)
			{
				/** @var Item $entity */
				if ($entity->item_state == 'moderated')
				{
					$approver = \XF::app()->service(ApproveService::class, $entity);
					$approver->setNotifyRunTime(1); // may be a lot happening
					$approver->approve();
				}
			}
		);

		$actions['unapprove'] = $this->getSimpleActionHandler(
			\XF::phrase('dbtech_shop_unapprove_items'),
			'canApproveUnapprove',
			function (Entity $entity)
			{
				/** @var Item $entity */
				if ($entity->item_state == 'visible')
				{
					$entity->item_state = 'moderated';
					$entity->save();
				}
			}
		);

		$actions['reassign'] = $this->getActionHandler(Reassign::class);
		$actions['move'] = $this->getActionHandler(Move::class);
		$actions['apply_prefix'] = $this->getActionHandler(ApplyPrefix::class);

		return $actions;
	}

	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		$visitor = \XF::visitor();

		return ['Permissions|' . $visitor->permission_combination_id, 'Category', 'Category.Permissions|' . $visitor->permission_combination_id];
	}
}