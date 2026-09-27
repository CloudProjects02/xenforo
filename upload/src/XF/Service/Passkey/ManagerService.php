<?php

namespace XF\Service\Passkey;

use lbuchs\WebAuthn\WebAuthn;
use XF\App;
use XF\Entity\Passkey;
use XF\Entity\User;
use XF\Finder\PasskeyFinder;
use XF\Http\Request;
use XF\Service\AbstractService;
use XF\Service\LoginLimitTrait;
use XF\Session\Session;
use XF\Util\Ip;

use function strlen;

class ManagerService extends AbstractService
{
	use LoginLimitTrait;

	protected $challenge;
	protected $challengeTime;

	/**
	 * @var Passkey
	 */
	protected $passkey;

	/**
	 * @var array
	 */
	protected $payload;

	protected $recordAttempts = true;

	public function __construct(App $app, ?Session $session = null)
	{
		parent::__construct($app);

		if ($session)
		{
			$this->setupFromSession($session);
		}
		else
		{
			$this->generateState();
		}
	}

	public function getChallenge(): string
	{
		return $this->challenge;
	}

	public function getPasskeyUser(): ?User
	{
		if (!$this->passkey)
		{
			throw new \LogicException('Passkey not validated');
		}

		return $this->passkey->User;
	}

	public function setRecordAttempts($value)
	{
		$this->recordAttempts = (bool) $value;
	}

	public function getRecordAttempts()
	{
		return $this->recordAttempts;
	}

	public function isLoginLimited($ip, &$limitType = null): bool
	{
		if (!$ip)
		{
			return false;
		}

		$login = null;
		if ($this->passkey)
		{
			$user = $this->passkey->User;
			if ($user)
			{
				$login = $user->username;
			}
		}

		if ($this->hasTooManyLoginAttempts($ip, $login))
		{
			$limitType = $this->app->options()->loginLimit;
			return true;
		}

		return false;
	}

	public function hasTooManyLoginAttempts($ip, $login = null)
	{
		return $this->checkTooManyLoginAttempts($ip, $login);
	}

	public function getAttemptLimits()
	{
		return $this->getLoginAttemptLimits();
	}

	public function generateState(): void
	{
		$this->challenge = \XF::generateRandomString(128);
		$this->challengeTime = \XF::$time;
	}

	public function setupFromSession(Session $session): void
	{
		$values = $session->get('passkeyChallenge');
		if ($values)
		{
			$this->challenge = $values['challenge'];
			$this->challengeTime = $values['time'];
		}
		else
		{
			$this->generateState();
		}
	}

	public function saveStateToSession(Session $session): void
	{
		$session->set('passkeyChallenge', [
			'challenge' => $this->challenge,
			'time' => $this->challengeTime,
		]);
	}

	public function clearStateFromSession(Session $session): void
	{
		$session->remove('passkeyChallenge');
	}

	public function validate(Request $request, &$error = null): bool
	{
		return $this->validateWithUser($request, $error);
	}

