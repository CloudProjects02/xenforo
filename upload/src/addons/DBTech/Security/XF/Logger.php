<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF;

use DBTech\Security\Entity\Watcher;
use DBTech\Security\Entity\WatcherLog;
use XF\Entity\User;
use XF\PrintableException;

/**
 * @extends \XF\Logger
 */
class Logger extends XFCP_Logger
{
	protected ?\DBTech\Security\WatcherLog\Logger $dbtechSecurityWatcherLogger = null;


	/**
	 * @return \DBTech\Security\WatcherLog\Logger
	 * @throws \Exception
	 */
	public function dbtechSecurityWatcherLogger()
	{
		if (!$this->dbtechSecurityWatcherLogger)
		{
			$class = \XF::extendClass(\DBTech\Security\WatcherLog\Logger::class);
			$this->dbtechSecurityWatcherLogger = new $class();
		}

		return $this->dbtechSecurityWatcherLogger;
	}

	/**
	 * @param Watcher $content
	 * @param string $action
	 * @param string|null $message
	 * @param array $params
	 * @param bool $throw
	 * @param User|null $actor
	 *
	 * @return WatcherLog|null
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function logDbtechSecurityWatcher(
		Watcher $content,
		string $action,
		?string $message,
		array $params = [],
		bool $throw = true,
		?User $actor = null
	)
	{
		return $this->dbtechSecurityWatcherLogger()->log($content, $action, $message, $params, $throw, $actor);
	}
}