<?php

namespace DBTech\Security\ChangeLog;

use XF\ChangeLog\AbstractHandler;

class OptionHandler extends AbstractHandler
{
	protected array $userMap = [];

	/**
	 * @return array
	 */
	protected function getLabelMap(): array
	{
		return [
			'boardActive'                     => 'option.boardActive',
			'boardInactiveMessage'            => 'option.boardInactiveMessage',
			'dbtech_security_allowip'         => 'option.dbtech_security_allowip',
			'dbtech_security_allowip_exclude' => 'option.dbtech_security_allowip_exclude',
			'dbtech_security_globalallowip'   => 'option.dbtech_security_globalallowip',
		];
	}

	/**
	 * @return array
	 */
	protected function getFormatterMap(): array
	{
		return [
			'boardActive'                     => 'formatYesNo',
			'dbtech_security_allowip'         => 'formatIpJsonArray',
			'dbtech_security_allowip_exclude' => 'formatYesNo',
			'dbtech_security_globalallowip'   => 'formatIpJsonArray',
		];
	}

	/**
	 * @param string $json
	 *
	 * @return string
	 */
	protected function formatIpJsonArray(string $json): string
	{
		$values = [];
		$ips = json_decode($json, true);
		foreach ($ips AS $ipArray)
		{
			if ($ipArray['ip'])
			{
				$values[] = $ipArray['ip'];
			}
		}

		if (empty($values))
		{
			$values[] = \XF::phrase('none');
		}

		return implode(', ', $values);
	}
}