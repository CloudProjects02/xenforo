<?php

namespace DBTech\Shop\Repository;

use XF\Mvc\Entity\Repository;

/**
 * Class ItemWatch
 *
 * @package DBTech\Shop\Repository
 */
class ItemWatch extends Repository
{
	/**
	 * @param \DBTech\Shop\Entity\Item $item
	 * @param \XF\Entity\User $user
	 * @param bool $onCreation
	 *
	 * @return null|\XF\Mvc\Entity\Entity
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	public function autoWatchItem(\DBTech\Shop\Entity\Item $item, \XF\Entity\User $user, bool $onCreation = false)
	{
		$userField = $onCreation ? 'creation_watch_state' : 'interaction_watch_state';

		if (!$item->item_id || !$user->user_id || !$user->Option->getValue($userField))
		{
			return null;
		}

		$watch = $this->em->find('DBTech\Shop:ItemWatch', [
			'item_id' => $item->item_id,
			'user_id' => $user->user_id
		]);
		if ($watch)
		{
			return null;
		}

		/** @var \DBTech\Shop\Entity\ItemWatch $watch */
		$watch = $this->em->create('DBTech\Shop:ItemWatch');
		$watch->item_id = $item->item_id;
		$watch->user_id = $user->user_id;
		$watch->email_subscribe = ($user->Option->getValue($userField) == 'watch_email');

		try
		{
			$watch->save();
		}
		/** @noinspection PhpRedundantCatchClauseInspection */
		catch (\XF\Db\DuplicateKeyException $e)
		{
			return null;
		}

		return $watch;
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Item $item
	 * @param \XF\Entity\User $user
	 * @param $action
	 * @param array $config
	 *
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	public function setWatchState(
		\DBTech\Shop\Entity\Item $item,
		\XF\Entity\User $user,
		string $action,
		array $config = []
	) {
		if (!$item->item_id || !$user->user_id)
		{
			throw new \InvalidArgumentException('Invalid item or user');
		}

		$watch = $this->em->find('DBTech\Shop:ItemWatch', [
			'item_id' => $item->item_id,
			'user_id' => $user->user_id
		]);

		switch ($action)
		{
			case 'watch':
				if (!$watch)
				{
					$watch = $this->em->create('DBTech\Shop:ItemWatch');
					$watch->item_id = $item->item_id;
					$watch->user_id = $user->user_id;
				}
				unset($config['item_id'], $config['user_id']);

				$watch->bulkSet($config);
				$watch->save();
				break;

			case 'update':
				if ($watch)
				{
					unset($config['item_id'], $config['user_id']);

					$watch->bulkSet($config);
					$watch->save();
				}
				break;

			case 'delete':
				if ($watch)
				{
					$watch->delete();
				}
				break;

			default:
				throw new \InvalidArgumentException("Unknown action '$action' (expected: delete/watch)");
		}
	}
	
	/**
	 * @param \XF\Entity\User $user
	 * @param string $action
	 * @param array $updates
	 *
	 * @return int
	 * @throws \InvalidArgumentException
	 */
	public function setWatchStateForAll(\XF\Entity\User $user, string $action, array $updates = []): int
	{
		if (!$user->user_id)
		{
			throw new \InvalidArgumentException('Invalid user');
		}

		$db = $this->db();

		switch ($action)
		{
			case 'update':
				unset($updates['item_id'], $updates['user_id']);
				return $db->update('xf_dbtech_shop_item_watch', $updates, 'user_id = ?', $user->user_id);

			case 'delete':
				return $db->delete('xf_dbtech_shop_item_watch', 'user_id = ?', $user->user_id);

			default:
				throw new \InvalidArgumentException("Unknown action '$action'");
		}
	}

	/**
	 * @param string $state
	 *
	 * @return bool
	 */
	public function isValidWatchState(string $state): bool
	{
		switch ($state)
		{
			case 'watch':
			case 'update':
			case 'delete':

			default:
				return false;
		}
	}
}