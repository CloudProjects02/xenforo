<?php

namespace DBTech\Shop\Repository;

use DBTech\Shop\Entity\Category;
use DBTech\Shop\Entity\CategoryWatch;
use XF\Entity\User;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;

class CategoryWatchRepository extends Repository
{
	/**
	 * @param Category $category
	 * @param User $user
	 * @param $action
	 * @param array $config
	 *
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function setWatchState(Category $category, User $user, $action, array $config = []): void
	{
		if (!$category->category_id || !$user->user_id)
		{
			throw new \InvalidArgumentException('Invalid category or user');
		}

		$watch = \XF::app()->em()->find(CategoryWatch::class, [
			'category_id' => $category->category_id,
			'user_id' => $user->user_id,
		]);

		switch ($action)
		{
			case 'delete':
				if ($watch)
				{
					$watch->delete();
				}
				break;

			case 'watch':
				if (!$watch)
				{
					$watch = \XF::app()->em()->create(CategoryWatch::class);
					$watch->category_id = $category->category_id;
					$watch->user_id = $user->user_id;
				}
				unset($config['category_id'], $config['user_id']);

				$watch->bulkSet($config);
				$watch->save();
				break;

			case 'update':
				if ($watch)
				{
					unset($config['category_id'], $config['user_id']);

					$watch->bulkSet($config);
					$watch->save();
				}
				break;

			default:
				throw new \InvalidArgumentException("Unknown action '$action' (expected: delete/watch/update)");
		}
	}

	/**
	 * @param User $user
	 * @param $action
	 * @param array $updates
	 *
	 * @return int
	 */
	/**
	 * @param User $user
	 * @param $action
	 * @param array $updates
	 *
	 * @return int
	 * @throws \InvalidArgumentException
	 */
	public function setWatchStateForAll(User $user, $action, array $updates = []): int
	{
		if (!$user->user_id)
		{
			throw new \InvalidArgumentException('Invalid user');
		}

		$db = $this->db();

		switch ($action)
		{
			case 'update':
				unset($updates['category_id'], $updates['user_id']);
				return $db->update('xf_dbtech_shop_category_watch', $updates, 'user_id = ?', $user->user_id);

			case 'delete':
				return $db->delete('xf_dbtech_shop_category_watch', 'user_id = ?', $user->user_id);

			default:
				throw new \InvalidArgumentException("Unknown action '$action'");
		}
	}
}