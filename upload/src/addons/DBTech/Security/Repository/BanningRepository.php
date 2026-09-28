<?php

namespace DBTech\Security\Repository;

use XF\Entity\IpMatch;
use XF\Finder\IpMatchFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;
use XF\Util\File;
use XF\Util\Ip;

class BanningRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findTorExitNodes(): Finder
	{
		return \XF::app()->finder(IpMatchFinder::class)
			->where('match_type', 'dbtech_security_tor')
			->setDefaultOrder('start_range', 'asc');
	}

	/**
	 * @param string $ip
	 *
	 * @return bool
	 * @throws PrintableException
	 */
	public function addTorExitNode(string $ip): bool
	{
		[$niceIp, $firstByte, $startRange, $endRange] = $this->getIpRecord($ip);

		$ipBan = \XF::app()->em()->create(IpMatch::class);
		$ipBan->ip = $niceIp;
		$ipBan->match_type = 'dbtech_security_tor';
		$ipBan->first_byte = $firstByte;
		$ipBan->start_range = $startRange;
		$ipBan->end_range = $endRange;
		$ipBan->reason = '[DragonByte Security] TOR Exit Node';
		$ipBan->create_user_id = 0;

		return $ipBan->save();
	}

	/**
	 *
	 */
	public function removeBannedTorIps(): void
	{
		$this->db()->delete('xf_ip_match', 'match_type = \'banned\' AND dbtech_security_comment = \'tor\'');

		\XF::runOnce('bannedIpCache', function ()
		{
			\XF::app()->repository(\XF\Repository\BanningRepository::class)
				->rebuildBannedIpCache()
			;
		});
	}

	/**
	 *
	 */
	public function rebuildBannedTorIps(): void
	{
		$db = $this->db();
		$db->beginTransaction();

		$this->removeBannedTorIps();

		$torNodes = $this->findTorExitNodes()
			->fetch();

		$insert = [];
		foreach ($torNodes AS $torNode)
		{
			$entry = $torNode->toArray();
			$entry['match_type'] = 'banned';
			$entry['dbtech_security_comment'] = 'tor';

			$insert[] = $entry;
		}

		if ($insert)
		{
			$this->db()->insertBulk('xf_ip_match', $insert, false, false, 'IGNORE');
		}

		\XF::runOnce('bannedIpCache', function ()
		{
			\XF::app()->repository(\XF\Repository\BanningRepository::class)
				->rebuildBannedIpCache()
			;
		});

		$db->commit();
	}

	/**
	 * @return Finder
	 */
	public function findCountryIps(): Finder
	{
		return \XF::app()->finder(IpMatchFinder::class)
			->where('match_type', 'dbtech_security_country')
			->setDefaultOrder('start_range', 'asc');
	}


	/**
	 * @param string $countryCode
	 * @param string $ip
	 *
	 * @return bool
	 * @throws PrintableException
	 */
	public function banCountryIp(string $countryCode, string $ip): bool
	{
		[$niceIp, $firstByte, $startRange, $endRange] = $this->getIpRecord($ip);

		$ipBan = \XF::app()->em()->create(IpMatch::class);
		$ipBan->ip = $niceIp;
		$ipBan->match_type = 'banned';
		$ipBan->dbtech_security_comment = $countryCode;
		$ipBan->first_byte = $firstByte;
		$ipBan->start_range = $startRange;
		$ipBan->end_range = $endRange;
		$ipBan->reason = '[DragonByte Security] Blocked country';
		$ipBan->create_user_id = 0;

		return $ipBan->save();
	}

	/**
	 * @param string $countryCode
	 */
	public function removeBannedCountryIps(string $countryCode): void
	{
		$this->db()->delete(
			'xf_ip_match',
			"match_type = 'banned' AND dbtech_security_comment = ?",
			$countryCode
		);

		\XF::runOnce('bannedIpCache', function ()
		{
			\XF::app()->repository(\XF\Repository\BanningRepository::class)
				->rebuildBannedIpCache()
			;
		});
	}

	/**
	 * @param string $countryCode
	 *
	 * @throws PrintableException
	 */
	public function updateAndBanIpsForCountry(string $countryCode): void
	{
		/** @var BanningRepository $banningRepo */
		$banningRepo = \XF::app()->repository(BanningRepository::class);

		$tempFile = File::getTempFile();

		/** @noinspection HttpUrlsUsage */
		$url = 'http://www.ipdeny.com/ipblocks/data/aggregated/' . strtolower($countryCode) . '-aggregated.zone';

		$response = $this->app()
			->http()
			->reader()
			->getUntrusted($url, [], $tempFile)
		;
		if (!$response || $response->getStatusCode() != 200)
		{
			return;
		}

		// Remove existing countries
		$banningRepo->removeBannedCountryIps($countryCode);

		$ips = preg_split('/\r?\n/', file_get_contents($tempFile), -1, PREG_SPLIT_NO_EMPTY);
		foreach ($ips AS $ip)
		{
			// Add new country IP
			$banningRepo->banCountryIp($countryCode, $ip);
		}
	}

	/**
	 * @param string $ip
	 *
	 * @return array
	 * @throws PrintableException
	 */
	protected function getIpRecord(string $ip): array
	{
		$results = Ip::parseIpRangeString($ip);
		if (!$results)
		{
			throw new PrintableException(\XF::phrase('please_enter_valid_ip_or_ip_range'));
		}

		return [
			$results['printable'],
			$results['binary'][0],
			$results['startRange'],
			$results['endRange'],
		];
	}
}