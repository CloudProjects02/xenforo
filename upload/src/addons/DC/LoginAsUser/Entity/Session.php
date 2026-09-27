<?php

declare(strict_types=1);

namespace DC\LoginAsUser\Entity;

use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;

/**
 * COLUMNS
 * @property int|null $session_id
 * @property int $actor_user_id
 * @property string $actor_username
 * @property int $target_user_id
 * @property string $target_username
 * @property string $reason
 * @property string $ip_address
 * @property int $start_date
 * @property int $end_date
 * @property int $expiry_date
 * @property string $end_type
 * @property int $ended_by_user_id
 * @property string $actor_session_id
 * @property int $admin_log_id
 * @property array $suppressed
 *
 * GETTERS
 * @property-read UserEntity|null $Actor
 * @property-read bool $is_active
 * @property-read int $duration
 * @property-read int $duration_minutes
 * @property-read Phrase $end_type_text
 *
 * RELATIONS
 * @property-read UserEntity|null $User
 * @property-read UserEntity|null $Target
 * @property-read UserEntity|null $EndedBy
 */
class Session extends Entity
{
	public const END_MANUAL = 'manual';
	public const END_LOGOUT = 'logout';
	public const END_EXPIRED = 'expired';
	public const END_FORCED = 'forced';
	public const END_SUPERSEDED = 'superseded';
	public const END_STALE = 'stale';
	public const END_REVOKED = 'revoked';
	public const END_TARGET_GONE = 'target_gone';
	public const END_INVALIDATED = 'invalidated';

	/**
	 * Readability alias only. Deliberately a getter and not a second TO_ONE on the same condition:
	 * a relation would let someone ->with('Actor') alongside ->with('User') and silently join
	 * xf_user twice for one column.
	 */
	public function getActor(): ?UserEntity
	{
		return $this->User;
	}

	public function getIsActive(): bool
	{
		return $this->end_date === 0;
	}

	/**
	 * Seconds. An open session is measured to now, so the ACP active list ticks upward on refresh
	 * rather than showing a frozen zero.
	 */
	public function getDuration(): int
	{
		$end = $this->end_date ?: \XF::$time;

		return max(0, $end - $this->start_date);
	}

	/**
	 * Templates render this through core's duration() function, which needs a unit. There is no
	 * time_amount filter in XenForo.
	 */
	public function getDurationMinutes(): int
	{
		return intdiv($this->duration, 60);
	}

	public function getEndTypeText(): Phrase
	{
		// Unknown values fall through to a generic phrase rather than throwing: a row written by a
		// newer version of this add-on and read by an older one must still render.
		$known = [
			self::END_MANUAL,
			self::END_LOGOUT,
			self::END_EXPIRED,
			self::END_FORCED,
			self::END_SUPERSEDED,
			self::END_STALE,
			self::END_REVOKED,
			self::END_TARGET_GONE,
			self::END_INVALIDATED,
		];

		$type = in_array($this->end_type, $known, true) ? $this->end_type : 'unknown';

		return \XF::phrase('dcLoginAsUser_end_type.' . $type);
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_dcLoginAsUser_session';
		$structure->shortName = 'DC\LoginAsUser:Session';
		$structure->primaryKey = 'session_id';
		$structure->columns = [
			'session_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'actor_user_id' => ['type' => self::UINT, 'default' => 0],
			'actor_username' => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
			'target_user_id' => ['type' => self::UINT, 'default' => 0],
			'target_username' => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
			'reason' => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
			'ip_address' => ['type' => self::BINARY, 'maxLength' => 16, 'default' => ''],
			'start_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'end_date' => ['type' => self::UINT, 'default' => 0],
			'expiry_date' => ['type' => self::UINT, 'default' => 0],
			'end_type' => ['type' => self::STR, 'maxLength' => 25, 'default' => ''],
			'ended_by_user_id' => ['type' => self::UINT, 'default' => 0],
			'actor_session_id' => ['type' => self::BINARY, 'maxLength' => 32, 'default' => ''],
			'admin_log_id' => ['type' => self::UINT, 'default' => 0],
			'suppressed' => ['type' => self::JSON_ARRAY, 'default' => []],
		];
		$structure->getters = [
			'Actor' => true,
			'is_active' => true,
			// false = do not cache. An open row's duration is a function of \XF::$time, so a cached
			// first answer would be wrong on the second read within the same request.
			'duration' => false,
			'duration_minutes' => false,
			'end_type_text' => true,
		];
		$structure->relations = [
			// Named User, not Actor, to match XF\Entity\ModeratorLog. That parity is what lets the
			// ACP row markup be a copy of log_moderator_list's rather than a translation of it.
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [['user_id', '=', '$actor_user_id']],
				'primary' => true,
			],
			'Target' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [['user_id', '=', '$target_user_id']],
				'primary' => true,
			],
			'EndedBy' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [['user_id', '=', '$ended_by_user_id']],
				'primary' => true,
			],
		];

		return $structure;
	}
}
