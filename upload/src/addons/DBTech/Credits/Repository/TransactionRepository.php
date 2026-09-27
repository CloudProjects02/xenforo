<?php

namespace DBTech\Credits\Repository;

use DBTech\Credits\Entity\Transaction;
use DBTech\Credits\Finder\TransactionFinder;
use XF\Entity\User;
use XF\Mvc\Entity\Repository;
use XF\Repository\UserAlertRepository;

class TransactionRepository extends Repository
{
	/**
	 * @param array $limits
	 *
	 * @return TransactionFinder
	 * @throws \InvalidArgumentException
	 */
	public function findTransactionsForOverviewList(array $limits = []): TransactionFinder
	{
		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => false,
		], $limits);

		$transactionFinder = \XF::app()->finder(TransactionFinder::class)
			->with('full')
			->with('Event', true)
			->useDefaultOrder()
			->indexHint('FORCE', 'transaction_date');

		if ($limits['visibility'])
		{
			$transactionFinder->applyGlobalVisibilityChecks($limits['allowOwnPending']);
		}

		return $transactionFinder;
	}

	/**
	 * @return TransactionFinder
	 */
	public function findTransactionsForList(): TransactionFinder
	{
		return \XF::app()->finder(TransactionFinder::class)
			->order('transaction_id', 'DESC')
		;
	}

	/**
	 * @param User $receiver
	 * @param User $sender
	 * @param Transaction $transaction
	 * @param array $extra
	 *
	 * @return bool
	 */
	public function sendTransactionAlert(
		User $receiver,
		User $sender,
		Transaction $transaction,
		array $extra = []
	): bool
	{
		$extra = array_merge($extra, [
			'depends_on_addon_id' => 'DBTech/Credits',
		]);

		$alertRepo = \XF::app()->repository(UserAlertRepository::class);

		if ($receiver->user_id == $sender->user_id || !$sender->user_id)
		{
			$alertRepo->alert(
				$receiver,
				0,
				'',
				'dbtech_credits_txn',
				$transaction->transaction_id,
				$transaction->event_trigger_id,
				$extra
			);
		}
		else
		{
			// Sent from another user
			$alertRepo->alertFromUser(
				$receiver,
				$sender,
				'dbtech_credits_txn',
				$transaction->transaction_id,
				$transaction->event_trigger_id,
				$extra
			);
		}

		return true;
	}

	/**
	 * @param Transaction $transaction
	 * @param string $action
	 * @param string $reason
	 * @param array $extra
	 * @param User|null $forceUser
	 *
	 * @return bool
	 */
	public function sendModeratorActionAlert(
		Transaction $transaction,
		string $action,
		string $reason = '',
		array $extra = [],
		?User $forceUser = null
	): bool
	{
		if (!$forceUser)
		{
			if (!$transaction->user_id || !$transaction->TargetUser)
			{
				return false;
			}

			$forceUser = $transaction->TargetUser;
		}

		$extra = array_merge([
			'title' => $transaction->Event->getTitle(),
			//			'link' => \XF::app()->router('public')->buildLink('nopath:dbtech-credits', $transaction),
			'reason' => $reason,
			'depends_on_addon_id' => 'DBTech/Credits',
		], $extra);

		$alertRepo = \XF::app()->repository(UserAlertRepository::class);
		$alertRepo->alert(
			$forceUser,
			0,
			'',
			'user',
			$transaction->user_id,
			"dbt_credits_txn_$action",
			$extra
		);

		return true;
	}
}