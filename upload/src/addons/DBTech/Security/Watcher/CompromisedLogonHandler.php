<?php

namespace DBTech\Security\Watcher;

use DBTech\Security\Entity\CompromisedLog;
use DBTech\Security\Entity\Watcher;
use XF\Entity\User;
use XF\Phrase;
use XF\PrintableException;

class CompromisedLogonHandler extends AbstractHandler
{
	/**
	 *
	 */
	protected function init(): void
	{
		$this->options = array_replace($this->options, [
			'emailUser' => true,
			'lockChange' => true,
			'lockReset' => true,
			'lockUser' => true,
		]);

		parent::init();
	}

	/**
	 * @param Watcher $watcher
	 *
	 * @return Phrase
	 */
	public function getParsedRule(Watcher $watcher): Phrase
	{
		$language = \XF::language();

		return \XF::phrase('dbtech_security_x_usernames_tried_in_y_hours', [
			'usernames' => $language->numberFormat($watcher->rule_data['intrusions']),
			'hours' => $language->numberFormat($watcher->rule_data['hours']),
		]);
	}

	/**
	 * @param Watcher $watcher
	 * @param array $params
	 * @param User|null $user
	 * @param string|null $logMessage
	 *
	 * @return bool
	 */
	protected function checkWatcher(
		Watcher $watcher,
		array &$params = [],
		?User $user = null,
		?string &$logMessage = null
	): bool
	{
		if (!$user)
		{
			return false;
		}

		if (empty($params['ipaddresses']))
		{
			return false;
		}

		if (empty($watcher->rule_data['intrusions']
			|| empty($watcher->rule_data['hours']))
		)
		{
			return false;
		}

		$params['ipaddresses'] = (array) $params['ipaddresses'];

		$db = \XF::db();
		$usernames = $db->fetchAllColumn("
			SELECT DISTINCT username
			FROM xf_dbtech_security_login_strike
			WHERE ipaddress IN (" . $db->quote($params['ipaddresses']) . ")
				AND dateline >= ?
		", [
			\XF::$time - (3600 * $watcher->rule_data['hours']),
		]);

		if (!$usernames || count($usernames) < $watcher->rule_data['intrusions'])
		{
			return false;
		}

		$params['usernames'] = $usernames;

		$logMessage = '<li>' . $this->defaultLanguage->renderPhrase('dbtech_security_compromisedaccount', [
			'param1' => $this->defaultLanguage->dateTime(\XF::$time),
			'param2' => $user->username,
			'param3' => implode(', ', $params['ipaddresses']),
		]) . '</li>';

		return true;
	}

	/**
	 * @param Watcher $watcher
	 * @param array $params
	 * @param User|null $user
	 * @param string|null $logMessage
	 *
	 * @return void
	 * @throws PrintableException
	 */
	protected function postTrigger(Watcher $watcher, array $params = [], ?User $user = null, ?string $logMessage = null): void
	{
		$log = \XF::app()->em()->create(CompromisedLog::class);
		$log->user_id = $user->user_id;
		$log->ipaddress = $params['ipaddresses'][0];
		$log->dateline = \XF::$time;
		$log->attempted_usernames = implode(', ', $params['usernames']);
		$log->save();
	}

	/**
	 * @param array $input
	 *
	 * @return array
	 */
	public function filterOptions(array $input = []): array
	{
		return \XF::app()->inputFilterer()->filterArray($input, [
			'intrusions' => 'uint',
			'hours' => 'uint',
		]);
	}
}