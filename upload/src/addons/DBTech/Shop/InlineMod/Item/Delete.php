<?php

namespace DBTech\Shop\InlineMod\Item;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Service\Item\DeleteService;
use XF\Http\Request;
use XF\InlineMod\AbstractAction;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\AbstractReply;
use XF\Phrase;
use XF\PrintableException;

class Delete extends AbstractAction
{
	/**
	 * @return Phrase
	 */
	public function getTitle(): Phrase
	{
		return \XF::phrase('dbtech_shop_delete_items...');
	}

	/**
	 * @param Entity $entity
	 * @param array $options
	 * @param null $error
	 *
	 * @return bool
	 */
	protected function canApplyToEntity(Entity $entity, array $options, &$error = null): bool
	{
		/** @var Item $entity */
		return $entity->canDelete($options['type'], $error);
	}

	/**
	 * @param Entity $entity
	 * @param array $options
	 *
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	protected function applyToEntity(Entity $entity, array $options): void
	{
		/** @var Item $entity */

		$deleter = \XF::app()->service(DeleteService::class, $entity);

		if ($options['alert'])
		{
			$deleter->setSendAlert(true, $options['alert_reason']);
		}

		$deleter->delete($options['type'], $options['reason']);

		if ($options['type'] == 'hard')
		{
			$this->returnUrl = \XF::app()->router()->buildLink('dbtech-shop/categories', $entity->Category);
		}
	}

	/**
	 * @return array
	 */
	public function getBaseOptions(): array
	{
		return [
			'type' => 'soft',
			'reason' => '',
			'alert' => false,
			'alert_reason' => '',
		];
	}

	/**
	 * @param AbstractCollection $entities
	 * @param Controller $controller
	 *
	 * @return AbstractReply
	 */
	public function renderForm(AbstractCollection $entities, Controller $controller): AbstractReply
	{
		$viewParams = [
			'items' => $entities,
			'total' => count($entities),
			'canHardDelete' => $this->canApply($entities, ['type' => 'hard']),
		];
		return $controller->view('DBTech\Shop:Public:InlineMod\Item\Delete', 'inline_mod_dbtech_shop_item_delete', $viewParams);
	}

	/**
	 * @param AbstractCollection $entities
	 * @param Request $request
	 *
	 * @return array
	 */
	public function getFormOptions(AbstractCollection $entities, Request $request): array
	{
		return [
			'type' => $request->filter('hard_delete', 'bool') ? 'hard' : 'soft',
			'reason' => $request->filter('reason', 'str'),
			'alert' => $request->filter('author_alert', 'bool'),
			'alert_reason' => $request->filter('author_alert_reason', 'str'),
		];
	}
}