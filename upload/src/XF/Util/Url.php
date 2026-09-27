<?php

namespace XF\Util;

use function defined, in_array;

class Url
{
	/**
	 * Attempt to convert an URL string into its IDNA ASCII form.
	 */
	public static function urlToAscii(string $url, bool $throw = true): string
	{
		try
		{
			$parts = static::parseUrl($url);
		}
		catch (\InvalidArgumentException $e)
		{
			if ($throw)
			{
				throw $e;
			}

			return $url;
		}

		if (isset($parts['host']))
		{
			// TODO: variant checks can be removed when dropping PHP < 7.4 support
			$variant = defined('INTL_IDNA_VARIANT_UTS46')
				? INTL_IDNA_VARIANT_UTS46
				: INTL_IDNA_VARIANT_2003;
			$host = idn_to_ascii(
				$parts['host'],
				IDNA_DEFAULT,
				$variant
			);
			if ($host === false)
			{
				if ($throw)
				{
					throw new \InvalidArgumentException(
						'The URL could not be converted to ASCII'
					);
				}

				$host = $parts['host'];
			}

			$parts['host'] = $host;
		}

		return static::unparseUrl($parts);
	}

	/**
	 * Attempt to convert an URL string into its IDNA Unicode form.
	 */
	public static function urlToUtf8(string $url, bool $throw = true): string
	{
		try
		{
			$parts = static::parseUrl($url);
		}
		catch (\InvalidArgumentException $e)
		{
			if ($throw)
			{
				throw $e;
			}

			return $url;
		}

		if (isset($parts['host']))
		{
			$variant = defined('INTL_IDNA_VARIANT_UTS46')
				? INTL_IDNA_VARIANT_UTS46
				: INTL_IDNA_VARIANT_2003;
			$host = idn_to_utf8(
				$parts['host'],
				IDNA_DEFAULT,
				$variant
			);
			if ($host === false)
			{
				if ($throw)
				{
					throw new \InvalidArgumentException(
						'The URL could not be converted to UTF-8'
					);
				}

				$host = $parts['host'];
			}

			$parts['host'] = $host;
		}

		return static::unparseUrl($parts);
	}

	protected static function parseUrl(string $url): array
	{
		$parts = parse_url($url);
		if ($parts === false)
		{
			throw new \InvalidArgumentException('The URL could not be parsed');
		}

		return $parts;
	}

	protected static function unparseUrl(array $parts): string
	{
		return (isset($parts['scheme']) ? $parts['scheme'] . ':' : '')
			. (isset($parts['user']) || isset($parts['host']) ? '//' : '')
			. ($parts['user'] ?? '')
			. (isset($parts['pass']) ? ':' . $parts['pass'] : '')
			. (isset($parts['user']) ? '@' : '')
			. ($parts['host'] ?? '')
			. (isset($parts['port']) ? ':' . $parts['port'] : '')
			. ($parts['path'] ?? '')
			. (isset($parts['query']) ? '?' . $parts['query'] : '')
			. (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
	}

	/**
	 * Attempt to convert an email string into its IDNA ASCII form.
	 */
	public static function emailToAscii(string $email, bool $throw = true)
	{
		if (!preg_match('/(.+)@(.+)$/i', $email, $matches))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException(
					'The email could not be converted to ASCII'
				);
			}

			return $email;
		}

		$local = $matches[1];
		$domain = $matches[2];

		$variant = defined('INTL_IDNA_VARIANT_UTS46')
			? INTL_IDNA_VARIANT_UTS46
			: INTL_IDNA_VARIANT_2003;
		$domain = idn_to_ascii(
			$domain,
			IDNA_DEFAULT,
			$variant
		);
		if ($domain === false)
		{
			if ($throw)
			{
				throw new \InvalidArgumentException(
					'The email could not be converted to ASCII'
				);
			}

			$domain = $matches[2];
		}

		return $local . '@' . $domain;
	}

	/**
	 * Attempt to convert an email string into its IDNA Unicode form.
	 */
	public static function emailToUtf8(string $email, bool $throw = true)
	{
		if (!preg_match('/(.+)@(.+)$/i', $email, $matches))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException(
					'The email could not be converted to UTF-8'
				);
			}

			return $email;
		}

		$local = $matches[1];
		$domain = $matches[2];

		$variant = defined('INTL_IDNA_VARIANT_UTS46')
			? INTL_IDNA_VARIANT_UTS46
			: INTL_IDNA_VARIANT_2003;
		$domain = idn_to_utf8(
			$matches[2],
			IDNA_DEFAULT,
			$variant
		);
		if ($domain === false)
		{
			if ($throw)
			{
				throw new \InvalidArgumentException(
					'The email could not be converted to UTF-8'
				);
			}

			$domain = $matches[2];
		}

		return $local . '@' . $domain;
	}

	public static function hasPrivateUseTld(string $host): bool
	{
		$position = strrpos($host, '.');
		$tld = $position !== false ? substr($host, $position + 1) : $host;

		$localTlds = [
			'example',
			'internal',
			'invalid',
			'local',
			'localhost',
			'test',
		];

		return in_array($tld, $localTlds, true);
	}

	/**
	 * Returns a version of the passed in URL that is valid for use in a message or false
	 * if the URL is definitively unusable. Note that this is distinct from the URL being valid
	 * from an RFC perspective, as users may submit URLs that don't always have all components
	 * URL encoded as needed. We generally defer to the browsers to handle this for us rather
	 * than rejecting the URL.
	 *
	 * @param string $url
	 * @param string|null $allowedProtocolRegex Regular expression for allowed protocols. Defaults to https?|ftp
	 *
	 * @return false|string
	 */
	public static function getValidUrl(string $url, ?string $allowedProtocolRegex = null)
	{
		if ($allowedProtocolRegex === null)
		{
			$allowedProtocolRegex = '#^(https?|ftp)://#i';
		}

		$url = trim($url);

		if (preg_match('/proxy\.php\?\w+=(http[^&]+)&/i', $url, $match))
		{
			// proxy link of some sort, adjust to the original one
			$proxiedUrl = urldecode($match[1]);
			if (preg_match('/./su', $proxiedUrl))
			{
				$url = $proxiedUrl;
			}
		}

		if (preg_match('/^(\?|\/|#|:)/', $url))
		{
			return false;
		}

		if (strpos($url, "\n") !== false)
		{
			return false;
		}

		if (preg_match('#^(data|https?://data|javascript|about):#i', $url))
		{
			return false;
		}

		if (preg_match($allowedProtocolRegex, $url))
		{
			return $url;
		}

		return 'http://' . $url;
	}
}
