<?php

namespace XF\Api\Controller;

use XF\Api\Mvc\Reply\ApiResult;
use XF\Entity\OAuthClient;
use XF\Entity\OAuthCode;
use XF\Entity\OAuthRefreshToken;
use XF\Entity\OAuthRequest;
use XF\Entity\OAuthToken;
use XF\Finder\OAuthClientFinder;
use XF\Finder\OAuthCodeFinder;
use XF\Finder\OAuthRefreshTokenFinder;
use XF\Finder\OAuthTokenFinder;
use XF\Mvc\Entity\Entity;
use XF\Repository\OAuthRepository;
use XF\Service\OAuth\AuthToken\CreatorService;
use XF\Service\OAuth\AuthToken\RevokerService as AuthTokenRevokerService;
use XF\Service\OAuth\RefreshToken\RevokerService as RefreshTokenRevokerService;

/**
 * @api-group OAuth2
 */
class OAuth2Controller extends AbstractController
{
	/**
	 * @api-desc Exchanges an authorization code or refresh token for an access token.
	 *
	 * @api-in <req> string $client_id
	 * @api-in string $client_secret Required for confidential clients.
	 * @api-in <req> string $grant_type Either 'authorization_code' or 'refresh_token'.
	 * @api-in string $code Required when grant_type is 'authorization_code'.
	 * @api-in string $refresh_token Required when grant_type is 'refresh_token'.
	 * @api-in string $code_verifier Required for public clients using PKCE with 'authorization_code' grant.
	 * @api-in string $redirect_uri Required when grant_type is 'authorization_code'.
	 *
	 * @api-out string $access_token
	 * @api-out string $refresh_token
	 * @api-out string $token_type Always 'bearer'
	 * @api-out int $expires_in Token lifetime in seconds
	 * @api-out string $scope Space-separated list of granted scopes
	 * @api-out int $issue_date
	 */
	public function actionPostToken()
	{
		$this->assertRequiredApiInput(['client_id', 'grant_type']);

		$input = $this->filter([
			'client_id' => 'str',
			'client_secret' => '?str',
			'grant_type' => '?str',
			'code' => '?str',
			'refresh_token' => '?str',
			'code_verifier' => '?str',
			'redirect_uri' => '?str',
		]);

		/** @var OAuthClient $client */
		$client = $this->finder(OAuthClientFinder::class)
			->where('client_id', $input['client_id'])
			->where('active', 1)
			->fetchOne();
		if (!$client)
		{
			return $this->apiError(\XF::phrase('provided_client_credentials_invalid'), 'invalid_client');
		}

		if ($client->client_type === OAuthRepository::CLIENT_TYPE_CONFIDENTIAL)
		{
			$this->assertRequiredApiInput(['client_secret']);
		}
		else if ($client->client_type === OAuthRepository::CLIENT_TYPE_PUBLIC
			&& $input['grant_type'] === 'authorization_code'
		)
		{
			$this->assertRequiredApiInput(['code_verifier']);
		}

		if (
			$client->client_type === OAuthRepository::CLIENT_TYPE_CONFIDENTIAL
			&& !hash_equals($client->client_secret, $input['client_secret'] ?? '')
		)
		{
			return $this->apiError(\XF::phrase('provided_client_credentials_invalid'), 'invalid_client');
		}

		switch ($input['grant_type'])
		{
			case 'authorization_code':
				if (empty($input['redirect_uri']))
				{
					return $this->apiError(\XF::phrase('valid_redirect_uri_is_required'), 'invalid_request');
				}
				return $this->grantAuthorizationCode($client, $input);
			case 'refresh_token':
				return $this->grantRefreshToken($client, $input);
			default:
				return $this->apiError(\XF::phrase('provided_grant_type_is_not_supported'), 'unsupported_grant_type');
		}
	}

