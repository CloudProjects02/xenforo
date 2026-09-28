<?php

namespace DBTech\Security\Repository;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;

class TorRepository extends Repository
{
	/**
	 * @throws PrintableException
	 * @throws GuzzleException
	 */
	public function updateNodes(): void
	{
		$request = \XF::app()->request();

		$host = $request->getServer('HTTP_HOST');
		$port = intval($request->getServer('SERVER_PORT'));
		if (!$host)
		{
			$host = $request->getServer('SERVER_NAME');
		}

		//		$host = '78.157.218.102';

		try
		{
			$response = $this->app()
				->http()
				->client()
				->get('https://check.torproject.org/torbulkexitlist?ip=' . $host . '&port=' . $port)
			;
		}
		catch (RequestException $e)
		{
			\XF::logException($e, false, "[Security] Error fetching TOR exit nodes: ");
			return;
		}

		$contents = $response->getBody()->getContents();
		if ($contents[0] !== '1')
		{
			return;
		}

		$banningRepo = \XF::app()->repository(BanningRepository::class);

		$this->db()->delete('xf_ip_match', 'match_type = \'dbtech_security_tor\'');

		$ipAddresses = preg_split('#\r?\n#', $contents, -1, PREG_SPLIT_NO_EMPTY);
		foreach ($ipAddresses AS $ip)
		{
			if ($ip[0] == '#')
			{
				continue;
			}

			$banningRepo->addTorExitNode($ip);
		}

		if (\XF::app()->options()->dbtech_security_tornodes)
		{
			$banningRepo->rebuildBannedTorIps();
		}
	}
}