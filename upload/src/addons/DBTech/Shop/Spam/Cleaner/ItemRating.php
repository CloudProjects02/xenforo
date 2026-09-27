<?php

namespace DBTech\Shop\Spam\Cleaner;

use DBTech\Shop\Finder\ItemRatingFinder;
use XF\PrintableException;
use XF\Spam\Cleaner\AbstractHandler;

class ItemRating extends AbstractHandler
{
	/**
	 * @param array $options
	 *
	 * @return bool
	 */
	public function canCleanUp(array $options = []): bool
	{
		return !empty($options['delete_messages']);
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

		$ratings = \XF::app()->finder(ItemRatingFinder::class)
			->where('user_id', $this->user->user_id)
			->fetch();

		if ($ratings->count())
		{
			$submitter = $app->container('spam.contentSubmitter');
			$submitter->submitSpam('dbtech_shop_rating', $ratings->keys());

			$deleteType = $app->options()->spamMessageAction == 'delete' ? 'hard' : 'soft';

			$log['dbtech_shop_rating'] = [
				'deleteType' => $deleteType,
				'ratingIds' => [],
			];

			foreach ($ratings AS $ratingId => $rating)
			{
				$log['dbtech_shop_rating']['ratingIds'][] = $ratingId;

				/** @var \DBTech\Shop\Entity\ItemRating $rating */
				$rating->setOption('log_moderator', false);
				if ($deleteType == 'soft')
				{
					$rating->softDelete();
				}
				else
				{
					$rating->delete();
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
			$ratings = \XF::app()->finder(ItemRatingFinder::class)
				->where('item_rating_id', $log['ratingIds'])
				->fetch();

			foreach ($ratings AS $rating)
			{
				/** @var \DBTech\Shop\Entity\ItemRating $rating */
				$rating->setOption('log_moderator', false);
				$rating->rating_state = 'visible';
				$rating->save();
			}
		}

		return true;
	}
}