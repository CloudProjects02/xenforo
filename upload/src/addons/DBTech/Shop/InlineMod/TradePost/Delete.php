<?php

namespace DBTech\Shop\InlineMod\TradePost;

use DBTech\Shop\Entity\TradePost;
use DBTech\Shop\Service\TradePost\DeleterService;
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
		return \XF::phrase('delete_posts...');
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
		/** @var TradePost $entity */
		return $entity->canDelete($options['type'], $error);
	}

	/**
	 * @param Entity $entity
	 * @param array $options
	 *
	 * @throws PrintableException
	 */
	protected function applyToEntity(Entity $entity, array $options): void
	{
		$deleter = \XF::app()->service(DeleterService::class, $entity);

		if ($options['alert'])
		{
			$deleter->setSendAlert(true, $options['alert_reason']);
		}

		$deleter->delete($options['type'], $options['reason']);
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
			'profilePosts' => $entities,
			'total' => count($entities),
			'canHardDelete' => $this->canApply($entities, ['type' => 'hard']),
		];
		return $controller->view('DBTech\Shop:Public:InlineMod\TradePost\Delete', 'inline_mod_dbtech_shop_trade_post_delete', $viewParams);
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