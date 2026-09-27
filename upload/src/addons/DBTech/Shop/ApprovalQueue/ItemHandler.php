<?php

namespace DBTech\Shop\ApprovalQueue;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Service\Item\ApproveService;
use XF\ApprovalQueue\AbstractHandler;
use XF\Mvc\Entity\Entity;
use XF\PrintableException;

class ItemHandler extends AbstractHandler
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
			'User',
		];
	}

	/**
	 * @param Item $item
	 *
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function actionApprove(Item $item): void
	{
		$approver = \XF::app()->service(ApproveService::class, $item);
		$approver->setNotifyRunTime(1); // may be a lot happening
		$approver->approve();
	}

	/**
	 * @param Item $item
	 */
	public function actionDelete(Item $item): void
	{
		$this->quickUpdate($item, 'item_state', 'deleted');
	}
}