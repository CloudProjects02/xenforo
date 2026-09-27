<?php

namespace DBTech\Shop\Searcher;

use DBTech\Shop\Repository\ItemRepository;
use XF\Mvc\Entity\Finder;
use XF\Searcher\AbstractSearcher;
use XF\Util\Ip;

class Purchase extends AbstractSearcher
{
	/** @var array */
	protected $allowedRelations = ['User', 'Buyer', 'Item'];

	/** @var array */
	protected $formats = [
		'username' => 'like',
		'dateline' => 'date',
		'Item' => [
			'title' => 'like',
		],
	];

	/** @var array */
	protected $order = [['dateline', 'desc']];


	/**
	 * @return string
	 */
	protected function getEntityType(): string
	{
		return 'DBTech\Shop:Purchase';
	}

	/**
	 * @return array
	 */
	protected function getDefaultOrderOptions(): array
	{
		$orders = [
			'dateline' => \XF::phrase('date'),
			'User.username' => \XF::phrase('user_name'),
			'Buyer.username' => \XF::phrase('dbtech_shop_buyer'),
			'Item.title' => \XF::phrase('dbtech_shop_item'),
		];

		\XF::fire('dbtech_shop_purchase_searcher_orders', [$this, &$orders]);

		return $orders;
	}

	/**
	 * @param Finder $finder
	 * @param $key
	 * @param $value
	 * @param $column
	 * @param $format
	 * @param $relation
	 * @return bool
	 */
	protected function applySpecialCriteriaValue(Finder $finder, $key, $value, $column, $format, $relation): bool
	{
		if ($key == 'ip')
		{
			$parsed = Ip::parseIpRangeString($value);

			if (!$parsed)
			{
				return true;
			}

			if ($parsed['isRange'])
			{
				$finder->where('Ip.ip', '>=', $parsed['startRange']);
				$finder->where('Ip.ip', '<=', $parsed['endRange']);
			}
			else
			{
				$finder->where('Ip.ip', $parsed['startRange']);
			}

			return true;
		}

		return false;
	}

	/**
	 * @return array
	 */
	public function getFormData(): array
	{
		return [];
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function getFormDefaults(): array
	{
		return [
			'item_type_ids' => array_keys(\XF::app()->repository(ItemRepository::class)->getItemTypeTitlePairs(true)),
		];
	}
}