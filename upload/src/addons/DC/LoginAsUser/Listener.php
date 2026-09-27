<?php

declare(strict_types=1);

namespace DC\LoginAsUser;

use DC\LoginAsUser\Service\ImpersonationService;
use XF\App;
use XF\Mvc\Reply\AbstractReply;
use XF\Pub\App as PubApp;

class Listener
{
	/**
	 * The single point at which impersonation state enters the request.
	 *
	 * It sits at app_pub_start_end rather than in a controller because this is the last moment
	 * \XF::visitor() can be corrected before anything has read it: the dispatcher does not run until
	 * App::run(). A preDispatchType() extension runs INSIDE dispatch, after a route has been
	 * resolved and a controller instantiated as the wrong identity - and it would put our code on
	 * AbstractController, the most-extended class in any XenForo install.
	 *
	 * It is also why Impersonation has no lazy read: state is pushed in here and nowhere else, so
	 * the Admin, Api and Cli apps get their guard by never calling this.
	 *
	 * The page-cache short-circuits earlier in start() are not a hole - both require a guest, and an
	 * impersonated session always carries a userId.
	 */
	public static function appPubStartEnd(PubApp $app): void
	{
		$session = $app->session();
		$marker = Impersonation::readMarker($session);

		Impersonation::boot($marker);

		/** @var ImpersonationService $service */
		$service = $app->service(ImpersonationService::class);

		if (!$marker)
		{
			// Recovers the staff member when XenForo destroyed the impersonation session out from
			// under us - see the password-change path in App::getVisitorFromSession().
			$service->recoverOrphan();
			return;
		}

		$service->enforce();
	}

	/**
	 * Supplies $xf.dcLoginAsUser to public templates.
	 *
	 * A dedicated param rather than letting templates read $xf.session.dcLoginAsUser directly:
	 * Templater::arrayKey() is an unguarded $var[$key], so drilling into a marker that is absent
	 * would raise "Trying to access array offset on null" on every page for every normal visitor.
	 * This key always exists and always has an 'active' flag.
	 */
	public static function templaterGlobalData(App $app, array &$data, ?AbstractReply $reply): void
	{
		if (!($app instanceof PubApp))
		{
			return;
		}

		$active = Impersonation::isActive();

		$data['dcLoginAsUser'] = [
			'active' => $active,
			'adminUserId' => $active ? Impersonation::adminUserId() : 0,
			'adminUsername' => $active ? Impersonation::adminUsername() : '',
			'targetUserId' => $active ? Impersonation::targetUserId() : 0,
			'deadline' => $active ? Impersonation::deadline() : 0,
		];
	}
}
