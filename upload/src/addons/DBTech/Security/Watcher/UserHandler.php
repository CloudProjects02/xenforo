<?php

namespace DBTech\Security\Watcher;

use DBTech\Security\Entity\Watcher;
use XF\Entity\User;
use XF\Finder\ChangeLogFinder;
use XF\Phrase;

class UserHandler extends AbstractHandler
{
	/**
	 * @param Watcher $watcher
	 *
	 * @return Phrase
	 */
	public function getParsedRule(Watcher $watcher): Phrase
	{
		$language = \XF::language();

		return \XF::phrase('dbtech_security_x_changes_same_user_y_hours', [
			'changes' => $language->numberFormat($watcher->rule_data['intrusions']),
			'hours' => $language->numberFormat($watcher->rule_data['hours']),
		]);
	}

	/**
	 * @param array $params
	 * @param User|null $user
	 *
	 * @return bool
	 */
	protected function preCheck(array &$params, ?User $user = null): bool
	{
		if (!isset($params['field'])
			|| !in_array($params['field'], [
				'username',
				'password',
				'email',
				'user_group_id',
				'secondary_group_ids',
			])
		)
		{
			return false;
		}

		if (empty($params['ipaddress'])
			|| !isset($params['script'])
			|| !isset($params['differences'])
		)
		{
			return false;
		}

		return true;
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
		array   &$params = [],
		?User   $user = null,
		?string &$logMessage = null
	): bool
	{
		if (empty($watcher->rule_data['intrusions']
			|| empty($watcher->rule_data['hours']))
			|| $params['field'] != $watcher->rule_data['field']
		)
		{
			return false;
		}

		$changeLogFinder = \XF::app()->finder(ChangeLogFinder::class)
			->where('edit_date', '>=', \XF::$time - (3600 * $watcher->rule_data['hours']))
			->where('edit_user_id', \XF::visitor()->user_id)
			->where('field', $params['field'])
		;
		if ($changeLogFinder->total() != $watcher->rule_data['intrusions'])
		{
			return false;
		}

		$logMessage = '<li>' . $this->defaultLanguage->renderPhrase('dbtech_security_' . $params['script'], [
			'param1' => $this->defaultLanguage->dateTime(\XF::$time),
			'param2' => $params['field'],
			'param3' => $params['differences'],
			'param4' => $params['ipaddress'],
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
		return \XF::app()->inputFilterer()->filterArray($input, [
			'intrusions' => 'uint',
			'field' => 'str',
			'hours' => 'uint',
		]);
	}
}