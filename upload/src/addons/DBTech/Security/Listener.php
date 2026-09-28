<?php

namespace DBTech\Security;

use DBTech\Security\Entity\Session;
use DBTech\Security\Entity\Watcher;
use DBTech\Security\Repository\SessionRepository;
use DBTech\Security\Repository\WatcherRepository as WatcherRepo;
use DBTech\Security\XF\Entity\User;
use XF\Container;
use XF\Http\Response;
use XF\Mvc\Controller;
use XF\Mvc\Dispatcher;
use XF\Mvc\ParameterBag;
use XF\Mvc\Renderer\AbstractRenderer;
use XF\Mvc\Reply\AbstractReply;
use XF\Pub\App;
use XF\Repository\UserRepository;
use XF\Service\User\DeleteCleanUpService;
use XF\Template\Templater;

class Listener
{
	/**
	 * The product ID (in the DBTech store)
	 */
	protected static int $_productId = 345;

	/**
	 * @param App $app
	 */
	public static function appPubSetup(App $app): void
	{
		/*DBTECH_BRANDING_START*/
		// Make sure we fetch the branding array from the application
		$branding = $app->offsetExists('dbtech_branding') ? $app->dbtech_branding : [];

		// Add productid to the array
		$branding[] = self::$_productId;

		// Store the branding
		$app->dbtech_branding = $branding;
		/*DBTECH_BRANDING_END*/
	}

	/**
	 * Called during the global \XF\App object setup. This will fire regardless of the application type.
	 *
	 * @param \XF\App $app The global app object.
	 * @param array $keys An array of keys to preload from the registry.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public static function appRegistryPreload(\XF\App $app, array &$keys): void
	{
		$keys[] = 'dbtSecurityWatchers';
	}

	/**
	 * @param \XF\App $app
	 *
	 * @throws \XF\Db\Exception
	 */
	public static function appSetup(\XF\App $app): void
	{
		$container = $app->container();

		$container['dbtechSecurity.watchers'] = $app->fromRegistry(
			'dbtSecurityWatchers',
			function (Container $c)
			{
				return $c['em']->getRepository(WatcherRepo::class)->rebuildCache();
			},
			function (array $watchers): array
			{
				$em = \XF::em();

				$entities = [];
				foreach ($watchers AS $watcherId => $watcher)
				{
					if (!isset($entities[$watcher['watcher_type']]))
					{
						$entities[$watcher['watcher_type']] = [];
					}

					$watcher['rule_data'] = json_encode($watcher['rule_data']);
					$watcher['actions'] = json_encode($watcher['actions']);
					$watcher['extra_data'] = json_encode($watcher['extra_data']);

					$entities[$watcher['watcher_type']][$watcherId] = \XF::app()->em()->instantiateEntity(
						Watcher::class,
						$watcher
					);
				}

				foreach ($entities AS $watcherType => $forCollection)
				{
					$entities[$watcherType] = $em->getBasicCollection($forCollection);
				}

				return $entities;
			}
		);
	}

	/**
	 * @param App $app
	 *
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 */
	public static function appPubStartEnd(App $app): void
	{
		$sessionRepo = \XF::app()->repository(SessionRepository::class);
		$sessionId = $sessionRepo->getSessionCookie();
		if (!$sessionId)
		{
			// Create a new session
			$sessionRepo->createSession(\XF::visitor());
		}
		else
		{
			if (!\XF::app()->em()->find(Session::class, $sessionId))
			{
				$sessionRepo->deleteSessionCookie();

				$app->session()->logoutUser();

				$user = \XF::app()->repository(UserRepository::class)->getGuestUser();
				\XF::setVisitor($user);
			}
		}
	}

	/**
	 * @param Templater $templater
	 * @param string $type
	 * @param string $template
	 * @param string $name
	 * @param array $arguments
	 * @param array $globalVars
	 */
	public static function templaterMacroPreRender(
		Templater $templater,
		string &$type,
		string &$template,
		string &$name,
		array &$arguments,
		array &$globalVars
	): void
	{
		if (!empty($arguments['group']) && $arguments['group']->group_id == 'dbtech_security')
		{
			// Override template name
			$template = 'dbtech_security_option_macros';
		}
	}

	/**
	 * @param Controller $controller
	 * @param string $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public static function controllerPreDispatch(
		Controller $controller,
		string $action,
		ParameterBag $params
	): void
	{
		$app = \XF::app();
		$router = $app->router('public');

		/** @var User $visitor */
		$visitor = \XF::visitor();

