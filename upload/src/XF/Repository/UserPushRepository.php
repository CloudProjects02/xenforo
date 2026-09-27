<?php

namespace XF\Repository;

use XF\Entity\User;
use XF\Mvc\Entity\Repository;

use function intval, strlen;

class UserPushRepository extends Repository
{
	public function validateSubscriptionDetails(array $subscription, &$error = null)
	{
		$endpoint = $subscription['endpoint'] ?? null;
		if ($endpoint === null)
		{
			return false;
		}

		if (!$this->isValidEndpoint($endpoint))
		{
			return false;
		}

		if (empty($subscription['key']) || empty($subscription['token']) || empty($subscription['encoding']))
		{
			return false;
		}

		if (
			strlen($subscription['key']) > 1024
			|| strlen($subscription['token']) > 256
			|| strlen($subscription['encoding']) > 64
		)
		{
			return false;
		}

		return true;
	}

	public function insertUserPushSubscription(User $user, array $subscription)
	{
		$db = $this->db();

		$endpointHash = $this->getEndpointHash($subscription['endpoint']);

		return $db->insert('xf_user_push_subscription', [
			'endpoint_hash' => $endpointHash,
			'endpoint' => $subscription['endpoint'],
			'user_id' => $user->user_id,
			'data' => json_encode([
				'key' => $subscription['key'],
				'token' => $subscription['token'],
				'encoding' => $subscription['encoding'],
			]),
			'last_seen' => time(),
		], false, '
			user_id = VALUES(user_id),
			data = VALUES(data),
			last_seen = VALUES(last_seen)
		');
	}

	public function deletePushSubscription(array $subscription)
	{
		$db = $this->db();
		$endpointHash = $this->getEndpointHash($subscription['endpoint']);
		return $db->delete('xf_user_push_subscription', 'endpoint_hash = ?', $endpointHash);
	}

	public function deleteUserPushSubscription(User $user, array $subscription)
	{
		$db = $this->db();
		$endpointHash = $this->getEndpointHash($subscription['endpoint']);
		return $db->delete('xf_user_push_subscription', 'endpoint_hash = ? AND user_id = ?', [
			$endpointHash, $user->user_id,
		]);
	}

	public function limitUserPushSubscriptionCount(User $user, $maxAllowed)
	{
		$cutOff = max(0, intval($maxAllowed)); // offset is 0 based, so this will give the max+1 row

		$lastSeenCutOff = $this->db()->fetchOne("
			SELECT last_seen
			FROM xf_user_push_subscription
			WHERE user_id = ?
			ORDER BY last_seen DESC
			LIMIT ?, 1
		", [$user->user_id, $cutOff]);
		if ($lastSeenCutOff)
		{
			$this->db()->delete(
				'xf_user_push_subscription',
				'user_id = ? AND last_seen <= ?',
				[$user->user_id, $lastSeenCutOff]
			);
		}
	}

	public function getUserSubscriptions(User $user)
	{
		return $this->db()->fetchAllKeyed(
			'SELECT *
				FROM xf_user_push_subscription
				WHERE user_id = ?
				ORDER BY endpoint_id',
			'endpoint_hash',
			[$user->user_id]
		);
	}

	public function getEndpointHash($endpoint)
	{
		return md5($endpoint);
	}

	public function isValidEndpoint(string $endpoint): bool
	{
		if (!preg_match('#https?://#i', $endpoint) || strlen($endpoint) > 2048)
		{
			return false;
		}

		$invalidEndpoints = $this->getInvalidEndpoints();
		foreach ($invalidEndpoints AS $invalidEndpoint)
		{
			if (strpos($endpoint, $invalidEndpoint) !== 0)
			{
				continue;
			}

			return false;
		}

		return true;
	}

	/**
	 * @return list<string>
	 */
	public function getInvalidEndpoints(): array
	{
		return [
			'https://android.googleapis.com/gcm/send', // GCM
			'https://permanently-removed.invalid/fcm/send', // Chrome (XF-234461)
		];
	}
}
