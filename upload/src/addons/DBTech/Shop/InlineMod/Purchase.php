<?php

namespace DBTech\Shop\InlineMod;

use XF\InlineMod\AbstractHandler;
use XF\Mvc\Entity\Entity;

/**
 * Class Purchase
 *
 * @package DBTech\Shop\InlineMod
 */
class Purchase extends AbstractHandler
{
	/**
	 * @return array|\XF\InlineMod\AbstractAction[]
	 * @throws \Exception
	 * @throws \LogicException
	 */
	public function getPossibleActions(): array
	{
		$actions = [];

		$actions['discard'] = $this->getSimpleActionHandler(
			\XF::phrase('dbtech_shop_discard_purchases'),
			'canDiscard',
			function (Entity $entity)
			{
				/** @var \DBTech\Shop\Entity\Purchase $entity */
				$entity->getHandler()->discard($error, 'manual');
			}
		);

		$actions['sellback'] = $this->getSimpleActionHandler(
			\XF::phrase('dbtech_shop_sellback_purchases'),
			'canSellBack',
			function (Entity $entity)
			{
				/** @var \DBTech\Shop\Entity\Purchase $entity */

				/** @var \DBTech\Shop\Service\Purchase\Sellback $purchaseService */
				$purchaseService = \XF::app()->service('DBTech\Shop:Purchase\Sellback', $entity);
				if ($purchaseService->validate($errors))
				{
					$purchaseService->save();
				}
			}
		);

		$actions['hide'] = $this->getSimpleActionHandler(
			\XF::phrase('dbtech_shop_hide_purchases'),
			'canEditSettings',
			function (Entity $entity)
			{
				/** @var \DBTech\Shop\Entity\Purchase $entity */
				$entity->hidden = true;
				$entity->save();
			}
		);

		$actions['unhide'] = $this->getSimpleActionHandler(
			\XF::phrase('dbtech_shop_unhide_purchases'),
			'canEditSettings',
			function (Entity $entity)
			{
				/** @var \DBTech\Shop\Entity\Purchase $entity */
				$entity->hidden = false;
				$entity->save();
			}
		);

		$actions['activate'] = $this->getSimpleActionHandler(
			\XF::phrase('dbtech_shop_activate_purchases'),
			'canEditSettings',
			function (Entity $entity)
			{
				/** @var \DBTech\Shop\Entity\Purchase $entity */
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
				/** @var \DBTech\Shop\Entity\Purchase $entity */
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
				/** @var \DBTech\Shop\Entity\Purchase $entity */
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
				/** @var \DBTech\Shop\Entity\Purchase $entity */
				if ($entity->canSellBack())
				{
					/** @var \DBTech\Shop\Service\Purchase\Sellback $purchaseService */
					$purchaseService = \XF::app()->service('DBTech\Shop:Purchase\Sellback', $entity);

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