<?php

namespace DBTech\Shop\Job;

use XF\Job\AbstractJob;

/**
 * Class CategoryDelete
 *
 * @package DBTech\Shop\Job
 */
class CategoryDelete extends AbstractJob
{
	/**
	 * @var array
	 */
	protected $defaultData = [
		'category_id' => null,
		'count' => 0,
		'total' => null
	];
	
	/**
	 * @param $maxRunTime
	 *
	 * @return \XF\Job\JobResult
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \XF\PrintableException
	 */
	public function run($maxRunTime): \XF\Job\JobResult
	{
		$s = microtime(true);

		if (!$this->data['category_id'])
		{
			throw new \InvalidArgumentException('Cannot delete items without a category_id.');
		}

		$finder = $this->app->finder('DBTech\Shop:Item')
			->where('category_id', $this->data['category_id']);

		if ($this->data['total'] === null)
		{
			$this->data['total'] = $finder->total();
			if (!$this->data['total'])
			{
				return $this->complete();
			}
		}

		$ids = $finder->pluckFrom('item_id')->fetch(1000);
		if (!$ids)
		{
			return $this->complete();
		}

		$continue = count($ids) >= 1000;

		foreach ($ids AS $id)
		{
			$this->data['count']++;

			/** @var \DBTech\Shop\Entity\Item $item */
			$item = $this->app->find('DBTech\Shop:Item', $id);
			if (!$item)
			{
				continue;
			}

			// This can only mean we did not have any valid fallback categories
			$item->delete();

			if ($maxRunTime && microtime(true) - $s > $maxRunTime)
			{
				$continue = true;
				break;
			}
		}

		if ($continue)
		{
			return $this->resume();
		}
		
		return $this->complete();
	}

	/**
	 * @return string
	 */
	public function getStatusMessage(): string
	{
		$actionPhrase = \XF::phrase('deleting');
		$typePhrase = \XF::phrase('dbtech_shop_items');
		return sprintf(
			'%s... %s (%s/%s)',
			$actionPhrase,
			$typePhrase,
			\XF::language()->numberFormat($this->data['count']),
			\XF::language()->numberFormat($this->data['total'])
		);
	}

	/**
	 * @return bool
	 */
	public function canCancel(): bool
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function canTriggerByChoice(): bool
	{
		return false;
	}
}