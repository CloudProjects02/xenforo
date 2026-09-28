<?php

namespace DBTech\Security\Option;

use DBTech\Security\Repository\WatcherRepository as WatcherRepo;
use XF\Entity\Option;
use XF\Option\AbstractOption;

class BadBehavior extends AbstractOption
{
	public const MIN_PHP_VERSION = '7.2.0';
	public const MAX_PHP_VERSION = '8.0.99';

	public static function renderOption(Option $option, array $htmlParams): string
	{
		return self::getTemplate(
			'admin:option_template_dbtech_security_badbehavior_enabled',
			$option,
			$htmlParams,
			[
				'noBadBehavior' => WatcherRepo::noBadBehavior(),
				'minVersion' => WatcherRepo::BB2_MIN_PHP_VERSION,
				'maxVersion' => WatcherRepo::BB2_MAX_PHP_VERSION,
				'yourVersion' => phpversion(),
			]
		);
	}

	public static function verifyOption(&$value, Option $option): bool
	{
		if ($value && !WatcherRepo::noBadBehavior())
		{
			$option->error(
				\XF::phrase('dbtech_security_bad_behavior_cannot_run_x_y_z', [
					'minVersion' => WatcherRepo::BB2_MIN_PHP_VERSION,
					'maxVersion' => WatcherRepo::BB2_MAX_PHP_VERSION,
					'yourVersion' => phpversion(),
				]),
				$option->option_id
			);
			return false;
		}

		return true;
	}
}