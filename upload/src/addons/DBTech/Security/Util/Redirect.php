<?php

namespace DBTech\Security\Util;

class Redirect
{
	/**
	 * @param string $whitelist
	 * @param string $redirectUrl
	 * @param string $requestUri
	 *
	 * @return bool
	 */
	public static function canBypassRedirect(string $whitelist, string $redirectUrl, string $requestUri): bool
	{
		$app = \XF::app();
		$request = $app->request();

		if ($whitelist)
		{
			$whitelistRoutePaths = preg_split('/\s+/', trim($whitelist), -1, PREG_SPLIT_NO_EMPTY);
		}
		else
		{
			$whitelistRoutePaths = [];
		}

		$whitelistRoutePaths[] = $request->getRoutePathFromUrl($redirectUrl);
		$whitelistRoutePaths[] = $request->getRoutePathFromUrl($app->router('public')->buildLink('dbtech-security/account-lock/unlock'));
		$whitelistRoutePaths[] = $request->getRoutePathFromUrl($app->router('public')->buildLink('dbtech-security/account-lock/resend'));
		$whitelistRoutePaths[] = $request->getRoutePathFromUrl($app->router('public')->buildLink('misc/accept-privacy-policy'));
		$whitelistRoutePaths[] = $request->getRoutePathFromUrl($app->router('public')->buildLink('misc/accept-terms'));
		$whitelistRoutePaths[] = $request->getRoutePathFromUrl($app->container('privacyPolicyUrl'));
		$whitelistRoutePaths[] = $request->getRoutePathFromUrl($app->container('tosUrl'));
		$whitelistRoutePaths = array_map(function ($routePath): string
		{
			return rtrim($routePath, '/') . '/';
		}, $whitelistRoutePaths);

		$requestRoutePath = $request->getRoutePathFromUrl($requestUri);
		$requestRoutePath = rtrim($requestRoutePath, '/') . '/';

		return in_array($requestRoutePath, $whitelistRoutePaths);
	}
}