<?php

namespace DBTech\Security\Watcher;

use DBTech\Security\Entity\Watcher;
use XF\Entity\User;
use XF\Finder\ChangeLogFinder;
use XF\Phrase;
use XF\Repository\ChangeLogRepository;

class ForumPermissionsHandler extends AbstractHandler
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
		if (empty($params['ipaddress'])
			|| !isset($params['script'])
			|| !isset($params['id'])
			|| !isset($params['field'])
			|| !isset($params['differences'])
		)
		{
			return false;
		}

		$changeRepo = \XF::app()->repository(ChangeLogRepository::class);
		$changeRepo->logChange(
			'dbtech_security_forumperm',
			$params['id'],
			$params['field'],
			$params['old'],
			$params['new'],
			\XF::visitor()->user_id
		);

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
		array &$params = [],
		?User $user = null,
		?string &$logMessage = null
	): bool
	{
		if (empty($watcher->rule_data['intrusions']
			|| empty($watcher->rule_data['hours']))
		)
		{
			return false;
		}

		$changeLogFinder = \XF::app()->finder(ChangeLogFinder::class)
			->where('content_type', 'dbtech_security_forumperm')
			->where('field', $params['field'])
			->where('edit_user_id', \XF::visitor()->user_id)
			->where('edit_date', '>=', \XF::$time - (3600 * $watcher->rule_data['hours']))
		;
		if ($changeLogFinder->total() != $watcher->rule_data['intrusions'])
		{
			return false;
		}

		$logMessage = '<li>' . $this->defaultLanguage->renderPhrase('dbtech_security_' . $params['script'], [
			'date' => $this->defaultLanguage->dateTime(\XF::$time),
			'diffs' => $params['differences'],
			'ip' => $params['ipaddress'],
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
			'hours' => 'uint',
		]);
	}
}