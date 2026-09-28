<?php

namespace DBTech\Security\Cron;

use DBTech\Security\Repository\BackupRepository;
use DBTech\Security\Repository\LogRepository;
use DBTech\Security\Repository\SessionRepository;
use DBTech\Security\Repository\TorRepository;
use GuzzleHttp\Exception\GuzzleException;
use XF\PrintableException;

class Security
{
	/**
	 *
	 */
	public static function pruneLogs(): void
	{
		$logRepo = \XF::app()->repository(LogRepository::class);
		$logRepo->prune();
	}

	/**
	 *
	 */
	public static function pruneSessions(): void
	{
		$sessionRepo = \XF::app()->repository(SessionRepository::class);
		$sessionRepo->prune();
	}

	/**
	 * @throws PrintableException
	 */
	public static function backupOptions(): void
	{
		$backupRepo = \XF::app()->repository(BackupRepository::class);
		$backupRepo->backupOptions();
	}

	/**
	 * @throws PrintableException
	 * @throws GuzzleException
	 */
	public static function updateTorNodes(): void
	{
		$torRepo = \XF::app()->repository(TorRepository::class);
		$torRepo->updateNodes();
	}
}