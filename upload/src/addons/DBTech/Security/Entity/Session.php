<?php

namespace DBTech\Security\Entity;

use donatj\UserAgent\Platforms;
use donatj\UserAgent\UserAgentParser;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string $session_id
 * @property int $user_id
 * @property int $start_date
 * @property int $last_activity_date
 * @property string $user_agent
 *
 * GETTERS
 * @property-read string|null $browser
 * @property-read string|null $platform
 *
 * RELATIONS
 * @property-read User|null $User
 */
class Session extends Entity
{
	/**
	 * @return string|null
	 */
	public function getBrowser(): ?string
	{
		$ua = (new UserAgentParser())
			->parse($this->user_agent)
		;
		$browser = $ua->browser();

		if ($browser !== null)
		{
			$version = $ua->browserVersion();
			if ($version !== null)
			{
				$browser .= ' ' . $version;
			}
		}

		return $browser;
	}

	/**
	 * @return string|null
	 */
	public function getPlatform(): ?string
	{
		$platform = (new UserAgentParser())
			->parse($this->user_agent)
			->platform()
		;
		if ($platform === Platforms::MACINTOSH)
		{
			$platform = 'macOS';
		}

		return $platform;
	}

	/**
	 * @param int $days
	 *
	 * @return bool
	 */
	public function isExpired(int $days = 30): bool
	{
		return $this->last_activity_date < (\XF::$time - ($days * 86400));
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_security_session';
		$structure->shortName = 'DBTech\Security:Session';
		$structure->primaryKey = 'session_id';
		$structure->columns = [
			'session_id'			=> ['type' => self::BINARY, 'required' => true],
			'user_id' 				=> ['type' => self::UINT, 'required' => true],
			'start_date' 			=> ['type' => self::UINT, 'default' => \XF::$time],
			'last_activity_date' 	=> ['type' => self::UINT, 'default' => \XF::$time],
			'user_agent' 			=> ['type' => self::BINARY, 'required' => true],
		];
		$structure->getters = [
			'browser' => true,
			'platform' => true,
		];
		$structure->relations = [
			'User' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			],
		];

		return $structure;
	}
}