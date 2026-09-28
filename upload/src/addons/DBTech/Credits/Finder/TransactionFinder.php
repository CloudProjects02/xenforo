<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Credits\Finder;

use DBTech\Credits\Entity\Currency;
use DBTech\Credits\XF\Entity\User;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Credits\Entity\Transaction> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Credits\Entity\Transaction> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Credits\Entity\Transaction|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Credits\Entity\Transaction>
 */
class TransactionFinder extends Finder
{
	/**
	 * @param bool $allowOwnPending
	 *
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function applyGlobalVisibilityChecks(bool $allowOwnPending = false): TransactionFinder
	{
		/** @var User $visitor */
		$visitor = \XF::visitor();
		$conditions = [];
		$viewableStates = ['visible'];

		if ($visitor->canViewModeratedDbtechCreditsTransactions())
		{
			$viewableStates[] = 'moderated';
		}
		else if ($visitor->user_id && $allowOwnPending)
		{
			$conditions[] = [
				'transaction_state' => 'moderated',
				'user_id' => $visitor->user_id,
			];
		}

		$conditions[] = ['transaction_state', $viewableStates];
		$this->whereOr($conditions);

		if (!$visitor->canViewAnyDbtechCreditsTransaction())
		{
			$this->whereOr([
				['user_id', $visitor->user_id],
				['source_user_id', $visitor->user_id],
			]);
		}

		$this->where([
			['Event.active', true],
			['Event.display', true],
		]);

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Credits\Entity\Currency> $currencies */
		$container = \XF::app()->container();
		if (isset($container['dbtechCredits.currencies']) && $currencies = $container['dbtechCredits.currencies'])
		{
			$currencies = $currencies
				->filter(function (Currency $currency): ?Currency
				{
					if (!$currency->isActive())
					{
						return null;
					}

					return $currency;
				})
			;

			if (!$visitor->canBypassDbtechCreditsCurrencyPrivacy())
			{
				$currencyIds = $currencies
					->filter(function (Currency $currency): ?Currency
					{
						if (!$currency->privacy)
						{
							return null;
						}

						return $currency;
					})
					->pluckNamed('currency_id')
				;
			}
			else
			{
				$currencyIds = $currencies->pluckNamed('currency_id');
			}

			$this->where('currency_id', $currencyIds);
		}

		return $this;
	}

	/**
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function useDefaultOrder(): TransactionFinder
	{
		//		$defaultOrder = \XF::app()->options()->dbtechCreditsListDefaultOrder ?: 'last_update';
		$defaultOrder = 'dateline';
		/** @noinspection PhpConditionAlreadyCheckedInspection */
		$defaultDir = $defaultOrder == 'title' ? 'asc' : 'desc';

		$this->setDefaultOrder([
			[$defaultOrder, $defaultDir],
			['transaction_id', 'desc'],
		]);

		return $this;
	}
}