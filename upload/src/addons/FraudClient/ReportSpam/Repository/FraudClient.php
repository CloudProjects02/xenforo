<?php

namespace FraudClient\ReportSpam\Repository;

use XF\Mvc\Entity\Repository;
use XF\Util\Ip;

class FraudClient extends Repository
{
	public function checkUser(\XF\Entity\User $user)
	{
		$apiKey = trim((string) \XF::options()->ms_fc_api_key);
		if ($apiKey === '')
		{
			return ['error' => \XF::phrase('ms_fc_api_key_missing')];
		}

		$params = [
			'headers' => [
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $apiKey
			],
			'connect_timeout' => 5,
			'timeout' => 10,
			'http_errors' => false,
			'json' => [
				'clientEmail' => $user->email,
				'clientUsername' => $user->username,
				'clientIP' => $this->getLatestIp($user)
			]
		];

		try
		{
			$response = \XF::app()->http()->client()->post(
				'https://fraudclient.com/api.php?action=search_client_reports',
				$params
			);
			$body = json_decode((string) $response->getBody(), true);
			if (
				$response->getStatusCode() < 200
				|| $response->getStatusCode() >= 300
				|| !is_array($body)
				|| !isset($body['reports'])
				|| !is_array($body['reports'])
			)
			{
				throw new \RuntimeException('FraudClient returned an invalid check response.');
			}

			return $body;
		}
		catch (\Throwable $e)
		{
			\XF::logException($e, false, 'FraudClient API error: ');
			return ['error' => \XF::phrase('ms_fc_api_error_message')];
		}
	}

	protected function getLatestIp(\XF\Entity\User $user)
	{
		/** @var \XF\Repository\Ip $ipRepo */
		$ipRepo = $this->repository('XF:Ip');
		$ips = $ipRepo->getIpsByUser($user);
		if (!$ips)
		{
			return '';
		}

		$latestIp = reset($ips);
		return method_exists(Ip::class, 'binaryToString')
			? Ip::binaryToString($latestIp['ip'])
			: Ip::convertIpBinaryToString($latestIp['ip']);
	}
}
