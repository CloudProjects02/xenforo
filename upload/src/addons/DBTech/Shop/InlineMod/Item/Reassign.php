<?php

namespace DBTech\Shop\InlineMod\Item;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Service\Item\ReassignService;
use XF\Entity\User;
use XF\Http\Request;
use XF\InlineMod\AbstractAction;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\AbstractReply;
use XF\Phrase;
use XF\PrintableException;

class Reassign extends AbstractAction
{
	protected ?User $targetUser = null;
	protected ?int $targetUserId = null;

	/**
	 * @return Phrase
	 */
	public function getTitle(): Phrase
	{
		return \XF::phrase('dbtech_shop_reassign_items...');
	}

	/**
	 * @param AbstractCollection $entities
	 * @param array $options
	 * @param $error
	 *
	 * @return bool
	 */
	protected function canApplyInternal(AbstractCollection $entities, array $options, &$error): bool
	{
		$result = parent::canApplyInternal($entities, $options, $error);

		if ($result && $options['confirmed'] && !$options['target_user_id'])
		{
			$error = \XF::phrase('requested_user_not_found');
			return false;
		}

		return $result;
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
		return $entity->canReassign($error);
	}

	/**
	 * @param Entity $entity
	 * @param array $options
	 *
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	protected function applyToEntity(Entity $entity, array $options): void
	{
		$user = $this->getTargetUser($options['target_user_id']);
		if (!$user)
		{
			throw new \InvalidArgumentException('No target specified');
		}

		$reassigner = \XF::app()->service(ReassignService::class, $entity);

		if ($options['alert'])
		{
			$reassigner->setSendAlert(true, $options['alert_reason']);
		}

		$reassigner->reassignTo($user);
	}

	/**
	 * @return array
	 */
	public function getBaseOptions(): array
	{
		return [
			'target_user_id' => 0,
			'confirmed' => false,
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
		];
		return $controller->view('DBTech\Shop:Public:InlineMod\Item\Reassign', 'inline_mod_dbtech_shop_item_reassign', $viewParams);
	}

	/**
	 * @param AbstractCollection $entities
	 * @param Request $request
	 *
	 * @return array
	 */
	public function getFormOptions(AbstractCollection $entities, Request $request): array
	{
		$username = $request->filter('username', 'str');
		$user = \XF::app()->em()->findOne(User::class, ['username' => $username]);

		return [
			'target_user_id' => $user ? $user->user_id : 0,
			'confirmed' => true,
			'alert' => $request->filter('alert', 'bool'),
			'alert_reason' => $request->filter('alert_reason', 'str'),
		];
	}

	/**
	 * @param int $userId
	 *
	 * @return null|User
	 * @throws \InvalidArgumentException
	 */
	protected function getTargetUser(int $userId): ?User
	{
		if ($this->targetUserId && $this->targetUserId == $userId)
		{
			return $this->targetUser;
		}
		if (!$userId)
		{
			return null;
		}

		$user = \XF::app()->em()->find(User::class, $userId);
		if (!$user)
		{
			throw new \InvalidArgumentException("Invalid target user ($userId)");
		}

		$this->targetUserId = $userId;
		$this->targetUser = $user;

		return $this->targetUser;
	}
}