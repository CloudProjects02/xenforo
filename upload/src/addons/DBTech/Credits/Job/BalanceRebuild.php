<?php

namespace DBTech\Credits\Job;

use DBTech\Credits\Finder\CurrencyFinder;
use DBTech\Credits\Repository\CurrencyRepository;
use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Entity\User;
use XF\Job\AbstractRebuildJob;
use XF\Mvc\Entity\AbstractCollection;
use XF\Phrase;
use XF\PrintableException;

class BalanceRebuild extends AbstractRebuildJob
{
	/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Credits\Entity\Currency> */
	protected AbstractCollection $currencies;


	/**
	 * @param array $data
	 *
	 * @return array
	 */
	protected function setupData(array $data): array
	{
		$this->currencies = \XF::app()->finder(CurrencyFinder::class)->fetch();

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
				SELECT user_id
				FROM xf_user
				WHERE user_id > ?
				ORDER BY user_id
			',
			$batch
		), $start);
	}

	/**
	 * @param $id
	 *
	 * @throws PrintableException
	 * @throws \Exception
	 */
	protected function rebuildById($id): void
	{
		$user = \XF::app()->em()->find(User::class, $id);

		$visitor = \XF::visitor();

		$repo = \XF::app()->repository(CurrencyRepository::class);

		$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
		$adjustHandler = $eventTriggerRepo->getHandler('adjust');

		foreach ($this->currencies AS $currency)
		{
			$balance = $repo->getUserBalanceFromTransactionLog($id, $currency->currency_id);

			// Make sure there's an adjust event
			$currency->verifyAdjustEvent();

			if ($user->{$currency->column} < $balance)
			{
				// Adjust event (up)
				$adjustHandler
					->apply($user->user_id, [
						'currency_id'    => $currency->currency_id,
						'multiplier'     => abs($balance - $user->{$currency->column}),
						'message'        => \XF::language()->renderPhrase('dbtech_credits_balance_correction'),
						'source_user_id' => $visitor->user_id,
						'forceVisible'   => true,
					], $user)
				;
			}
			else if ($user->{$currency->column} > $balance)
			{
				// Adjust event (down)
				$adjustHandler
					->apply($user->user_id, [
						'currency_id'    => $currency->currency_id,
						'multiplier'     => (-1 * abs($user->{$currency->column} - $balance)),
						'message'        => \XF::language()->renderPhrase('dbtech_credits_balance_correction'),
						'source_user_id' => $visitor->user_id,
						'forceVisible'   => true,
					], $user)
				;
			}
		}
	}

	/**
	 * @return Phrase
	 */
	protected function getStatusType(): Phrase
	{
		return \XF::phrase('users');
	}
}