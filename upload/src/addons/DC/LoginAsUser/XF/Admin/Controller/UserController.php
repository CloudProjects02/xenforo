<?php

declare(strict_types=1);

namespace DC\LoginAsUser\XF\Admin\Controller;

use DC\LoginAsUser\Impersonation;
use DC\LoginAsUser\Service\ImpersonationService;
use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/**
 * The ACP entry point. No new route row is needed: the existing admin 'users' prefix already maps a
 * trailing segment onto an action, so users/username.123/login-as reaches actionLoginAs() exactly
 * the way users/edit does.
 *
 * The parent's preDispatchController already asserts the 'user' admin permission for any action but
 * index/find, so reaching here means the visitor can administer users; we still require the public
 * dcLoginAsUser permission on top, because administering an account and wearing it are different
 * privileges.
 */
class UserController extends XFCP_UserController
{
	public function actionLoginAs(ParameterBag $params): AbstractReply
	{
		$visitor = \XF::visitor();

		if (!$visitor->canLoginAsUsers())
		{
			return $this->noPermission();
		}

		/** @var User $user */
		$user = $this->assertRecordExists(
			User::class,
			$params->user_id,
			['Admin'],
			'requested_user_not_found'
		);

		if (!$user->canLoginAs($error))
		{
			return $this->noPermission($error);
		}

		// Both the GET (confirm) and POST (act) halves live on this one action, so the password
		// form's default "redirect back to the current URL" lands on the confirm view. No explicit
		// redirect needed here, unlike the public /start action.
		if (Impersonation::requiresPasswordConfirmation())
		{
			$this->assertPasswordVerified(Impersonation::PASSWORD_CONFIRM_WINDOW);
		}

		if ($this->isPost())
		{
			/** @var ImpersonationService $service */
			$service = $this->service(ImpersonationService::class);

			// SOURCE_ADMIN is load-bearing: it stops the service calling \XF::setVisitor(), which
			// would make XF\Admin\Controller\AbstractController::postDispatchType() attribute this
			// administrator's own admin-log row to the member they just impersonated.
			$service->start(
				$visitor,
				$user,
				$this->filter('reason', 'str'),
				ImpersonationService::SOURCE_ADMIN
			);

			return $this->redirect(
				$this->app->router('public')->buildLink('canonical:index'),
				\XF::phrase('dcLoginAsUser_you_are_now_logged_in_as_x', ['name' => $user->username])
			);
		}

		return $this->view(
			'DC\LoginAsUser:User\LoginAs',
			'dcLoginAsUser_user_confirm',
			['user' => $user]
		);
	}
}
