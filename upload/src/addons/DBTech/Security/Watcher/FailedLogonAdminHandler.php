<?php

namespace DBTech\Security\Watcher;

use DBTech\Security\Entity\Watcher;
use DBTech\Security\Finder\AdminStrikeFinder;
use XF\Entity\User;
use XF\Phrase;

class FailedLogonAdminHandler extends AbstractHandler
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

		if (empty($params['username'])
			|| empty($params['ipaddress'])
		)
		{
			return false;
		}

		$strikeFinder = \XF::app()->finder(AdminStrikeFinder::class)
			->where('dateline', '>=', \XF::$time - (3600 * $watcher->rule_data['hours']))
		;
		if ($watcher->rule_data['ipaddresses'] == 'same')
		{
			$strikeFinder->where('ipaddress', $params['ipaddress']);
		}

		if ($strikeFinder->total() != $watcher->rule_data['intrusions'])
		{
			return false;
		}

		/** @var \DBTech\Security\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if ($visitor->user_id)
		{
			$logMessage = '<li>' . $this->defaultLanguage->renderPhrase('dbtech_security_failedlogonadmin_user', [
				'param1' => $this->defaultLanguage->dateTime(\XF::$time),
				'param2' => $visitor->username,
				'param3' => $visitor->user_id,
				'param4' => $params['username'],
				'param5' => $params['ipaddress'],
			]) . '</li>';
		}
		else
		{
			$logMessage = '<li>' . $this->defaultLanguage->renderPhrase('dbtech_security_failedlogonadmin_guest', [
				'param1' => $this->defaultLanguage->dateTime(\XF::$time),
				'param2' => $this->defaultLanguage->renderPhrase('n_a'),
				'param3' => $params['username'],
				'param4' => $params['ipaddress'],
			]) . '</li>';
		}

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