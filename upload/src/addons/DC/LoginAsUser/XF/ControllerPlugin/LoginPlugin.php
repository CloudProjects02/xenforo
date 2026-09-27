<?php

declare(strict_types=1);

namespace DC\LoginAsUser\XF\ControllerPlugin;

use DC\LoginAsUser\Entity\Session as ImpersonationLog;
use DC\LoginAsUser\Impersonation;
use DC\LoginAsUser\Service\ImpersonationService;

class LoginPlugin extends XFCP_LoginPlugin
{
	/**
	 * "Log out" while impersonating means "stop being this person", so it ends the impersonation and
	 * leaves the staff member logged in as themselves.
	 *
	 * This guard lives on the plugin rather than only on LogoutController because the controller is
	 * not a reliable choke point. Add-ons that replace LogoutController::actionIndex() outright and
	 * call logoutVisitor() directly - never reaching parent - are common enough that one was found
	 * on the very first board this was tested against, and a controller-only guard is silently
	 * skipped in that case. Defending the destructive method itself catches every caller.
	 *
	 * And it IS destructive, in three ways that all target the ADMIN rather than the account being
	 * impersonated:
	 *
	 *   lastActivityUpdate()          writes xf_user.last_activity for the TARGET directly
	 *   deleteVisitorRememberRecord() resolves the raw xf_user cookie -- the ADMIN's -- and deletes
	 *                                 that remember row
	 *   clearCookies()                'user' is absent from the skip list, so the admin's remember
	 *                                 cookie is expired too
	 *
	 * Losing the remember record and the cookie together removes the fallback every other failure
	 * mode in this add-on relies on, which is why this is not merely cosmetic.
	 */
	public function logoutVisitor()
	{
		if (!Impersonation::isActive())
		{
			parent::logoutVisitor();
			return;
		}

		/** @var ImpersonationService $service */
		$service = $this->app->service(ImpersonationService::class);
		$service->end(ImpersonationLog::END_LOGOUT);
	}

	public function lastActivityUpdate()
	{
		if (Impersonation::suppressesForVisitor(Impersonation::SUPPRESS_LAST_ACTIVITY))
		{
			return;
		}

		parent::lastActivityUpdate();
	}
}