	public function validateWithUser(Request $request, &$error = null, ?User $user = null): bool
	{
		if (!$this->verifyRequest($request, $error))
		{
			return false;
		}

		$payload = $this->payload;

		$clientDataJSON = base64_decode($payload['clientDataJSON']);
		$authenticatorData = base64_decode($payload['authenticatorData']);
		if (!$clientDataJSON || !$authenticatorData)
		{
			$error = \XF::phrase('something_went_wrong_please_try_again');
			return false;
		}

		$clientData = @json_decode($clientDataJSON);
		if (!$clientData || !isset($clientData->origin))
		{
			$error = \XF::phrase('something_went_wrong_please_try_again');
			return false;
		}

		if (!$this->validateOrigin($clientData->origin))
		{
			$error = \XF::phrase('something_went_wrong_please_try_again');
			return false;
		}

		$credentialId = $payload['id'];
		$signature = $payload['signature'];

		try
		{
			$webAuthn = $this->getWebAuthnClass();

			$finder = \XF::app()->finder(PasskeyFinder::class)
				->with('User', true)
				->where('credential_id', $credentialId);

			if ($user !== null)
			{
				$finder->where('user_id', $user->user_id);
			}

			$this->passkey = $finder->fetchOne();

			if (!$this->passkey)
			{
				// Record failed attempt even when passkey not found (IP-based rate limiting)
				$this->recordFailedAttempt($request->getIp());
				$error = \XF::phrase('given_passkey_or_security_key_could_not_be_verified');
				return false;
			}

			$isValid = $webAuthn->processGet(
				$clientDataJSON,
				$authenticatorData,
				base64_decode($signature),
				$this->passkey->credential_public_key,
				$this->challenge
			);

			if (!$isValid)
			{
				$this->recordFailedAttempt($request->getIp());
				$error = \XF::phrase('given_passkey_or_security_key_could_not_be_verified');
				return false;
			}

			$newSignatureCounter = 0;
			if (strlen($authenticatorData) >= 37)
			{
				$counterBytes = substr($authenticatorData, 33, 4);
				$unpacked = unpack('N', $counterBytes);
				$newSignatureCounter = $unpacked[1] ?? 0;
			}

			$validationResult = $this->validateSignatureCounter(
				$this->passkey->signature_counter,
				$newSignatureCounter,
				$this->passkey
			);

			if (!$validationResult)
			{
				$this->recordFailedAttempt($request->getIp());
				$error = \XF::phrase('given_passkey_or_security_key_could_not_be_verified');
				return false;
			}

			$this->updatePasskeyLastUse($this->passkey, $request, $newSignatureCounter);

			return true;
		}
		catch (\Exception $e)
		{
			$this->logPasskeyError('Passkey validation failed', $e);

			// If we found a passkey, record this as a failed attempt
			if ($this->passkey)
			{
				$this->recordFailedAttempt($request->getIp());
			}

			$error = \XF::phrase('given_passkey_or_security_key_could_not_be_verified');
			return false;
		}
	}

	public function create(Request $request, &$error = null)
	{
		if (!$this->verifyRequest($request, $error))
		{
			return false;
		}

		$name = $request->filter('passkey_name', '?str') ?: null;
		$userVerification = $request->filter('user_verification', '?str') ?: 'none';

		$payload = $this->payload;

		$clientDataJSON = base64_decode($payload['clientDataJSON']);
		$attestationObject = base64_decode($payload['attestationObject']);
		if (!$clientDataJSON || !$attestationObject)
		{
			$error = \XF::phrase('something_went_wrong_please_try_again');
			return false;
		}

		$clientData = @json_decode($clientDataJSON);
		if (!$clientData || !isset($clientData->origin))
		{
			$error = \XF::phrase('something_went_wrong_please_try_again');
			return false;
		}

		if (!$this->validateOrigin($clientData->origin))
		{
			$error = \XF::phrase('something_went_wrong_please_try_again');
			return false;
		}

		$webAuthn = $this->getWebAuthnClass();

		try
		{
			$results = $webAuthn->processCreate(
				$clientDataJSON,
				$attestationObject,
				$this->challenge,
				($userVerification === 'required'),
				true,
				false
			);

			$encodedCredentialId = base64_encode($results->credentialId);
			$aaguid = bin2hex($results->AAGUID);

			$aaguidData = $this->app->data(\XF\Data\WebAuthn::class);
			$aaguidName = $aaguidData->getDataForAAGUID($aaguid)['name'] ?? null;

			$visitor = \XF::visitor();
			$visitorLang = $this->app->userLanguage($visitor);

			$fallbackName = \XF::phrase('passkey_date_x', [
				'date' => $visitorLang->dateTime(\XF::$time),
			]);

			/** @var Passkey $passkey */
			$passkey = \XF::em()->create(Passkey::class);
			$passkey->bulkSet([
				'user_id' => $visitor->user_id,
				'credential_id' => $encodedCredentialId,
				'credential_public_key' => $results->credentialPublicKey,
				'create_date' => \XF::$time,
				'create_ip_address' => Ip::stringToBinary($request->getIp()),
				'name' => $aaguidName ?? $name ?? $fallbackName,
				'aaguid' => $aaguid,
				'signature_counter' => 0,
			]);
			$passkey->save();
		}
		catch (\Exception $e)
		{
			$this->logPasskeyError('Passkey registration failed', $e);

			$error = \XF::phrase('unexpected_error_occurred');
			return false;
		}

		return true;
	}

	public function recordFailedAttempt($ip, $login = null)
	{
		if ($login === null && $this->passkey)
		{
			$user = $this->passkey->User;
			if ($user)
			{
				$login = $user->username;
			}
		}

		if ($login === null)
		{
			$login = 'passkey:unknown';
		}

		$this->recordLoginAttempt($login, $ip);
	}