	protected function grantAuthorizationCode(OAuthClient $client, $input)
	{
		$this->assertRequiredApiInput(['code']);

		$oauthRepo = $this->repository(OAuthRepository::class);

		if (!$oauthRepo->isValidRedirectUri($client, $input['redirect_uri']))
		{
			return $this->apiError(\XF::phrase('provided_redirection_uri_does_not_match_redirection_uri_registered'), 'invalid_grant');
		}

		/** @var OAuthCode $authCode */
		$authCode = $this->finder(OAuthCodeFinder::class)
			->where('code', $input['code'])
			->fetchOne();

		if (!$authCode || !$authCode->isValid())
		{
			return $this->apiError(
				\XF::phrase('provided_authorization_code_or_refresh_token_is_invalid_expired'),
				'invalid_grant'
			);
		}

		/** @var OAuthRequest $authRequest */
		$authRequest = $authCode->OAuthRequest;

		if (!$oauthRepo->matchesRedirectUriExact($authRequest->redirect_uri, $input['redirect_uri']))
		{
			return $this->apiError(
				\XF::phrase('provided_redirection_uri_does_not_match_redirection_uri_registered'),
				'invalid_grant'
			);
		}

		/** @var OAuthClient $authCodeClient */
		$authCodeClient = $authRequest->OAuthClient;

		if ($authCodeClient->client_id !== $client->client_id)
		{
			return $this->apiError(\XF::phrase('provided_client_credentials_do_not_match_client_credentials_used'), 'invalid_grant');
		}

		if ($authRequest->code_challenge)
		{
			if (empty($input['code_verifier']))
			{
				return $this->apiError(\XF::phrase('provided_code_verifier_does_not_match_code_challenge'), 'invalid_grant');
			}

			$codeVerifier = base64_encode(hash('sha256', $input['code_verifier'], true));
			$codeVerifier = strtr(rtrim($codeVerifier, '='), '+/', '-_');

			if (!hash_equals($authRequest->code_challenge, $codeVerifier))
			{
				return $this->apiError(\XF::phrase('provided_code_verifier_does_not_match_code_challenge'), 'invalid_grant');
			}
		}

		$db = \XF::db();
		$db->beginTransaction();

		if (!$oauthRepo->consumeAuthCode($input['code']))
		{
			$db->rollback();

			return $this->apiError(
				\XF::phrase('provided_authorization_code_or_refresh_token_is_invalid_expired'),
				'invalid_grant'
			);
		}

		$authTokenCreator = $this->service(CreatorService::class, $client);
		$authTokenCreator->setFromCode($authCode);

		$authToken = $authTokenCreator->save();
		$refreshToken = $authTokenCreator->getRefreshToken();

		$db->commit();

		return $this->apiResult([
			'access_token' => $authToken->token,
			'refresh_token' => $refreshToken->refresh_token,
			'token_type' => 'bearer',
			'expires_in' => OAuthToken::TOKEN_LIFETIME_SECONDS,
			'scope' => implode(' ', array_keys($authToken->scopes ?? [])),
			'issue_date' => $authToken->issue_date,
		]);
	}

	protected function grantRefreshToken(OAuthClient $client, $input)
	{
		$this->assertRequiredApiInput(['refresh_token']);

		/** @var OAuthRefreshToken $refreshToken */
		$refreshToken = $this->finder(OAuthRefreshTokenFinder::class)
			->where('refresh_token', $input['refresh_token'])
			->where('client_id', $client->client_id)
			->fetchOne();

		if (!$refreshToken || !$refreshToken->isValid())
		{
			return $this->apiError(
				\XF::phrase('provided_authorization_code_or_refresh_token_is_invalid_expired'),
				'invalid_grant'
			);
		}

		$oauthRepo = $this->repository(OAuthRepository::class);

		$db = \XF::db();
		$db->beginTransaction();

		if (!$oauthRepo->consumeRefreshToken($input['refresh_token']))
		{
			$db->rollback();

			return $this->apiError(
				\XF::phrase('provided_authorization_code_or_refresh_token_is_invalid_expired'),
				'invalid_grant'
			);
		}

		$authTokenCreator = $this->service(CreatorService::class, $client);
		$authTokenCreator->setFromRefreshToken($refreshToken);

		$newAuthToken = $authTokenCreator->save();
		$newRefreshToken = $authTokenCreator->getRefreshToken();

		$oldAuthToken = $refreshToken->OAuthToken;
		$tokenRevoker = $this->service(AuthTokenRevokerService::class, $oldAuthToken);
		$tokenRevoker->revokeSkippingValidity(true);

		$db->commit();

		return $this->apiResult([
			'access_token' => $newAuthToken->token,
			'refresh_token' => $newRefreshToken->refresh_token,
			'token_type' => 'bearer',
			'expires_in' => OAuthToken::TOKEN_LIFETIME_SECONDS,
			'scope' => implode(' ', array_keys($newAuthToken->scopes ?? [])),
			'issue_date' => $newAuthToken->issue_date,
		]);
	}

