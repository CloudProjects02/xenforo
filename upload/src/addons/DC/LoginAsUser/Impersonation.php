<?php

declare(strict_types=1);

namespace DC\LoginAsUser;

use XF\Session\Session as XfSession;

/**
 * The single definition of "this request is an impersonation".
 *
 * WHY A BOOT FLAG AND NOT A LAZY READ. Reading the marker means touching the session, and touching
 * the session in the wrong app means CREATING one. $container['session.public'] is a lazy factory
 * that calls Session::start() (src/XF/App.php), so a lazy ::isActive() called from job.php - which
 * builds an XF\Pub\App but never calls start() - would mint a session row per background job. Boot
 * is therefore push, not pull: the app_pub_start_end listener hands the state in once, and nothing
 * else reads storage.
 *
 * The consequence is the guard the Admin, Api and Cli apps need for free: they never fire
 * app_pub_start_end, so $booted stays false, ::isActive() is false, and every suppression override
 * falls straight through to parent. There is no app-type check to keep in sync.
 */
final class Impersonation
{
	public const SESSION_KEY = 'dcLoginAsUser';

	/**
	 * How long a password confirmation stays good for, matching XenForo's own window for sensitive
	 * ACP actions (api keys, OAuth clients).
	 *
	 * Note that changeUser() wipes passwordConfirm along with the rest of the session, so entering
	 * an account also spends the confirmation: the next impersonation is confirmed again even
	 * inside the window. That is the desirable reading of "confirm before taking over an account".
	 */
	public const PASSWORD_CONFIRM_WINDOW = 1800;

	public const SUPPRESS_ONLINE = 'online';
	public const SUPPRESS_LAST_ACTIVITY = 'last_activity';
	public const SUPPRESS_READ_MARKING = 'read_marking';
	public const SUPPRESS_ALERTS = 'alerts';
	public const SUPPRESS_CONVERSATIONS = 'conversations';
	public const SUPPRESS_IP_LOGGING = 'ip_logging';

	/**
	 * Every session key that belongs to the identity being left behind. changeUser() wipes these
	 * via regenerate(false) - but ONLY when the session already exists, and it does not exist for
	 * the whole of a browser's first public request (exists is set inside save(), which runs in
	 * XF\Pub\App::complete()). So they are cleared by hand, unconditionally.
	 *
	 * Sources: XF\Pub\App::updateUserCaches() and updateModeratorCaches(), plus the adjacent
	 * user-scoped keys read by the same staff bar and assert chain.
	 */
	public const VOLATILE_SESSION_KEYS = [
		'dismissedNotices',
		'lastNoticeUpdate',
		'promotionChecked',
		'trophyChecked',
		'previousActivity',
		'alertCountChecked',
		'reportCounts',
		'unapprovedCounts',
		'reportLastRead',
		'hasContentPendingUntil',
		'tfaLoginUserId',
		'tfaLoginDate',
		'tfaLoginRedirect',
		'preRegActionKey',
		'preRegContentUrl',
		'passwordConfirm',
		self::SESSION_KEY,
	];

	private static bool $booted = false;

	/** @var array<string, mixed>|null */
	private static ?array $state = null;

	/** @var array<string, bool>|null */
	private static ?array $suppress = null;

	/**
	 * @param array<string, mixed>|null $state
	 */
	public static function boot(?array $state): void
	{
		self::$booted = true;
		self::$state = $state;
	}

	/**
	 * Drops the in-request state after a return, so anything running later in the same request
	 * (session activity, IP logging) is attributed to the admin rather than suppressed.
	 */
	public static function reset(): void
	{
		self::$state = null;
	}

