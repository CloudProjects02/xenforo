<?php

namespace SynapseThemes\YearsOfService;

use SynapseThemes\YearsOfService\Helper\ServiceHelper;
use XF\Template\Templater;

class Listener
{
	/**
	 * Template post render event listener
	 *
	 * @param string $type
	 * @param string $template
	 * @param array $params
	 * @param string $html
	 * @param Templater $templater
	 */
	public static function templatePostRender($type, $template, array &$params, &$html, Templater $templater)
	{
		// Only process public templates
		if ($type !== 'public')
		{
			return;
		}
		
		$options = \XF::options();
		
		// Check if we should display in postbit
		if ($template === 'message_macros' && !empty($options->synapseYearsOfService_displayPostbit))
		{
			self::injectServiceBadgeInPostbit($params, $html, $templater);
		}
		
		// Check if we should display in profiles
		if ($template === 'member_view' && !empty($options->synapseYearsOfService_displayProfile))
		{
			self::injectServiceBadgeInProfile($params, $html, $templater);
		}
	}
	
	/**
	 * Inject service badge data into postbit
	 *
	 * @param array $params
	 * @param string $html
	 * @param Templater $templater
	 */
	protected static function injectServiceBadgeInPostbit(array &$params, &$html, Templater $templater)
	{
		if (!isset($params['message']) || !isset($params['message']['User']))
		{
			return;
		}
		
		$user = $params['message']['User'];
		if (!$user || !$user->user_id)
		{
			return;
		}
		
		$serviceBadgeData = ServiceHelper::getServiceBadgeData($user);
		$params['serviceBadge'] = $serviceBadgeData;
	}
	
	/**
	 * Inject service badge data into profile
	 *
	 * @param array $params
	 * @param string $html
	 * @param Templater $templater
	 */
	protected static function injectServiceBadgeInProfile(array &$params, &$html, Templater $templater)
	{
		if (!isset($params['user']))
		{
			return;
		}
		
		$user = $params['user'];
		if (!$user || !$user->user_id)
		{
			return;
		}
		
		$serviceBadgeData = ServiceHelper::getServiceBadgeData($user);
		$params['serviceBadge'] = $serviceBadgeData;
	}
}