		if ($visitor->user_id)
		{
			/*
			$clientIps = Ip::getClientIps();
			foreach (array_unique($clientIps) as $clientIp)
			{
				// Log IP
				$app->db()->insert('xf_dbtech_security_ip_log', [
					'user_id' => $visitor->user_id,
					'ipaddress' => $clientIp,
					'first_visit' => \XF::$time,
					'last_visit' => \XF::$time
				], false, 'last_visit = UNIX_TIMESTAMP()');
			}
			*/

			$options = \XF::options();
			$request = $app->request();
			$requestUri = $request->getFullRequestUri();

			if ($visitor->hasDbtechSecurityRequiredPasswordChange()
				&& !Util\Redirect::canBypassRedirect(
					$options->dbtechSecurityPasswordChangeForceWhitelist,
					$router->buildLink('full:account/security'),
					$requestUri
				)
			)
			{
				// We need a password change
				throw $controller->exception($controller->redirect($router->buildLink('account/security', null, [
					'_xfRedirect' => $request->getFullRequestUri(),
				]), \XF::phrase('dbtech_security_need_password_change')));
			}

			if (
				(
					$visitor->Option->dbtech_security_is_user_locked
					|| $visitor->Option->dbtech_security_is_admin_locked
				)
				&& !Util\Redirect::canBypassRedirect(
					$options->dbtechSecurityAccountLockForceWhitelist,
					$router->buildLink('full:dbtech-security/account-lock'),
					$requestUri
				)
			)
			{
				// We need a password change
				throw $controller->exception($controller->redirect($router->buildLink('dbtech-security/account-lock', null, [
					'_xfRedirect' => $request->getFullRequestUri(),
				]), \XF::phrase('dbtech_security_account_locked')));
			}
		}

		
	}

	/**
	 * @param Dispatcher $dispatcher
	 * @param string|null $content
	 * @param AbstractReply $reply
	 * @param AbstractRenderer $renderer
	 * @param Response $response
	 *
	 * @throws \Exception
	 */
	public static function dispatcherPostRender(
		Dispatcher $dispatcher,
		?string &$content,
		AbstractReply $reply,
		AbstractRenderer $renderer,
		Response $response
	): void
	{
		$app = \XF::app();
		$options = $app->options();

		/** @var User $visitor */
		$visitor = \XF::visitor();

		if ($app['app.classType'] != 'Pub'
			|| $response->contentType() != 'text/html'
			|| $dispatcher->getRequest()->isPost()
			|| in_array($reply->getControllerClass(), [
				'XF:Attachment',
				'XF:Login',
				'XF:Logout',
			])
		)
		{
			// We're not doing this
			return;
		}

		if ($options->dbtech_security_badbehavior_enabled && !WatcherRepo::noBadBehavior())
		{
			$bb2_run = true;

			if ($visitor->user_id)
			{
				if ($options->dbtech_security_badbehavior_bypass_user['enabled']
					&& (
						$options->dbtech_security_badbehavior_bypass_user['days'] < 1
						|| ((\XF::$time - $visitor->register_date / 86400) > $options->dbtech_security_badbehavior_bypass_user['days'])
					)
				)
				{
					$bb2_run = false;
				}
			}

			if ($bb2_run === true)
			{
				/** @noinspection PhpIncludeInspection */
				require_once \XF::getAddOnDirectory() . '/DBTech/Security/3rdParty/functions_badbehavior.php';

				bb2_start(bb2_read_settings());

				if (!empty($GLOBALS['bb2_javascript']))
				{
					$content = str_replace('</head>', $GLOBALS['bb2_javascript'] . '</head>', $content);
				}
			}
		}

		$watcherRepo = \XF::app()->repository(WatcherRepo::class);
		$handler = $watcherRepo->getHandler('configtamper', false);

		if ($handler)
		{
			$handler->trigger();
		}
	}

	/**
	 * @param DeleteCleanUpService $deleteService
	 * @param array $deletes
	 */
	public static function userDeleteCleanInit(DeleteCleanUpService $deleteService, array &$deletes): void
	{
		$deletes = array_merge($deletes, [
			'xf_dbtech_security_account_lock' => 'user_id = ?',
			'xf_dbtech_security_admin_strike' => 'user_id = ?',
			'xf_dbtech_security_change_log' => 'user_id = ?',
			'xf_dbtech_security_compromised_log' => 'user_id = ?',
			'xf_dbtech_security_fingerprint_log' => 'user_id = ?',
			'xf_dbtech_security_ip_log' => 'user_id = ?',
			'xf_dbtech_security_ip_verify' => 'user_id = ?',
			'xf_dbtech_security_recovery_log' => 'user_id = ?',
			'xf_dbtech_security_session' => 'user_id = ?',
			'xf_dbtech_security_watcher_log' => 'user_id = ?',
		]);
	}

	/**
	 * Called when determining which extra icons are currently in use. These icons will be included in the generated sprites.
	 *
	 * @param array $icons An array of strings containing icon classes, for example 'fas fa-acorn'.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public static function iconUsageAnalyzerExtra(array &$icons): void
	{
		$icons[] = 'fas fa-check';
		$icons[] = 'fas fa-times';
	}
}