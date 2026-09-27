<?php

namespace DBTech\Shop\Repository;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Entity\ItemWatch;
use XF\Db\DuplicateKeyException;
use XF\Entity\User;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;

class ItemWatchRepository extends Repository
{
	/**
	 * @param Item $item
	 * @param User $user
	 * @param bool $onCreation
	 *
	 * @return null|ItemWatch
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function autoWatchItem(Item $item, User $user, bool $onCreation = false): ?ItemWatch
	{
		$userField = $onCreation ? 'creation_watch_state' : 'interaction_watch_state';

		if (!$item->item_id || !$user->user_id || !$user->Option->getValue($userField))
		{
			return null;
		}

		$watch = \XF::app()->em()->find(ItemWatch::class, [
			'item_id' => $item->item_id,
			'user_id' => $user->user_id,
		]);
		if ($watch)
		{
			return null;
		}

		$watch = \XF::app()->em()->create(ItemWatch::class);
		$watch->item_id = $item->item_id;
		$watch->user_id = $user->user_id;
		$watch->email_subscribe = ($user->Option->getValue($userField) == 'watch_email');

		try
		{
			$watch->save();
		}
		/** @noinspection PhpRedundantCatchClauseInspection */
		catch (DuplicateKeyException $e)
		{
			return null;
		}

		return $watch;
	}

	/**
	 * @param Item $item
	 * @param User $user
	 * @param string $action
	 * @param array $config
	 *
	 * @throws PrintableException
	 */
	public function setWatchState(
		Item $item,
		User $user,
		string $action,
		array $config = []
	): void
	{
		if (!$item->item_id || !$user->user_id)
		{
			throw new \InvalidArgumentException('Invalid item or user');
		}

		$watch = \XF::app()->em()->find(ItemWatch::class, [
			'item_id' => $item->item_id,
			'user_id' => $user->user_id,
		]);

		switch ($action)
		{
			case 'watch':
				if (!$watch)
				{
					$watch = \XF::app()->em()->create(ItemWatch::class);
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
	 * @param User $user
	 * @param string $action
	 * @param array $updates
	 *
	 * @return int
	 * @throws \InvalidArgumentException
	 */
	public function setWatchStateForAll(User $user, string $action, array $updates = []): int
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
		return match ($state)
		{
			'watch', 'update', 'delete' => true,
			default => false,
		};
	}
}