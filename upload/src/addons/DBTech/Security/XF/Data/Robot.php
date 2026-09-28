<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Data;

/**
 * @extends \XF\Data\Robot
 */
class Robot extends XFCP_Robot
{
	public function getRobotUserAgents()
	{
		$previous = parent::getRobotUserAgents();

		if (\XF::options()->dbtech_security_spiders)
		{
			$spiders = include 'src/addons/DBTech/Security/3rdParty/spiders.php';

			foreach ($spiders AS $spider)
			{
				// Normalisation
				$spider['ident'] = strtolower($spider['ident']);

				if (isset($previous[$spider['ident']]))
				{
					// Skip this spider
					continue;
				}

				// Set the "known robots"
				$previous[$spider['ident']] = $spider['name'];
			}
		}

		return $previous;
	}

	public function getRobotList()
	{
		$previous = parent::getRobotList();

		if (\XF::options()->dbtech_security_spiders)
		{
			$spiders = include 'src/addons/DBTech/Security/3rdParty/spiders.php';

			foreach ($spiders AS $spider)
			{
				// Normalisation
				$spider['ident'] = strtolower($spider['ident']);

				if (isset($previous[$spider['ident']]))
				{
					// Skip this spider
					continue;
				}

				// Set the extended info
				$previous[$spider['ident']] = [
					'title' => $spider['name'],
					'link' => $spider['info'] ?? '',
				];
			}
		}

		return $previous;
	}
}