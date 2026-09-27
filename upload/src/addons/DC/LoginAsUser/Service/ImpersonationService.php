<?php

declare(strict_types=1);

namespace DC\LoginAsUser\Service;

use DC\LoginAsUser\Entity\Session as ImpersonationLog;
use DC\LoginAsUser\Impersonation;
use DC\LoginAsUser\Repository\SessionRepository;
use XF\Entity\User;
use XF\Repository\UserRepository;
use XF\Service\AbstractService;
use XF\Session\Session as XfSession;
use XF\Util\Ip;

/**
 * Every identity switch in this add-on goes through here.
 *
 * The single most important rule in this file: we NEVER call
 * XF\ControllerPlugin\LoginPlugin::completeLogin(). DC/Multisite overrides it to mint a
 * parent-domain xf_user remember cookie, which for an impersonation would issue a cross-domain,
 * up-to-30-day credential for the impersonated account that outlives the impersonation itself.
 * We use the primitives directly - changeUser() + setVisitor() - exactly as
 * XF\Admin\Controller\LoginController::completeLogin() does.
 */
class ImpersonationService extends AbstractService
{
	public const SOURCE_PUBLIC = 'public';
	public const SOURCE_ADMIN = 'admin';

	protected ?string $endedTargetUsername = null;

	/**
	 * Start impersonating $target as $actor.
	 *
	 * $source matters: in the ACP we must NOT call \XF::setVisitor(), because
	 * XF\Admin\Controller\AbstractController::postDispatchType() writes its automatic admin-log row
	 * from \XF::visitor()->user_id AFTER dispatch - switching the visitor there would attribute the
	 * administrator's own action to the person they just impersonated.
	 */
	public function start(User $actor, User $target, string $reason, string $source = self::SOURCE_PUBLIC): ImpersonationLog
	{
		$sessionRepo = $this->getSessionRepo();
		$session = $this->publicSession();

		$reason = trim($reason);
		if (strlen($reason) > 255)
		{
			$reason = substr($reason, 0, 255);
		}

		$ip = $this->app->request()->getIp(false) ?: '';

		$this->ensureSessionHasOwnerIp($session);

		$log = $sessionRepo->openSession(
			$actor,
			$target,
			$reason,
			$ip ? Ip::stringToBinary($ip) : '',
			Impersonation::resolveSuppressionMap()
		);

		// Destroys the session row and mints a new id, taking every session key with it. Anything
		// we want to survive has to be written AFTER this line.
		$session->changeUser($target);

		Impersonation::writeMarker($session, [
			'admin_user_id' => $actor->user_id,
			'admin_username' => $actor->username,
			'target_user_id' => $target->user_id,
			'started' => \XF::$time,
			'expires' => $log->expiry_date,
			'session_log_id' => $log->session_id,
			'source' => $source,
		]);

		// Explicit in both apps. In Pub these are belt-and-braces (XF\Pub\App::complete() would do
		// them); in Admin they are mandatory, because XF\Admin\App::complete() only saves the ADMIN
		// session and never touches session.public.
		$session->save();
		$session->applyToResponse($this->app->response());

		// After save(), so the id we record is guaranteed to exist in storage and force-end can
		// never target a phantom row.
		$log->fastUpdate('actor_session_id', $session->getSessionId());

		$adminLogId = $sessionRepo->logAdminAction($actor->user_id, 'start', [
			'session_id' => $log->session_id,
			'target_user_id' => $target->user_id,
			'target_username' => $target->username,
			'reason' => $reason,
			'expiry_date' => $log->expiry_date,
			'source' => $source,
		]);

		if ($adminLogId)
		{
			$log->fastUpdate('admin_log_id', $adminLogId);
		}

		$this->clearStaleParentDomainSessionCookie();

		if ($source === self::SOURCE_PUBLIC)
		{
			\XF::setVisitor($target);
			Impersonation::boot(Impersonation::readMarker($session));
		}

		return $log;
	}