	/**
	 * @api-desc Introspects an OAuth token to determine its validity and metadata (RFC 7662).
	 *
	 * @api-in <req> string $client_id
	 * @api-in <req> string $client_secret
	 * @api-in <req> string $token
	 * @api-in string $token_type_hint Either 'access_token' or 'refresh_token'
	 *
	 * @api-out bool $active Whether the token is currently active
	 * @api-out string $scope <cond> Space-separated list of scopes
	 * @api-out string $client_id <cond> The client that the token was issued to
	 * @api-out string $username <cond> The user associated with the token
	 * @api-out string $token_type <cond> Either 'bearer' or 'refresh_token'
	 * @api-out int $exp <cond> Expiry timestamp
	 * @api-out int $iat <cond> Issue timestamp
	 * @api-out string $sub <cond> Subject (user ID)
	 * @api-out string $iss <cond> Issuer (board URL)
	 */
	public function actionPostIntrospect(): ApiResult
	{
		$this->assertRequiredApiInput(['client_id', 'client_secret', 'token']);

		$input = $this->filter([
			'client_id' => 'str',
			'client_secret' => 'str',
			'token' => 'str',
			'token_type_hint' => '?str',
		]);

		$client = $this->finder(OAuthClientFinder::class)
			->where('client_id', $input['client_id'])
			->where('active', 1)
			->fetchOne();

		if (!$client || !hash_equals($client->client_secret, $input['client_secret']))
		{
			throw $this->exception($this->apiError(\XF::phrase('provided_client_credentials_invalid'), 'invalid_client'));
		}

		$tokenValue = $input['token'];
		$tokenTypeHint = $input['token_type_hint'] ?: 'access_token';

		$accessToken = null;
		$refreshToken = null;

		if ($tokenTypeHint === 'refresh_token')
		{
			$refreshToken = $this->findRefreshToken($tokenValue, $client);
			if (!$refreshToken)
			{
				$accessToken = $this->findAccessToken($tokenValue, $client);
			}
		}
		else
		{
			$accessToken = $this->findAccessToken($tokenValue, $client);
			if (!$accessToken)
			{
				$refreshToken = $this->findRefreshToken($tokenValue, $client);
			}
		}

		if ($accessToken && $accessToken->isValid())
		{
			return $this->buildIntrospectionResponse($accessToken, 'bearer');
		}

		if ($refreshToken && $refreshToken->isValid())
		{
			return $this->buildIntrospectionResponse($refreshToken, 'refresh_token');
		}

		return $this->apiResult(['active' => false]);
	}

	protected function findAccessToken(string $tokenValue, OAuthClient $client): ?OAuthToken
	{
		return $this->finder(OAuthTokenFinder::class)
			->where('token', $tokenValue)
			->where('client_id', $client->client_id)
			->fetchOne();
	}

	protected function findRefreshToken(string $tokenValue, OAuthClient $client): ?OAuthRefreshToken
	{
		return $this->finder(OAuthRefreshTokenFinder::class)
			->where('refresh_token', $tokenValue)
			->where('client_id', $client->client_id)
			->fetchOne();
	}

	protected function buildIntrospectionResponse(Entity $token, string $tokenType): ApiResult
	{
		/** @var OAuthToken|OAuthRefreshToken $token */

		// Refresh tokens don't have user/scope data directly - get from parent access token
		if ($token instanceof OAuthRefreshToken)
		{
			$accessToken = $token->OAuthToken;
			$user = $accessToken ? $accessToken->User : null;
			$scopes = $accessToken ? ($accessToken->scopes ?? []) : [];
			$userId = $accessToken ? $accessToken->user_id : 0;
		}
		else
		{
			$user = $token->User;
			$scopes = $token->scopes ?? [];
			$userId = $token->user_id;
		}

		$username = $user ? ($user->email ?: $user->username) : '';

		return $this->apiResult([
			'active' => true,
			'scope' => implode(' ', $scopes),
			'client_id' => $token->client_id,
			'username' => $username,
			'token_type' => $tokenType,
			'exp' => $token->expiry_date,
			'iat' => $token->issue_date,
			'sub' => (string) $userId,
			'iss' => \XF::options()->boardUrl,
		]);
	}

