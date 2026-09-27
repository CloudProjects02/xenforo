<?php

declare(strict_types=1);

namespace DC\LoginAsUser\XF\Pub\Controller;

use DC\LoginAsUser\Entity\Session as ImpersonationLog;
use DC\LoginAsUser\Impersonation;
use DC\LoginAsUser\Service\ImpersonationService;

/**
 * "Log out" means "stop being this person". While impersonating, the person you stop being is the
 * target - so logout ends the impersonation and leaves the staff member logged in as themselves. It
 * does NOT then log them out: that would be two intents in one click.
 *
 * The interception has to happen before LoginPlugin::logoutVisitor() rather than inside it, because
 * that method is destructive to the ADMIN in three separate ways and none of them are individually
 * suppressible:
 *
 *   lastActivityUpdate()          writes xf_user.last_activity for the TARGET directly, bypassing
 *                                 every session-activity guard we install
 *   deleteVisitorRememberRecord() resolves the raw xf_user cookie -- which during impersonation is
 *                                 the ADMIN's -- and deletes that remember row
 *   clearCookies()                'user' is absent from the skip list, so the admin's remember
 *                                 cookie is expired as well
 *
 * Losing the remember record and the cookie together is what makes this more than cosmetic: it
 * removes the fallback that every other failure mode in this add-on relies on.
 *
 * Worth noting for support: LogoutController neuters its entire assert chain, so this is the one
 * public route reachable even when the impersonated account is banned, TFA-blocked or policy-locked.
 * It doubles as the emergency exit if the staff bar somehow fails to render.
 */
class LogoutController extends XFCP_LogoutController
{
	public function actionIndex()
	{
		if (!Impersonation::isActive())
		{
			return parent::actionIndex();
		}

		// Kept: a logout link is a state-changing GET target and core guards it the same way.
		$this->assertValidCsrfToken($this->filter('t', 'str'));

		/** @var ImpersonationService $service */
		$service = $this->service(ImpersonationService::class);
		$service->end(ImpersonationLog::END_LOGOUT);

		return $this->redirect(
			$this->buildLink('index'),
			\XF::phrase('dcLoginAsUser_you_are_no_longer_logged_in_as_x', [
				'name' => $service->getEndedTargetUsername(),
			])
		);
	}
}
