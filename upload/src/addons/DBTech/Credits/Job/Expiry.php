<?php

namespace DBTech\Credits\Job;

use DBTech\Credits\Entity\Currency;
use DBTech\Credits\Entity\Transaction;
use DBTech\Credits\Finder\CurrencyFinder;
use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Job\AbstractRebuildJob;
use XF\Mvc\Entity\AbstractCollection;
use XF\Phrase;
use XF\PrintableException;

class Expiry extends AbstractRebuildJob
{
	/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Credits\Entity\Currency> */
	protected AbstractCollection $currencies;


	/**
	 * @param array $data
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function setupData(array $data): array
	{
		$this->currencies = \XF::app()->finder(CurrencyFinder::class)
			->fetch()
			->filter(function (Currency $currency): ?Currency
			{
				if (!$currency->isActive())
				{
					return null;
				}

				return $currency;
			})
		;

		return parent::setupData($data);
	}

	/**
	 * @param $start
	 * @param $batch
	 *
	 * @return array
	 */
	protected function getNextIds($start, $batch): array
	{
		$db = \XF::app()->db();

		return $db->fetchAllColumn($db->limit(
			'
				SELECT transaction_id
				FROM xf_dbtech_credits_transaction
				WHERE transaction_id > ?
					AND expiry_date <= ?
				  	AND expiry_date > 0
					AND transaction_state = \'visible\'
				ORDER BY transaction_id
			',
			$batch
		), [$start, \XF::$time]);
	}

	/**
	 * @param $id
	 *
	 * @throws PrintableException
	 * @throws \Exception
	 */
	protected function rebuildById($id): void
	{
		$transaction = \XF::app()->em()->find(Transaction::class, $id);
		if (!$transaction)
		{
			return;
		}

		$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

		$expiry = $eventTriggerRepo->getHandler('expiry');
		$expiry->apply($id, [
			'multiplier'  => (-1 * $transaction->amount),
			'currency_id' => $transaction->currency_id,
		], $transaction->TargetUser);

		// Flag transaction as expired
		$transaction->fastUpdate('expiry_date', 0);
	}

	/**
	 * @return Phrase
	 */
	protected function getStatusType(): Phrase
	{
		return \XF::phrase('dbtech_credits_credits');
	}
}