	public function clearFailedAttempts($ip)
	{
		if (!$this->passkey || !$ip)
		{
			return;
		}

		$user = $this->passkey->User;
		if (!$user)
		{
			return;
		}

		$this->clearLoginAttempts($user->username, $ip);
	}

	protected function verifyRequest(Request $request, &$error = null): bool
	{
		if (!$this->challengeTime || \XF::$time - $this->challengeTime > 900)
		{
			$error = \XF::phrase('page_no_longer_available_back_try_again');
			return false;
		}

		if (!$this->challenge || $this->challenge !== $request->filter('webauthn_challenge', 'str'))
		{
			$error = \XF::phrase('something_went_wrong_please_try_again');
			return false;
		}

		$this->payload = $request->filter('webauthn_payload', 'json-array');
		if (!$this->payload)
		{
			$error = \XF::phrase('something_went_wrong_please_try_again');
			return false;
		}

		return true;
	}

	protected function validateSignatureCounter(int $storedCounter, int $newCounter, Passkey $passkey): bool
	{
		if ($storedCounter === 0 && $newCounter === 0)
		{
			return true;
		}

		if ($storedCounter === 0 && $newCounter > 0)
		{
			return true;
		}

		if ($storedCounter > 0 && $newCounter === 0)
		{
			\XF::logError(sprintf(
				'Passkey signature counter reset detected for user %d (passkey %d). Counter went from %d to 0. This may indicate authenticator replacement or reset.',
				$passkey->user_id,
				$passkey->passkey_id,
				$storedCounter
			));

			return false;
		}

		if ($newCounter <= $storedCounter)
		{
			\XF::logError(sprintf(
				'Passkey signature counter validation failed for user %d (passkey %d). Expected counter > %d, got %d. This may indicate a cloned authenticator.',
				$passkey->user_id,
				$passkey->passkey_id,
				$storedCounter,
				$newCounter
			));

			return false;
		}

		return true;
	}

	protected function updatePasskeyLastUse(Passkey $passkey, Request $request, int $signatureCounter = 0): bool
	{
		$passkey->last_use_date = \XF::$time;
		$passkey->last_use_ip_address = Ip::stringToBinary($request->getIp());
		$passkey->signature_counter = $signatureCounter;
		$passkey->save();

		return true;
	}

	protected function logPasskeyError(string $message, \Exception $e): void
	{
		$options = \XF::options();
		$currentHost = $this->app->request()->getHost();
		$configuredRpId = parse_url($options->boardUrl, PHP_URL_HOST);

		if (str_contains($e->getMessage(), 'invalid rpId hash'))
		{
			\XF::logError(sprintf(
				"%s: %s - Current host '%s' does not match Board URL '%s' (rpId: %s). Update Board URL option to match.",
				$message,
				$e->getMessage(),
				$currentHost,
				$options->boardUrl,
				$configuredRpId ?: 'invalid'
			));
		}
		else
		{
			\XF::logError(sprintf(
				"%s: %s - Host: %s, Board URL: %s, rpId: %s, Secure: %s",
				$message,
				$e->getMessage(),
				$currentHost,
				$options->boardUrl,
				$configuredRpId ?: 'invalid',
				$this->app->request()->isSecure() ? 'yes' : 'no'
			));
		}
	}

	protected function getAllowedOrigins(): array
	{
		$options = $this->app->options();
		$boardUrl = $options->boardUrl;

		$parsedUrl = parse_url($boardUrl);
		if (!$parsedUrl || !isset($parsedUrl['scheme']) || !isset($parsedUrl['host']))
		{
			return [$boardUrl];
		}

		$origin = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
		if (isset($parsedUrl['port']))
		{
			$origin .= ':' . $parsedUrl['port'];
		}

		return [$origin];
	}

	protected function validateOrigin(string $origin): bool
	{
		$allowedOrigins = $this->getAllowedOrigins();
		$origin = rtrim($origin, '/');

		foreach ($allowedOrigins AS $allowedOrigin)
		{
			$allowedOrigin = rtrim($allowedOrigin, '/');
			if ($origin === $allowedOrigin)
			{
				return true;
			}
		}

		\XF::logError(sprintf(
			'Passkey origin validation failed. Origin "%s" not in allowed list: %s',
			$origin,
			implode(', ', $allowedOrigins)
		));

		return false;
	}

	protected function getWebAuthnClass(): WebAuthn
	{
		$options = \XF::options();

		return new WebAuthn(
			$options->boardTitle,
			parse_url($options->boardUrl, PHP_URL_HOST)
		);
	}
}