	/**
	 * @api-desc Gets information about an OAuth token.
	 *
	 * @api-in <req> string $client_id
	 * @api-in <req> string $client_secret
	 * @api-in <req> string $token
	 *
	 * @api-out int $user_id
	 * @api-out object $scope Key-value pairs of granted scopes
	 * @api-out int $expires_in Remaining lifetime in seconds
	 * @api-out int $issue_date
	 *
	 * @deprecated Use POST /api/oauth2/introspect instead (RFC 7662)
	 */
	public function actionGetToken(): ApiResult
	{
		$this->assertRequiredApiInput(['client_id', 'client_secret', 'token']);

		$input = $this->filter([
			'client_id' => 'str',
			'client_secret' => 'str',
			'token' => 'str',
		]);

		$client = $this->finder(OAuthClientFinder::class)
			->where('client_id', $input['client_id'])
			->where('active', 1)
			->fetchOne();

		if (!$client || !hash_equals($client->client_secret, $input['client_secret']))
		{
			throw $this->exception($this->apiError(\XF::phrase('provided_client_credentials_invalid'), 'invalid_client'));
		}

		/** @var OAuthToken $token */
		$token = $this->finder(OAuthTokenFinder::class)
			->where('token', $input['token'])
			->where('client_id', $client->client_id)
			->fetchOne();

		if (!$token)
		{
			throw $this->exception($this->notFound());
		}

		return $this->apiResult([
			'user_id' => $token->user_id,
			'scope' => $token->scopes,
			'expires_in' => max(0, $token->expiry_date - \XF::$time),
			'issue_date' => $token->issue_date,
		]);
	}

	/**
	 * @api-desc Revokes an access token or refresh token.
	 *
	 * @api-in <req> string $client_id
	 * @api-in <req> string $client_secret
	 * @api-in <req> string $token
	 * @api-in string $token_type_hint Defaults to 'access_token' but can be 'refresh_token' to revoke a refresh token.
	 *
	 * @api-out true $success
	 */
	public function actionPostRevoke()
	{
		$this->assertRequiredApiInput(['client_id', 'client_secret', 'token']);

		$input = $this->filter([
			'client_id' => 'str',
			'client_secret' => 'str',
			'token' => 'str',
			'token_type_hint' => '?str',
		]);

		$client = $this->finder(OAuthClientFinder::class)
			->where('client_id', $input['client_id'])
			->where('client_secret', $input['client_secret'])
			->where('active', 1)
			->fetchOne();
		if (!$client || !hash_equals($client->client_secret, $input['client_secret']))
		{
			return $this->apiError(\XF::phrase('provided_client_credentials_invalid'), 'invalid_client');
		}

		switch ($input['token_type_hint'])
		{
			case 'refresh_token':
				return $this->revokeRefreshToken($client, $input);
			case 'access_token':
			default:
				return $this->revokeAuthToken($client, $input);
		}
	}

	protected function revokeRefreshToken(OAuthClient $client, array $input)
	{
		$refreshToken = $this->finder(OAuthRefreshTokenFinder::class)
			->where('refresh_token', $input['token'])
			->where('client_id', $client->client_id)
			->fetchOne();

		if ($refreshToken)
		{
			$tokenRevoker = $this->service(RefreshTokenRevokerService::class, $refreshToken);
			$tokenRevoker->revoke();
		}

		return $this->apiSuccess();
	}

	protected function revokeAuthToken(OAuthClient $client, array $input)
	{
		$authToken = $this->finder(OAuthTokenFinder::class)
			->where('token', $input['token'])
			->where('client_id', $client->client_id)
			->fetchOne();

		if ($authToken)
		{
			$tokenRevoker = $this->service(AuthTokenRevokerService::class, $authToken);
			$tokenRevoker->revoke();
		}

		return $this->apiSuccess();
	}

	public function allowUnauthenticatedRequest($action): bool
	{
		return true;
	}
}
