<?php

namespace DBTech\Security\Watcher;

use DBTech\Security\Entity\FingerprintLog;
use DBTech\Security\Entity\Watcher;
use XF\Db\DuplicateKeyException;
use XF\Entity\User;
use XF\Phrase;
use XF\PrintableException;

class NewStaffFingerprintHandler extends AbstractHandler
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
			'adminLockUser' => true,
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
		return \XF::phrase('n_a');
	}

	/**
	 * @param array $params
	 * @param User|null $user
	 *
	 * @return bool
	 * @throws PrintableException
	 */
	protected function preCheck(array &$params, ?User $user = null): bool
	{
		if ($user === null)
		{
			return false;
		}

		if (!isset($params['fingerprint'])
			|| !isset($params['components'])
			|| !isset($params['ipaddress'])
		)
		{
			return false;
		}

		try
		{
			$fingerprintLog = \XF::app()->em()->create(FingerprintLog::class);
			$fingerprintLog->user_id = $user->user_id;
			$fingerprintLog->fingerprint = $params['fingerprint'];
			$fingerprintLog->ipaddress = $params['ipaddress'];
			$fingerprintLog->components = $params['components'];
			if (!$fingerprintLog->preSave())
			{
				return false;
			}
			$fingerprintLog->save();
		}
		/** @noinspection PhpRedundantCatchClauseInspection */
		catch (DuplicateKeyException $e)
		{
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
		array &$params = [],
		?User $user = null,
		?string &$logMessage = null
	): bool
	{
		$logMessage = '<li>' . $this->defaultLanguage->renderPhrase('dbtech_security_newfingerprint', [
			'param1' => $this->defaultLanguage->dateTime(\XF::$time),
			'param2' => $user->username,
			'param3' => $params['fingerprint'],
			'param4' => $params['ipaddress'],
		]) . '</li>';

		return true;
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate(): ?string
	{
		return null;
	}
}