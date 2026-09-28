<?php

namespace DBTech\Security\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $id
 * @property string $ip
 * @property string $date
 * @property string $request_method
 * @property string $request_uri
 * @property string $server_protocol
 * @property string $http_headers
 * @property string $user_agent
 * @property string $request_entity
 * @property string $key
 *
 * GETTERS
 * @property-read string $response
 * @property-read int $response_code
 */
class BadBehavior extends Entity
{
	/**
	 * @return string
	 * @noinspection PhpIncludeInspection
	 */
	public function getResponse(): string
	{
		if (!defined('BB2_CORE'))
		{
			define('BB2_CORE', \XF::getAddOnDirectory() . '/DBTech/Security/3rdParty/bad-behavior');
		}
		require_once BB2_CORE . '/responses.inc.php';

		$response = bb2_get_response($this->key);

		if (isset($response[0]) and $response[0] == '00000000')
		{
			return 'Unknown';
		}
		else
		{
			return $response['log'];
		}
	}

	/**
	 * @return int
	 * @noinspection PhpIncludeInspection
	 */
	public function getResponseCode(): int
	{
		if (!defined('BB2_CORE'))
		{
			define('BB2_CORE', \XF::getAddOnDirectory() . '/DBTech/Security/3rdParty/bad-behavior');
		}
		require_once BB2_CORE . '/responses.inc.php';

		$response = bb2_get_response($this->key);

		if (isset($response[0]) and $response[0] == '00000000')
		{
			return 200;
		}
		else
		{
			return $response['response'];
		}
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_security_bad_behavior';
		$structure->shortName = 'DBTech\Security:BadBehavior';
		$structure->primaryKey = 'id';
		$structure->columns = [
			'id'				=> ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'ip' 				=> ['type' => self::STR, 'required' => true],
			'date' 				=> ['type' => self::STR, 'required' => true],
			'request_method' 	=> ['type' => self::STR, 'required' => true],
			'request_uri' 		=> ['type' => self::STR, 'required' => true],
			'server_protocol' 	=> ['type' => self::STR, 'required' => true],
			'http_headers' 		=> ['type' => self::STR, 'required' => true],
			'user_agent' 		=> ['type' => self::STR, 'required' => true],
			'request_entity' 	=> ['type' => self::STR, 'required' => true],
			'key' 				=> ['type' => self::STR, 'required' => true],
		];
		$structure->getters = [
			'response' => true,
			'response_code' => true,
		];

		return $structure;
	}
}