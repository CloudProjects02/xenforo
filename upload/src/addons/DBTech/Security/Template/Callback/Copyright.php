<?php

namespace DBTech\Security\Template\Callback;

use XF\App;

class Copyright
{
	/**
	 * @return string
	 */
	public static function getCopyrightText(): string
	{
		/** @var App $app */
		$app = \XF::app();

		$branding = $app->offsetExists('dbtech_branding') ? $app->dbtech_branding : [];

		if (!count($branding) or !is_array($branding))
		{
			// We had nothing left, another DBTech mod would have done it
			return '';
		}

		$brandingVariables = [
			'utm_source' 		=> str_replace('www.', '', htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'CLI')),
			'utm_content' 		=> 'footer',
		];

		// Create this long string
		$html = '<div>
			Parts of this site powered by add-ons from DragonByte&#8482;
			&copy;2011-' . date('Y') . ' DragonByte Technologies
		</div>';

		// Make sure we null this out (container may be locked during template rendering)
		try
		{
			$app->dbtech_branding = [];
		}
		catch (\LogicException $e) {}

		return $html;
	}
}