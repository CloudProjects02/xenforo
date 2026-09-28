<?php

namespace DBTech\Shop\ApprovalQueue;

use XF\ApprovalQueue\AbstractHandler;
use XF\Mvc\Entity\Entity;

/**
 * Class Item
 *
 * @package DBTech\Shop\ApprovalQueue
 */
class Item extends AbstractHandler
{
	/**
	 * @param Entity $content
	 * @param null $error
	 *
	 * @return bool
	 */
	protected function canActionContent(Entity $content, &$error = null): bool
	{
		/** @var $content \DBTech\Shop\Entity\Item */
		return $content->canApproveUnapprove($error);
	}
	
	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		$visitor = \XF::visitor();

		return [
			'Permissions|' . $visitor->permission_combination_id,
			'Category',
			'Category.Permissions|' . $visitor->permission_combination_id,
			'User'
		];
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Item $item
	 *
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	public function actionApprove(\DBTech\Shop\Entity\Item $item)
	{
		/** @var \DBTech\Shop\Service\Item\Approve $approver */
		$approver = \XF::service('DBTech\Shop:Item\Approve', $item);
		$approver->setNotifyRunTime(1); // may be a lot happening
		$approver->approve();
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Item $item
	 */
	public function actionDelete(\DBTech\Shop\Entity\Item $item)
	{
		$this->quickUpdate($item, 'item_state', 'deleted');
	}
}