<?php

namespace XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Util\Url;

/**
 * COLUMNS
 * @property int|null $result_id
 * @property string $url
 * @property string $url_hash
 * @property string $unfurl_key
 * @property string|null $title
 * @property string|null $description
 * @property string|null $image_url
 * @property string|null $favicon_url
 * @property int $last_request_date
 * @property bool $pending
 * @property int $error_count
 *
 * GETTERS
 * @property-read bool $is_recrawl
 * @property-read mixed $host
 * @property-read string $unfurl_token
 */
class UnfurlResult extends Entity
{
	/**
	 * @var int
	 */
	protected const TOKEN_TTL = 60;

	/**
	 * @return bool
	 */
	public function getIsRecrawl()
	{
		return ($this->pending && $this->requiresRecrawl());
	}

	public function getHost()
	{
		return parse_url(Url::urlToUtf8($this->url, false), PHP_URL_HOST);
	}

	public function getUnfurlToken(): string
	{
		$timestamp = \XF::$time;
		$signature = $this->getUnfurlTokenSignature($timestamp);

		return $timestamp . ':' . $signature;
	}

	public function isValidUnfurlToken(string $token): bool
	{
		if ($this->unfurl_key === '')
		{
			return false;
		}

		if (strpos($token, ':') === false)
		{
			return false;
		}

		[$timestamp, $signature] = explode(':', $token, 2);
		$timestamp = (int) $timestamp;
		if (
			$timestamp > \XF::$time
			|| \XF::$time - $timestamp > static::TOKEN_TTL
		)
		{
			return false;
		}

		return hash_equals(
			$this->getUnfurlTokenSignature($timestamp),
			$signature
		);
	}

	protected function getUnfurlTokenSignature(int $timestamp): string
	{
		return hash_hmac(
			'sha256',
			$this->result_id . ':' . $this->url_hash . ':' . $timestamp,
			$this->unfurl_key
		);
	}

	public function requiresRecrawl()
	{
		if ($this->error_count >= 3)
		{
			return false;
		}

		if ($this->last_request_date && $this->last_request_date <= \XF::$time - 30 * 86400)
		{
			return true;
		}

		return false;
	}

	public function isBasicLink()
	{
		return (!$this->pending
			&& !$this->description
			&& !$this->image_url
			&& !$this->favicon_url
		);
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_unfurl_result';
		$structure->shortName = 'XF:UnfurlResult';
		$structure->primaryKey = 'result_id';
		$structure->columns = [
			'result_id' => ['type' => self::UINT, 'nullable' => true, 'autoIncrement' => true],
			'url' => ['type' => self::STR, 'required' => true, 'maxLength' => 2500],
			'url_hash' => ['type' => self::STR, 'maxLength' => 32, 'required' => true],
			'unfurl_key' => ['type' => self::STR, 'maxLength' => 32, 'default' => ''],
			'title' => ['type' => self::STR, 'nullable' => true, 'maxLength' => 250, 'forced' => true],
			'description' => ['type' => self::STR, 'nullable' => true, 'maxLength' => 500, 'forced' => true],
			'image_url' => ['type' => self::STR, 'nullable' => true, 'maxLength' => 2500],
			'favicon_url' => ['type' => self::STR, 'nullable' => true, 'maxLength' => 2500],
			'last_request_date' => ['type' => self::UINT, 'default' => 0],
			'pending' => ['type' => self::BOOL, 'default' => false],
			'error_count' => ['type' => self::UINT, 'default' => 0],
		];
		$structure->getters = [
			'is_recrawl' => true,
			'host' => true,
			'unfurl_token' => true,
		];
		$structure->relations = [];

		return $structure;
	}
}