	public static function isActive(): bool
	{
		return self::$booted && self::$state !== null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function state(): ?array
	{
		return self::isActive() ? self::$state : null;
	}

	public static function adminUserId(): int
	{
		return self::isActive() ? (int) self::$state['admin_user_id'] : 0;
	}

	public static function adminUsername(): string
	{
		return self::isActive() ? (string) (self::$state['admin_username'] ?? '') : '';
	}

	public static function targetUserId(): int
	{
		return self::isActive() ? (int) self::$state['target_user_id'] : 0;
	}

	public static function sessionLogId(): int
	{
		return self::isActive() ? (int) (self::$state['session_log_id'] ?? 0) : 0;
	}

	public static function source(): string
	{
		return self::isActive() ? (string) (self::$state['source'] ?? 'public') : '';
	}

	/**
	 * The stricter of the deadline stamped at start and the one the CURRENT option implies.
	 * Lowering the option shortens live impersonations; raising it never extends one that was
	 * started under a tighter rule.
	 */
	public static function deadline(): int
	{
		if (!self::isActive())
		{
			return 0;
		}

		$stamped = (int) (self::$state['expires'] ?? 0);
		$seconds = (int) \XF::options()->dcLoginAsUser_maxDuration * 60;

		if (!$seconds)
		{
			return $stamped;
		}

		$byOption = (int) (self::$state['started'] ?? 0) + $seconds;

		return $stamped ? min($stamped, $byOption) : $byOption;
	}

	public static function isExpired(): bool
	{
		$deadline = self::deadline();

		return $deadline > 0 && $deadline <= \XF::$time;
	}

	public static function requiresPasswordConfirmation(): bool
	{
		return (bool) \XF::options()->dcLoginAsUser_requirePassword;
	}

	/**
	 * The only question the suppression overrides ask, and the reason they can each be one short
	 * guard: is this write attributable to the account being impersonated?
	 *
	 * Scoping by user id rather than by "am I impersonating" matters because a deferred job or a
	 * service acting on a third party can run inside an impersonated request, and blanking ITS
	 * writes would corrupt other users' data to hide one staff member's browsing.
	 */
	public static function suppressesFor(string $item, int $userId): bool
	{
		if (!self::isActive() || $userId <= 0 || $userId !== self::targetUserId())
		{
			return false;
		}

		if (self::$suppress === null)
		{
			$value = \XF::options()->dcLoginAsUser_suppress;
			self::$suppress = is_array($value) ? $value : [];
		}

		return !empty(self::$suppress[$item]);
	}

	/**
	 * For visitor-scoped writes that carry no explicit user id.
	 */
	public static function suppressesForVisitor(string $item): bool
	{
		return self::suppressesFor($item, (int) \XF::visitor()->user_id);
	}

	/**
	 * The resolved suppression map, snapshotted onto the audit row at open so the log can still
	 * answer "did this session leave a trace?" after the option has since been changed.
	 *
	 * @return array<string, bool>
	 */
	public static function resolveSuppressionMap(): array
	{
		$value = \XF::options()->dcLoginAsUser_suppress;
		$value = is_array($value) ? $value : [];

		$map = [];

		foreach (self::suppressionItems() AS $item)
		{
			$map[$item] = !empty($value[$item]);
		}

		return $map;
	}

	/**
	 * @return list<string>
	 */
	public static function suppressionItems(): array
	{
		return [
			self::SUPPRESS_ONLINE,
			self::SUPPRESS_LAST_ACTIVITY,
			self::SUPPRESS_READ_MARKING,
			self::SUPPRESS_ALERTS,
			self::SUPPRESS_CONVERSATIONS,
			self::SUPPRESS_IP_LOGGING,
		];
	}

	// --- marker I/O: the only methods that touch a Session object ---------------------------

	/**
	 * @return array<string, mixed>|null
	 */
	public static function readMarker(XfSession $session): ?array
	{
		$marker = $session->get(self::SESSION_KEY);

		if (!is_array($marker) || empty($marker['admin_user_id']) || empty($marker['target_user_id']))
		{
			return null;
		}

		return $marker;
	}

	/**
	 * MUST be called after changeUser(). changeUser() -> regenerate(false) -> expunge() mints a new
	 * session id and drops $data entirely, so a marker written before the switch is destroyed by
	 * the switch.
	 *
	 * @param array<string, mixed> $marker
	 */
	public static function writeMarker(XfSession $session, array $marker): void
	{
		self::clearMarker($session);

		$session->set(self::SESSION_KEY, $marker);
	}

	public static function clearMarker(XfSession $session): void
	{
		foreach (self::VOLATILE_SESSION_KEYS AS $key)
		{
			$session->remove($key);
		}
	}

	private function __construct()
	{
	}
}
