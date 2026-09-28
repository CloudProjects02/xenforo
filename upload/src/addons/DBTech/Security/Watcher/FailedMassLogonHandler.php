<?php

namespace DBTech\Security\Watcher;

use DBTech\Security\Entity\Watcher;
use XF\Entity\User;
use XF\Phrase;

class FailedMassLogonHandler extends AbstractHandler
{
	/**
	 *
	 */
	protected function init(): void
	{
		$this->options = array_replace($this->options, [
			'banUser' => false,
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

		return \XF::phrase('dbtech_security_x_hits_' . $watcher->rule_data['ipaddresses'] . '_ip_y_hours', [
			'hits' => $language->numberFormat($watcher->rule_data['intrusions']),
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
		if (empty($watcher->rule_data['intrusions']
			|| empty($watcher->rule_data['hours'])
			|| empty($watcher->rule_data['ipaddresses']))
		)
		{
			return false;
		}

		if (empty($params['ipaddress']))
		{
			return false;
		}

		$db = \XF::db();
		$usernames = $db->fetchAllColumn("
			SELECT DISTINCT username
			FROM xf_dbtech_security_login_strike
			WHERE dateline >= ?
				AND valid_user = 1
				" . (
			$watcher->rule_data['ipaddresses'] == 'same'
					? ("AND ipaddress = " . $db->quote($params['ipaddress']))
					: ''
		), [
			\XF::$time - (3600 * $watcher->rule_data['hours']),
		]);

		if (!$usernames || count($usernames) != $watcher->rule_data['intrusions'])
		{
			return false;
		}

		$logMessage = '<li>' . $this->defaultLanguage->renderPhrase('dbtech_security_failedmasslogon', [
			'param1' => $this->defaultLanguage->dateTime(\XF::$time),
			'param2' => implode(', ', $usernames),
			'param3' => $params['ipaddress'],
		]) . '</li>';

		return true;
	}

	/**
	 * @param array $input
	 *
	 * @return array
	 */
	public function filterOptions(array $input = []): array
	{
		$input = \XF::app()->inputFilterer()->filterArray($input, [
			'intrusions' => 'uint',
			'ipaddresses' => 'str',
			'hours' => 'uint',
		]);

		$input['ipaddresses'] = in_array($input['ipaddresses'], ['same', 'any']) ? $input['ipaddresses'] : 'same';

		return $input;
	}
}