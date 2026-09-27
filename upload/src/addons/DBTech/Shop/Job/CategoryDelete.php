<?php

namespace DBTech\Shop\Job;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Finder\ItemFinder;
use XF\Job\AbstractJob;
use XF\Job\JobResult;
use XF\PrintableException;

class CategoryDelete extends AbstractJob
{
	/**
	 * @var array
	 */
	protected $defaultData = [
		'category_id' => null,
		'count' => 0,
		'total' => null,
	];

	/**
	 * @param $maxRunTime
	 *
	 * @return JobResult
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws PrintableException
	 */
	public function run($maxRunTime): JobResult
	{
		$s = microtime(true);

		if (!$this->data['category_id'])
		{
			throw new \InvalidArgumentException('Cannot delete items without a category_id.');
		}

		$finder = \XF::app()->finder(ItemFinder::class)
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

			$item = \XF::app()->em()->find(Item::class, $id);
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