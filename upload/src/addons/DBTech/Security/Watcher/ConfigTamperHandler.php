<?php

namespace DBTech\Security\Watcher;

use DBTech\Security\Entity\Watcher;
use XF\Entity\User;
use XF\Phrase;
use XF\PrintableException;

class ConfigTamperHandler extends AbstractHandler
{
	/**
	 *
	 */
	protected function init(): void
	{
		$this->options = array_replace($this->options, [
			'banUser' => false,
			'banIp' => false,
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
		if (!empty($watcher->extra_data['differences'])
			&& $watcher->extra_data['differences'] == $params['differences']
		)
		{
			return false;
		}

		$logMessage = $params['differences'];

		return true;
	}

	/**
	 * @param array $params
	 * @param User|null $user
	 *
	 * @return bool
	 */
	protected function preCheck(array &$params, ?User $user = null): bool
	{
		$app = $this->app();
		$c = $app->container();
		$config = [];

		/** @noinspection PhpIncludeInspection */
		require $app['config.file'];

		$existingConfig = \XF::config();

		$differences = [];
		foreach ($config AS $key => $val)
		{
			if (is_array($val))
			{
				// We can't check this right now
				continue;
			}

			if (
				$existingConfig[$key] != $val
				&& !($existingConfig[$key] instanceof \Closure)
				&& !($val instanceof \Closure)
			)
			{
				$differences[] = '<li>' . $this->defaultLanguage->renderPhrase('dbtech_security_x_y_changed', [
					'param1' => $key,
					'param2' => $existingConfig[$key],
					'param3' => $val,
				]) . '</li>';
			}
		}

		//		$differences[] = '<li><strong>$config[\'test\']</strong> changed from <strong>old</strong> to <strong>new</strong></li>';

		if (!$differences)
		{
			return false;
		}

		$params['differences'] = is_array($differences) ? implode("\n", $differences) : $differences;

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
		$watcher->extra_data = [
			'differences' => $logMessage,
		];
		$watcher->save();
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate(): ?string
	{
		return null;
	}
}