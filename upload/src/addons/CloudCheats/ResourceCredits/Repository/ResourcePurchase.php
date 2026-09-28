<?php

namespace CloudCheats\ResourceCredits\Repository;

use XF\Mvc\Entity\Repository;

class ResourcePurchase extends Repository
{
	public function hasPurchased(int $resourceId, int $userId): bool
	{
		return (bool) $this->db()->fetchOne(
			'SELECT 1 FROM cc_resource_purchase WHERE resource_id = ? AND user_id = ?',
			[$resourceId, $userId]
		);
	}

	public function recordPurchase(int $resourceId, int $userId, float $amount): void
	{
		$this->db()->insert('cc_resource_purchase', [
			'resource_id'  => $resourceId,
			'user_id'      => $userId,
			'purchase_date' => \XF::$time,
			'credits_paid' => $amount,
		], false, false, 'IGNORE');
	}

	public function getCurrency(): ?array
	{
		return $this->db()->fetchRow(
			'SELECT * FROM xf_dbtech_credits_currency WHERE active = 1 ORDER BY display_order ASC LIMIT 1'
		) ?: null;
	}

	public function getUserBalance(\XF\Entity\User $user, array $currency): float
	{
		$column = $currency['column'];
		return (float) $this->db()->fetchOne(
			"SELECT `{$column}` FROM xf_user WHERE user_id = ?",
			[$user->user_id]
		);
	}

	/**
	 * Deducts credits from user and logs the transaction.
	 * Returns false if user cannot afford it.
	 */
	public function deductCredits(\XF\Entity\User $user, array $currency, float $amount, int $resourceId): bool
	{
		$column  = $currency['column'];
		$balance = $this->getUserBalance($user, $currency);

		if ($balance < $amount)
		{
			return false;
		}

		$db = $this->db();

		// Deduct from user balance atomically
		$db->query(
			"UPDATE xf_user SET `{$column}` = `{$column}` - ? WHERE user_id = ? AND `{$column}` >= ?",
			[$amount, $user->user_id, $amount]
		);

		if ($db->affectedRows() === 0)
		{
			return false; // race condition guard
		}

		$newBalance = $this->getUserBalance($user, $currency);

		// Log transaction in DBTech Credits
		try
		{
			$db->insert('xf_dbtech_credits_transaction', [
				'event_id'          => 0,
				'event_trigger_id'  => 'resourcedownload',
				'user_id'           => $user->user_id,
				'dateline'          => \XF::$time,
				'source_user_id'    => 0,
				'amount'            => -$amount,
				'transaction_state' => 1,
				'reference_id'      => 0,
				'content_type'      => 'resource',
				'content_id'        => $resourceId,
				'node_id'           => 0,
				'owner_id'          => 0,
				'multiplier'        => 1,
				'currency_id'       => $currency['currency_id'],
				'negate'            => 0,
				'message'           => '',
				'is_disputed'       => 0,
				'expiry_date'       => 0,
				'balance'           => $newBalance,
			]);
		}
		catch (\Exception $e)
		{
			// Transaction logging is optional — don't fail the purchase
			\XF::logException($e, false, '[ResourceCredits] Failed to log transaction: ');
		}

		return true;
	}
}
