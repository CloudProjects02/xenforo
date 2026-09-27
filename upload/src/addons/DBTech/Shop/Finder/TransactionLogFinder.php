<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Shop\Finder;

use DBTech\Shop\XF\Entity\User;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Shop\Entity\TransactionLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Shop\Entity\TransactionLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Shop\Entity\TransactionLog|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Shop\Entity\TransactionLog>
 */
class TransactionLogFinder extends Finder
{
	/**
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function applyGlobalVisibilityChecks(): TransactionLogFinder
	{
		/** @var User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->canViewAnyDbtechShopTransaction())
		{
			$this->whereOr([
				['user_id', $visitor->user_id],
				['recipient_user_id', $visitor->user_id],
			]);
		}

		return $this;
	}

	/**
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function useDefaultOrder(): TransactionLogFinder
	{
		$defaultOrder = 'dateline';
		/** @noinspection PhpConditionAlreadyCheckedInspection */
		$defaultDir = $defaultOrder == 'title' ? 'asc' : 'desc';

		$this->setDefaultOrder([
			[$defaultOrder, $defaultDir],
			['transaction_log_id', 'desc'],
		]);

		return $this;
	}
}