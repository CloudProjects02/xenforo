<?php

namespace DBTech\Shop\Spam\Cleaner;

use DBTech\Shop\Finder\ItemFinder;
use XF\PrintableException;
use XF\Spam\Cleaner\AbstractHandler;

class Item extends AbstractHandler
{
	/**
	 * @param array $options
	 *
	 * @return bool
	 */
	public function canCleanUp(array $options = []): bool
	{
		return !empty($options['action_threads']);
	}

	/**
	 * @param array $log
	 * @param null $error
	 *
	 * @return bool
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function cleanUp(array &$log, &$error = null): bool
	{
		$app = \XF::app();

		$items = \XF::app()->finder(ItemFinder::class)
			->where('user_id', $this->user->user_id)
			->fetch();

		if ($items->count())
		{
			$submitter = $app->container('spam.contentSubmitter');
			$submitter->submitSpam('dbtech_shop_item', $items->keys());

			$deleteType = $app->options()->spamMessageAction == 'delete' ? 'hard' : 'soft';

			$log['dbtech_shop_item'] = [
				'deleteType' => $deleteType,
				'itemIds' => [],
			];

			foreach ($items AS $itemId => $item)
			{
				$log['dbtech_shop_item']['itemIds'][] = $itemId;

				/** @var \DBTech\Shop\Entity\Item $item */
				$item->setOption('log_moderator', false);
				if ($deleteType == 'soft')
				{
					$item->softDelete();
				}
				else
				{
					$item->delete();
				}
			}
		}

		return true;
	}

	/**
	 * @param array $log
	 * @param null $error
	 *
	 * @return bool
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function restore(array $log, &$error = null): bool
	{
		if ($log['deleteType'] == 'soft')
		{
			$items = \XF::app()->finder(ItemFinder::class)
				->where('item_id', $log['itemIds'])
				->fetch();

			foreach ($items AS $item)
			{
				/** @var \DBTech\Shop\Entity\Item $item */
				$item->setOption('log_moderator', false);
				$item->item_state = 'visible';
				$item->save();
			}
		}

		return true;
	}
}