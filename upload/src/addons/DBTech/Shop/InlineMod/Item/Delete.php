<?php

namespace DBTech\Shop\InlineMod\Item;

use XF\Http\Request;
use XF\InlineMod\AbstractAction;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;

/**
 * Class Delete
 *
 * @package DBTech\Shop\InlineMod\Item
 */
class Delete extends AbstractAction
{
	/**
	 * @return \XF\Phrase
	 */
	public function getTitle(): \XF\Phrase
	{
		return \XF::phrase('dbtech_shop_delete_items...');
	}
	
	/**
	 * @param Entity $entity
	 * @param array $options
	 * @param null $error
	 *
	 * @return bool|mixed
	 */
	protected function canApplyToEntity(Entity $entity, array $options, &$error = null): bool
	{
		/** @var \DBTech\Shop\Entity\Item $entity */
		return $entity->canDelete($options['type'], $error);
	}
	
	/**
	 * @param Entity $entity
	 * @param array $options
	 *
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	protected function applyToEntity(Entity $entity, array $options)
	{
		/** @var \DBTech\Shop\Entity\Item $entity */
		
		/** @var \DBTech\Shop\Service\Item\Delete $deleter */
		$deleter = $this->app()->service('DBTech\Shop:Item\Delete', $entity);

		if ($options['alert'])
		{
			$deleter->setSendAlert(true, $options['alert_reason']);
		}

		$deleter->delete($options['type'], $options['reason']);

		if ($options['type'] == 'hard')
		{
			$this->returnUrl = $this->app()->router()->buildLink('dbtech-shop/categories', $entity->Category);
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
			'alert_reason' => ''
		];
	}
	
	/**
	 * @param AbstractCollection $entities
	 * @param \XF\Mvc\Controller $controller
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 */
	public function renderForm(AbstractCollection $entities, \XF\Mvc\Controller $controller): \XF\Mvc\Reply\AbstractReply
	{
		$viewParams = [
			'items' => $entities,
			'total' => count($entities),
			'canHardDelete' => $this->canApply($entities, ['type' => 'hard'])
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
			'alert_reason' => $request->filter('author_alert_reason', 'str')
		];
	}
}