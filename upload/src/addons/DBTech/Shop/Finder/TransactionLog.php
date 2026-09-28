<?php

namespace DBTech\Shop\Finder;

use XF\Mvc\Entity\Finder;

/**
 * Class TransactionLog
 * @package DBTech\Shop\Finder
 */
class TransactionLog extends Finder
{
	/**
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function applyGlobalVisibilityChecks(): TransactionLog
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->canViewAnyDbtechShopTransaction())
		{
			$this->whereOr([
				['user_id', $visitor->user_id],
				['recipient_user_id', $visitor->user_id]
			]);
		}
		
		return $this;
	}
	
	/**
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function useDefaultOrder(): TransactionLog
	{
		$defaultOrder = 'dateline';
		$defaultDir = $defaultOrder == 'title' ? 'asc' : 'desc';
		
		$this->setDefaultOrder([
			[$defaultOrder, $defaultDir],
			['transaction_log_id', 'desc']
		]);
		
		return $this;
	}
}