<?php

namespace DBTech\Shop\InlineMod;

use XF\InlineMod\AbstractHandler;
use XF\Mvc\Entity\Entity;

/**
 * Class Item
 *
 * @package DBTech\Shop\InlineMod
 */
class Item extends AbstractHandler
{
	/**
	 * @return array|\XF\InlineMod\AbstractAction[]
	 * @throws \Exception
	 * @throws \LogicException
	 */
	public function getPossibleActions(): array
	{
		$actions = [];

		$actions['delete'] = $this->getActionHandler('DBTech\Shop:Item\Delete');

		$actions['undelete'] = $this->getSimpleActionHandler(
			\XF::phrase('dbtech_shop_undelete_items'),
			'canUndelete',
			function (Entity $entity)
			{
				/** @var \DBTech\Shop\Service\Item\Delete $deleter */
				$deleter = $this->app->service('DBTech\Shop:Item\Delete', $entity);
				$deleter->unDelete();
			}
		);

		$actions['approve'] = $this->getSimpleActionHandler(
			\XF::phrase('dbtech_shop_approve_items'),
			'canApproveUnapprove',
			function (Entity $entity)
			{
				/** @var \DBTech\Shop\Entity\Item $entity */
				if ($entity->item_state == 'moderated')
				{
					/** @var \DBTech\Shop\Service\Item\Approve $approver */
					$approver = \XF::service('DBTech\Shop:Item\Approve', $entity);
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
				/** @var \DBTech\Shop\Entity\Item $entity */
				if ($entity->item_state == 'visible')
				{
					$entity->item_state = 'moderated';
					$entity->save();
				}
			}
		);

		$actions['reassign'] = $this->getActionHandler('DBTech\Shop:Item\Reassign');
		$actions['move'] = $this->getActionHandler('DBTech\Shop:Item\Move');
		$actions['apply_prefix'] = $this->getActionHandler('DBTech\Shop:Item\ApplyPrefix');

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