	/**
	 * End the current impersonation and put the staff member back in their own account.
	 *
	 * Deliberately does NOT re-check the permission. If an administrator's permission is revoked
	 * while they are mid-impersonation they must still be able to get out; the only gate on
	 * returning is that a marker exists.
	 */
	public function end(string $endType = ImpersonationLog::END_MANUAL): bool
	{
		$state = Impersonation::state();

		if (!$state)
		{
			return false;
		}

		$session = $this->publicSession();
		$sessionRepo = $this->getSessionRepo();

		$log = $this->findLogForState($state);
		$this->endedTargetUsername = $log ? $log->target_username : (string) \XF::visitor()->username;

		/** @var UserRepository $userRepo */
		$userRepo = $this->repository(UserRepository::class);
		$admin = $userRepo->getVisitor((int) $state['admin_user_id']);

		if (!$admin->user_id)
		{
			// The administrator's own account is gone. There is nobody to hand back to, so end the
			// session rather than leaving the staff member wearing someone else's identity.
			$session->logoutUser();
			Impersonation::clearMarker($session);
			$session->save();
			$session->applyToResponse($this->app->response());
			Impersonation::reset();

			if ($log)
			{
				$sessionRepo->closeSession($log, ImpersonationLog::END_INVALIDATED);
			}

			return true;
		}

		$session->changeUser($admin);
		Impersonation::clearMarker($session);
		$session->save();
		$session->applyToResponse($this->app->response());

		Impersonation::reset();
		\XF::setVisitor($admin);

		if ($log)
		{
			$sessionRepo->closeSession($log, $endType);
			$sessionRepo->logAdminAction($admin->user_id, $endType, [
				'session_id' => $log->session_id,
				'target_user_id' => $log->target_user_id,
				'target_username' => $log->target_username,
			]);
		}

		return true;
	}

	public function getEndedTargetUsername(): string
	{
		return (string) $this->endedTargetUsername;
	}

	/**
	 * Runs once per impersonated public request, from the app_pub_start_end listener. Each branch
	 * ends the impersonation with a distinct end type and lets the request continue as the admin.
	 */
	public function enforce(): void
	{
		$state = Impersonation::state();

		if (!$state)
		{
			return;
		}

		$visitor = \XF::visitor();

		// The impersonated account was deleted mid-session. getVisitorFromSession() leaves the
		// session alone in this case (the passwordDate branch is skipped for a 0 user id), so the
		// marker survives pointing at a dead row and only we can notice.
		if (!$visitor->user_id)
		{
			$this->end(ImpersonationLog::END_TARGET_GONE);
			return;
		}

		/** @var UserRepository $userRepo */
		$userRepo = $this->repository(UserRepository::class);
		$admin = $userRepo->getVisitor((int) $state['admin_user_id']);

		if (!$admin->user_id)
		{
			$this->end(ImpersonationLog::END_INVALIDATED);
			return;
		}

		if (!$admin->hasPermission('dcLoginAsUser', 'login'))
		{
			$this->end(ImpersonationLog::END_REVOKED);
			return;
		}

		if (Impersonation::isExpired())
		{
			$this->end(ImpersonationLog::END_EXPIRED);
		}
	}

	/**
	 * Recovers a staff member whom XenForo stranded.
	 *
	 * If the impersonated account changes its password mid-session, App::getVisitorFromSession()
	 * detects the passwordDate mismatch and calls Session::logoutUser(), which regenerates the
	 * session and destroys our marker. The staff member becomes a guest - and STAYS one, because
	 * loginFromRememberCookie() only runs when the session does not exist, and from the next
	 * request onward the new guest session does exist. They are left holding a valid xf_user cookie
	 * that will never be consulted again.
	 *
	 * The gate is nearly free: a remember cookie AND a guest visitor is a combination XF cannot
	 * normally produce, because loginFromRememberCookie() would have consumed the cookie. Virtually
	 * every request exits on the first condition.
	 */
	public function recoverOrphan(): void
	{
		$request = $this->app->request();

		if (\XF::visitor()->user_id || !$request->getCookie('user'))
		{
			return;
		}

		// Still the PRE-regeneration id on this request: the new one only reaches the browser via
		// the response, so this is exactly the value recorded on the audit row.
		$staleSessionId = $request->getCookie('session');

		if (!$staleSessionId || !is_string($staleSessionId))
		{
			return;
		}

		$sessionRepo = $this->getSessionRepo();
		$log = $sessionRepo->findOpenSessionBySessionId($staleSessionId);

		if (!$log)
		{
			return;
		}

		/** @var UserRepository $userRepo */
		$userRepo = $this->repository(UserRepository::class);
		$admin = $userRepo->getVisitor($log->actor_user_id);

		if ($admin->user_id)
		{
			$session = $this->publicSession();
			$session->changeUser($admin);
			Impersonation::clearMarker($session);
			$session->save();
			$session->applyToResponse($this->app->response());
			\XF::setVisitor($admin);
		}

		$sessionRepo->closeSession($log, ImpersonationLog::END_INVALIDATED);
	}

