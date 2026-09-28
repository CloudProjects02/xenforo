<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Repository;

use XF\Db\Exception;
use XF\PrintableException;

/**
 * @extends \XF\Repository\UserTfaTrustedRepository
 */
class UserTfaTrustedRepository extends XFCP_UserTfaTrustedRepository
{
	/**
	 * @param $userId
	 * @param null $trustedUntil
	 *
	 * @return mixed|null
	 * @throws PrintableException
	 */
	public function createTrustedKey($userId, $trustedUntil = null)
	{
		$previous = parent::createTrustedKey($userId, $trustedUntil);

		// Get the trust record
		$trustRecord = $this->getTfaTrustRecord($userId, $previous);

		// Set & save user agent
		$trustRecord->dbtech_security_user_agent = \XF::app()->request()->getUserAgent();
		$trustRecord->save();

		// Return the key
		return $previous;
	}

	/**
	 * @param int $userId
	 * @param int $trustedId
	 *
	 * @throws Exception
	 */
	public function untrustDeviceById(int $userId, int $trustedId)
	{
		$this->db()->query("
			DELETE FROM xf_user_tfa_trusted
			WHERE user_id = ?
				AND tfa_trusted_id = ?
		", [$userId, $trustedId]);
	}

	/**
	 * @param int $userId
	 * @param string|null $notTrustedKey
	 *
	 * @return array
	 */
	public function getUserTrustedRecords(int $userId, ?string $notTrustedKey = null)
	{
		return $this->db()->fetchAll("
			SELECT *
			FROM xf_user_tfa_trusted
			WHERE user_id = ?
				AND trusted_until >= ?
				" . ($notTrustedKey ? "AND trusted_key <> " . $this->db()->quote($notTrustedKey) : '') . "
		", [$userId, \XF::$time]);
	}
}