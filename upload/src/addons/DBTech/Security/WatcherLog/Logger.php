<?php

namespace DBTech\Security\WatcherLog;

use DBTech\Security\Entity\Watcher;
use DBTech\Security\Entity\WatcherLog;
use XF\Entity\User;
use XF\PrintableException;

class Logger
{
	/**
	 * @param Watcher $content
	 * @param string $action
	 * @param string $message
	 * @param array $params
	 * @param bool $throw
	 * @param User|null $actor
	 *
	 * @return WatcherLog|null
	 * @throws PrintableException
	 */
	public function log(
		Watcher $content,
		string  $action,
		string  $message,
		array   $params = [],
		bool    $throw = true,
		?User   $actor = null
	): ?WatcherLog
	{
		if ($this->isLoggableUser($content, $action, $actor) === null)
		{
			return null;
		}

		$log = \XF::app()->em()->create(WatcherLog::class);
		$log->log_date = \XF::$time;
		if ($this->isLoggableUser($content, $action, $actor))
		{
			$log->user_id = $actor->user_id;
		}
		$log->ip_address = !empty($params['ip_address']) ? $params['ip_address'] : '';
		$log->watcher_id = $content->watcher_id;
		$log->action = $action;
		$log->action_params = $params;
		$log->message = $message;
		$log->save($throw);

		return $log;
	}

	/**
	 * @param $content
	 * @param $action
	 * @param User|null $actor
	 *
	 * @return bool|int|null
	 */
	public function isLoggableUser($content, $action, ?User $actor = null): bool|int|null
	{
		if ($action == 'ban_user')
		{
			return $actor?->user_id;

		}

		return false;
	}
}