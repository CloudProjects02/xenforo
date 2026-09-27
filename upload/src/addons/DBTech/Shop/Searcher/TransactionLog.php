<?php

namespace DBTech\Shop\Searcher;

use DBTech\Shop\Repository\CurrencyRepository;
use XF\Mvc\Entity\Finder;
use XF\Searcher\AbstractSearcher;
use XF\Util\Ip;

class TransactionLog extends AbstractSearcher
{
	/** @var array */
	protected $allowedRelations = ['User', 'Recipient', 'Ip'];

	/** @var array */
	protected $formats = [
		'username' => 'like',
		'dateline' => 'date',
	];

	/** @var array */
	protected $order = [['dateline', 'desc']];


	/**
	 * @return string
	 */
	protected function getEntityType(): string
	{
		return 'DBTech\Shop:TransactionLog';
	}

	/**
	 * @return array
	 */
	protected function getDefaultOrderOptions(): array
	{
		$orders = [
			'dateline' => \XF::phrase('date'),
			'User.username' => \XF::phrase('user_name'),
			'Recipient.username' => \XF::phrase('recipient'),
		];

		\XF::fire('dbtech_shop_transaction_log_searcher_orders', [$this, &$orders]);

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
		return [
			'currencies' => \XF::app()->repository(CurrencyRepository::class)
				->getCurrencyTitlePairs(),
		];
	}

	/**
	 * @return array
	 */
	public function getFormDefaults(): array
	{
		return [
			'action' => [
				'purchase',
				'donate',
				'stealsuccess',
				'stealfail',
				'deposit',
				'withdraw',
				'interest',
				'discard',
				'gift',
				'sale',
				'sellback',
				'perreply',
				'perthread',
				'lotteryticket',
				'lotteryprize',
				'pointsadjust',
				'trade',
				'transfer',
			],
		];
	}
}