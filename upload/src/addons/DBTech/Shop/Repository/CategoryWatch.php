<?php

namespace DBTech\Shop\Repository;

use XF\Mvc\Entity\Repository;

/**
 * Class CategoryWatch
 *
 * @package DBTech\Shop\Repository
 */
class CategoryWatch extends Repository
{
	/**
	 * @param \DBTech\Shop\Entity\Category $category
	 * @param \XF\Entity\User $user
	 * @param $action
	 * @param array $config
	 *
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	public function setWatchState(\DBTech\Shop\Entity\Category $category, \XF\Entity\User $user, $action, array $config = [])
	{
		if (!$category->category_id || !$user->user_id)
		{
			throw new \InvalidArgumentException('Invalid category or user');
		}

		$watch = $this->em->find('DBTech\Shop:CategoryWatch', [
			'category_id' => $category->category_id,
			'user_id' => $user->user_id
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
					/** @var \DBTech\Shop\Entity\CategoryWatch $watch */
					$watch = $this->em->create('DBTech\Shop:CategoryWatch');
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
	 * @param \XF\Entity\User $user
	 * @param $action
	 * @param array $updates
	 *
	 * @return int
	 */
	/**
	 * @param \XF\Entity\User $user
	 * @param $action
	 * @param array $updates
	 *
	 * @return int
	 * @throws \InvalidArgumentException
	 */
	public function setWatchStateForAll(\XF\Entity\User $user, $action, array $updates = []): int
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