	/**
	 * @param array<string, mixed> $state
	 */
	protected function findLogForState(array $state): ?ImpersonationLog
	{
		$logId = (int) ($state['session_log_id'] ?? 0);

		if (!$logId)
		{
			return null;
		}

		/** @var ImpersonationLog|null $log */
		$log = $this->em()->find(ImpersonationLog::class, $logId);

		return $log;
	}

	/**
	 * Expires a legacy parent-domain xf_session cookie.
	 *
	 * DC/Multisite used to write one, and its own comment records that it shadowed the host-only
	 * cookie XenForo actually relies on. A browser carrying a stale one sends two xf_session values,
	 * oldest first, and PHP keeps the first - so the stale value wins on every request. The staff
	 * member never notices, because the xf_user remember cookie logs them back in each time, but an
	 * impersonation lives only in the session and is lost on the very next request.
	 *
	 * Sent with setcookie() rather than through the Response: XF\Http\Response keys cookies by name,
	 * and XF\Pub\App::complete() re-applies the host-only xf_session afterwards, which would silently
	 * replace this expiry before it ever reached the browser.
	 *
	 * Resolved by duck-typing so this add-on carries no dependency on DC/Multisite.
	 */
	protected function clearStaleParentDomainSessionCookie(): void
	{
		$domain = $this->resolveParentCookieDomain();

		if ($domain === null)
		{
			return;
		}

		$request = $this->app->request();

		setcookie($request->getCookiePrefix() . 'session', '', [
			'expires' => \XF::$time - 86400 * 365,
			'path' => '/',
			'domain' => $domain,
			'secure' => $request->isSecure(),
			'httponly' => true,
			'samesite' => 'Lax',
		]);
	}

	/**
	 * Not gated on admin_mode: the ACP entry point writes the same public session cookie on the same
	 * host, and DC/Multisite resolves current_domain_id during app_setup, before app_admin_setup.
	 */
	protected function resolveParentCookieDomain(): ?string
	{
		$app = $this->app;

		if (!$app->offsetExists('current_domain_id'))
		{
			return null;
		}

		if (!\XF::isAddOnActive('DC/Multisite'))
		{
			return null;
		}

		try
		{
			$main = $app->repository('DC\Multisite:Multisite')->getCurrentMainDomain();
		}
		catch (\Throwable $e)
		{
			return null;
		}

		return $main ? '.' . $main : null;
	}

	/**
	 * Repairs a session that was created without an IP.
	 *
	 * Session::start() packs the owner IP with Ip::stringToBinary() -- but only when it is
	 * non-empty; with no IP at all it stores a bare '' instead. Session::regenerate() then feeds
	 * that '' straight back through Ip::binaryToString(), which throws "Invalid binary IP". Since
	 * changeUser() goes through regenerate(), impersonating from such a session is a hard 500.
	 *
	 * Sessions created by a browser always carry a packed address, so this only ever fires for a
	 * session minted programmatically -- which is precisely how an authenticated end-to-end test is
	 * driven. Writing the current request's IP in is also strictly a tightening: confirmOwnership()
	 * treats an empty _ip as "no IP to check against".
	 */
	protected function ensureSessionHasOwnerIp(XfSession $session): void
	{
		if ($session->get('_ip'))
		{
			return;
		}

		$ip = $this->app->request()->getIp(false);

		if ($ip !== '' && $ip !== null)
		{
			$session->set('_ip', Ip::stringToBinary($ip, false) ?: '');
		}
	}

	/**
	 * The PUBLIC session, whichever app we are in. In the ACP $this->app->session() is the admin
	 * session, which is a different cookie, a different table and a different user entirely.
	 */
	protected function publicSession(): XfSession
	{
		return $this->app['session.public'];
	}

	protected function getSessionRepo(): SessionRepository
	{
		return $this->repository(SessionRepository::class);
	}
}
