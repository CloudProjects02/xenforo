<?php

namespace DBTech\Shop\InlineMod;

use DBTech\Shop\Entity\Purchase;
use DBTech\Shop\Service\Purchase\SellbackService;
use XF\InlineMod\AbstractHandler;
use XF\Mvc\Entity\Entity;

class PurchaseHandler extends AbstractHandler
{
	/**
	 * @return array|\XF\InlineMod\AbstractAction[]
	 * @throws \Exception
	 * @throws \LogicException
	 */
	public function getPossibleActions(): array
	{
		$actions = [];

		$actions['hide'] = $this->getSimpleActionHandler(
			\XF::phrase('dbtech_shop_hide_purchases'),
			'canEditSettings',
			function (Entity $entity)
			{
				/** @var Purchase $entity */
				$entity->hidden = true;
				$entity->save();
			}
		);

		$actions['unhide'] = $this->getSimpleActionHandler(
			\XF::phrase('dbtech_shop_unhide_purchases'),
			'canEditSettings',
			function (Entity $entity)
			{
				/** @var Purchase $entity */
				$entity->hidden = false;
				$entity->save();
			}
		);

		$actions['activate'] = $this->getSimpleActionHandler(
			\XF::phrase('dbtech_shop_activate_purchases'),
			'canEditSettings',
			function (Entity $entity)
			{
				/** @var Purchase $entity */
				if (!$entity->isActive())
				{
					$entity->getHandler()->activate($error);
				}
			}
		);

		$actions['deactivate'] = $this->getSimpleActionHandler(
			\XF::phrase('dbtech_shop_deactivate_purchases'),
			'canEditSettings',
			function (Entity $entity)
			{
				/** @var Purchase $entity */
				if ($entity->isActive())
				{
					$entity->getHandler()->deactivate($error);
				}
			}
		);

		$actions['discard'] = $this->getSimpleActionHandler(
			\XF::phrase('dbtech_shop_discard_purchases'),
			'canDiscard',
			function (Entity $entity)
			{
				/** @var Purchase $entity */
				if ($entity->canDiscard())
				{
					$entity->getHandler()->discard($error);
				}
			}
		);

		$actions['sellback'] = $this->getSimpleActionHandler(
			\XF::phrase('dbtech_shop_sellback_purchases'),
			'canSellBack',
			function (Entity $entity)
			{
				/** @var Purchase $entity */
				if ($entity->canSellBack())
				{
					$purchaseService = \XF::app()->service(SellbackService::class, $entity);

					if ($purchaseService->validate($errors))
					{
						$purchaseService->save();
					}
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
		$visitor = \XF::visitor();

		return ['Item.Permissions|' . $visitor->permission_combination_id, 'Item.Category', 'Item.Category.Permissions|' . $visitor->permission_combination_id];
	}
}