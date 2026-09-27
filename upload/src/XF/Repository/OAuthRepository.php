<?php

namespace XF\Repository;

use OAuth\Common\Http\Uri\Uri;
use XF\Entity\OAuthClient;
use XF\Entity\User;
use XF\Finder\ApiScopeFinder;
use XF\Finder\OAuthClientFinder;
use XF\Finder\OAuthTokenFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class OAuthRepository extends Repository
{
	public const CLIENT_TYPE_PUBLIC = 'public';
	public const CLIENT_TYPE_CONFIDENTIAL = 'confidential';

	public const RESPONSE_TYPE_CODE = 'code';

	public const CODE_CHALLENGE_METHOD_S256 = 'S256';

	public function isValidRedirectUri(OAuthClient $client, string $inputUri): bool
	{
		foreach ($client->redirect_uris AS $redirectUri)
		{
			if (!$this->matchesRedirectUri($redirectUri, $inputUri))
			{
				continue;
			}

			return true;
		}

		return false;
	}

	public function matchesRedirectUri(string $storedUri, string $inputUri): bool
	{
		$stored = new Uri($storedUri);
		$input = new Uri($inputUri);

		// RFC 8252 Section 7.3: Port-agnostic matching is only permitted for
		// loopback IP redirect URIs, not for all local hostnames
		if (
			$this->isLoopbackIpAddress($stored->getHost())
			&& $this->isLoopbackIpAddress($input->getHost())
		)
		{
			return (
				$stored->getScheme() === $input->getScheme()
				&& $stored->getRawUserInfo() === $input->getRawUserInfo()
				&& $stored->getHost() === $input->getHost()
				&& $stored->getPath() === $input->getPath()
				&& $stored->getQuery() === $input->getQuery()
				&& $stored->getFragment() === $input->getFragment()
			);
		}

		return $this->matchesRedirectUriExact($storedUri, $inputUri);
	}

	/**
	 * Matches two redirect URIs exactly, including any explicitly specified port.
	 *
	 * Unlike matchesRedirectUri(), no RFC 8252 loopback port exception is applied.
	 * This must be used when the two values are required to be identical, such as
	 * comparing the redirect_uri recorded on the authorization request with the
	 * redirect_uri supplied in the token request (RFC 6749 Section 4.1.3).
	 *
	 * @param string $storedUri
	 * @param string $inputUri
	 *
	 * @return bool
	 */
	public function matchesRedirectUriExact(string $storedUri, string $inputUri): bool
	{
		return $storedUri === $inputUri;
	}

	/**
	 * Checks whether the given host is a loopback IP address.
	 *
	 * Per RFC 8252 Section 7.3, only loopback IP addresses (127.0.0.1 for IPv4,
	 * ::1 or [::1] for IPv6) are permitted to use port-agnostic redirect URI
	 * matching. This excludes hostnames with private-use TLDs like .localhost,
	 * .local, .internal, .test, .example, or .invalid.
	 */
	protected function isLoopbackIpAddress(string $host): bool
	{
		return $host === '127.0.0.1'
			|| $host === '::1'
			|| $host === '[::1]';
	}

	public function findClientsForList(): Finder
	{
		return $this->finder(OAuthClientFinder::class)
			->setDefaultOrder('creation_date', 'desc');
	}

	public function findActiveTokensForUser(User $user): Finder
	{
		return $this->finder(OAuthTokenFinder::class)
			->with('OAuthClient')
			->where('user_id', $user->user_id)
			->order('issue_date', 'desc');
	}

	public function getConnectedClientsForUser(?User $user = null): AbstractCollection
	{
		if (!$user)
		{
			$user = \XF::visitor();
		}

		$uniqueClientIds = $this->db()->fetchAllColumn("
			SELECT DISTINCT client_id
			FROM xf_oauth_token
			WHERE user_id = ?
			AND revoked_date = 0
			ORDER BY issue_date DESC
		", $user->user_id);

		return $this->finder(OAuthClientFinder::class)
			->where('client_id', $uniqueClientIds)
			->where('active', 1)
			->order('client_id', 'desc')
			->fetch();
	}

	public function getScopesForTokens(OAuthClient $client)
	{
		$scopesFromTokens = $this->finder(OAuthTokenFinder::class)
			->where('client_id', $client->client_id)
			->where('user_id', \XF::visitor()->user_id)
			->fetch()
			->pluckNamed('scopes', 'token_id');

		$scopes = array_merge(...$scopesFromTokens);
		$scopes = array_keys($scopes);

		$apiScopes = $this->finder(ApiScopeFinder::class)
			->whereIds($scopes)
			->fetch();

		return $apiScopes;
	}

	public function revokeClientForUser(OAuthClient $client, ?User $user = null): void
	{
		if (!$user)
		{
			$user = \XF::visitor();
		}

		$db = $this->db();

		$db->update('xf_oauth_token', ['revoked_date' => \XF::$time], 'client_id = ? AND user_id = ? AND revoked_date = 0', [$client->client_id, $user->user_id]);

		$db->update(
			'xf_oauth_refresh_token',
			['revoked_date' => \XF::$time],
			'client_id = ? AND revoked_date = 0 AND token_id IN (
				SELECT token_id FROM xf_oauth_token WHERE client_id = ? AND user_id = ?
			)',
			[$client->client_id, $client->client_id, $user->user_id]
		);
	}

	public function getClientCount(): int
	{
		$clients = $this->finder(OAuthClientFinder::class)->fetch();

		$clients = $clients->filter(function (OAuthClient $client)
		{
			return $client->isUsable();
		});

		return $clients->count();
	}

	public function rebuildClientCount(): int
	{
		$cache = $this->getClientCount();
		\XF::registry()->set('oAuthClientCount', $cache);
		return $cache;
	}

	public function pruneExpiredCodes(?int $cutOff = null): int
	{
		if ($cutOff === null)
		{
			$cutOff = \XF::$time - 86400 * 14;
		}

		return $this->db()->delete('xf_oauth_code', 'expiry_date < ?', $cutOff);
	}

	public function consumeAuthCode(string $code): bool
	{
		return $this->db()->delete('xf_oauth_code', 'code = ?', $code) > 0;
	}

	public function consumeRefreshToken(string $refreshToken): bool
	{
		$consumed = $this->db()->update(
			'xf_oauth_refresh_token',
			['revoked_date' => \XF::$time],
			'refresh_token = ? AND revoked_date = 0',
			$refreshToken
		);

		return $consumed > 0;
	}

	public function pruneAuthRequests(?int $cutOff = null): int
	{
		if ($cutOff === null)
		{
			$cutOff = \XF::$time - 86400 * 30;
		}

		return $this->db()->delete('xf_oauth_request', 'request_date < ?', $cutOff);
	}
}
