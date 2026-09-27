<?php

namespace XF\Service\OAuth\AuthToken;

use XF\App;
use XF\Entity\OAuthToken;
use XF\Service\AbstractService;

class RevokerService extends AbstractService
{
	/**
	 * @var OAuthToken
	 */
	protected $authToken;

	public function __construct(App $app, OAuthToken $authToken)
	{
		parent::__construct($app);

		$this->authToken = $authToken;
	}

	public function revoke(): bool
	{
		return $this->revokeSkippingValidity();
	}

	public function revokeSkippingValidity(bool $skipValidityCheck = false): bool
	{
		if (!$skipValidityCheck && !$this->authToken->isValid())
		{
			return false;
		}

		$this->authToken->revoked_date = \XF::$time;
		$this->authToken->save();

		$this->onRevoke();

		return true;
	}

	protected function onRevoke(): void
	{
		$refreshTokens = $this->authToken->OAuthRefreshTokens;

		foreach ($refreshTokens AS $refreshToken)
		{
			if (!$refreshToken->revoked_date)
			{
				$refreshToken->revoke();
			}
		}
	}
}
