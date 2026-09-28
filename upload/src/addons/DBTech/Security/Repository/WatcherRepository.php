<?php

namespace DBTech\Security\Repository;

use DBTech\Security\Entity\Watcher;
use DBTech\Security\Finder\WatcherFinder;
use DBTech\Security\Watcher\AbstractHandler;
use GuzzleHttp\Exception\RequestException;
use XF\Entity\User;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;
use XF\Repository\IpRepository;

class WatcherRepository extends Repository
{
	public const BB2_MIN_PHP_VERSION = '7.2.0';
	public const BB2_MAX_PHP_VERSION = '8.0.99';

	public static function noBadBehavior(): bool
	{
		return (
			version_compare(phpversion(), static::BB2_MIN_PHP_VERSION, '<')
			|| version_compare(phpversion(), static::BB2_MAX_PHP_VERSION, '>=')
		);
	}

	/**
	 * @return AbstractHandler[]
	 * @throws \Exception
	 */
	public function getHandlers(): array
	{
		$handlers = [];

		foreach (\XF::app()->getContentTypeField('dbtech_security_watcher_handler_class') AS $contentType => $handlerClass)
		{
			if (class_exists($handlerClass))
			{
				$handlerClass = \XF::extendClass($handlerClass);
				$handlers[$contentType] = new $handlerClass($contentType);
			}
		}

		return $handlers;
	}

	/**
	 * @param string $type
	 * @param bool $throw
	 *
	 * @return AbstractHandler|null
	 * @throws \Exception
	 */
	public function getHandler(string $type, bool $throw = true): ?AbstractHandler
	{
		$handlerClass = \XF::app()->getContentTypeFieldValue($type, 'dbtech_security_watcher_handler_class');
		if (!$handlerClass)
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("No watcher handler for '$type'");
			}
			return null;
		}

		if (!class_exists($handlerClass))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("Watcher handler for '$type' does not exist: $handlerClass");
			}
			return null;
		}

		$handlerClass = \XF::extendClass($handlerClass);
		return new $handlerClass($type);
	}

	/**
	 * @return array
	 */
	public function getCacheData(): array
	{
		$cache = [];

		/** @var \DBTech\Security\Entity\Watcher[] $entities */
		$entities = \XF::app()->finder(WatcherFinder::class)
			->orderForList()
			->fetch()
		;
		foreach ($entities AS $entity)
		{
			$cache[$entity->getIdentifier()] = $entity->toArray(false);
		}

		return $cache;
	}

	/**
	 * @return array
	 */
	public function rebuildCache(): array
	{
		$cache = $this->getCacheData();
		\XF::registry()->set('dbtSecurityWatchers', $cache);
		return $cache;
	}

	/**
	 * @return WatcherFinder
	 */
	public function findWatchersForList(): WatcherFinder
	{
		return \XF::app()->finder(WatcherFinder::class)
			->orderForList()
		;
	}

	/**
	 * @return array<\XF\Mvc\Entity\AbstractCollection<\DBTech\Security\Entity\Watcher>>|null
	 */
	public function getWatchers(): ?array
	{
		$container = \XF::app()->container();
		if (isset($container['dbtechSecurity.watchers']))
		{
			return $container['dbtechSecurity.watchers'];
		}

		return null;
	}

	/**
	 * @param bool $onlyActive
	 * @param bool $onlyWithWatchers
	 *
	 * @return AbstractCollection
	 * @throws \Exception
	 */
	public function getWatcherTypes(bool $onlyActive = false, bool $onlyWithWatchers = false): AbstractCollection
	{
		$watcherTypes = new ArrayCollection($this->getHandlers());

		if ($onlyActive)
		{
			$watcherTypes = $watcherTypes->filter(function (AbstractHandler $watcherType): ?AbstractHandler
			{
				if (!$watcherType->isActive())
				{
					return null;
				}

				return $watcherType;
			});
		}

		if ($onlyWithWatchers)
		{
			/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Security\Entity\Watcher> $watchers */
			$watchers = \XF::app()->finder(WatcherFinder::class)->fetch();

			$watcherTypes = $watcherTypes->filter(function (AbstractHandler $watcherType) use ($watchers, $onlyActive): ?AbstractHandler
			{
				if ($onlyActive)
				{
					$watchers = $watchers->filterViewable();
				}

				$activeWatchers = $watchers->filter(function (Watcher $watcher) use ($watcherType): ?Watcher
				{
					if ($watcher->watcher_type == $watcherType->getContentType())
					{
						return $watcher;
					}

					return null;
				});

				if (!$activeWatchers->count())
				{
					return null;
				}

				// Caching purposes
				$watcherType->setWatchers($activeWatchers);

				return $watcherType;
			});
		}

		return $watcherTypes;
	}

	/**
	 * @param bool $filterActive
	 * @param bool $onlyWithWatchers
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getWatcherTitlePairs(bool $filterActive = false, bool $onlyWithWatchers = false): array
	{
		$arr = $this->getWatcherTypes($filterActive, $onlyWithWatchers)->pluck(function (AbstractHandler $e, $k): array
		{
			return [$k, $e->getTitle()->render()];
		}, false);

		natcasesort($arr);

		return $arr;
	}

	/**
	 * @param User $user
	 */
	public function breachCheck(User $user): void
	{
		/** @var \DBTech\Security\XF\Entity\User $user */

		$options = $this->options();
		$breaches = [];

		if (empty($options->dbtechSecurityBreachCheck['apiKey']))
		{
			return;
		}

		$reader = $this->app()
			->http()
			->reader()
		;

		if ($options->dbtechSecurityBreachCheck['includeUsername'])
		{
			try
			{
				$response = $reader->getUntrusted(
					'https://haveibeenpwned.com/api/v3/breachedaccount/' . urlencode($user->username),
					[],
					null,
					[
						'headers' => [
							'User-Agent' => $this->getUserAgent(),
							'Accept' => 'application/json',
							'hibp-api-key' => $options->dbtechSecurityBreachCheck['apiKey'],
						],
						'query' => ['truncateResponse' => false],
					]
				);
				if ($response)
				{
					$jsonText = $response->getBody()->getContents();

					$response->getBody()->close();

					if ($response->getStatusCode() == 200)
					{
						try
						{
							$json = \json_decode($jsonText, true);
							$breaches = array_merge($breaches, $json);
						}
						catch (\InvalidArgumentException $e)
						{
						}
					}
					else
					{
						\XF::logError(\XF::phraseDeferred('received_unexpected_response_code_x_message_y', [
							'code' => $response->getStatusCode(),
							'message' => $response->getReasonPhrase(),
						]));
					}
				}
			}
			catch (RequestException $e)
			{
			}
		}

		try
		{
			$response = $reader->getUntrusted(
				'https://haveibeenpwned.com/api/v3/breachedaccount/' . urlencode($user->email),
				[],
				null,
				[
					'headers' => [
						'User-Agent' => $this->getUserAgent(),
						'Accept' => 'application/json',
						'hibp-api-key' => $options->dbtechSecurityBreachCheck['apiKey'],
					],
					'query' => ['truncateResponse' => false],
				]
			);
			if ($response)
			{
				$body = $response->getBody();

				switch ($response->getStatusCode())
				{
					case 200:
						$jsonText = $body->getContents();

						try
						{
							$json = \json_decode($jsonText, true);
							$breaches = array_merge($breaches, $json);
						}
						catch (\InvalidArgumentException $e)
						{
						}
						break;

					case 404:
						// Do nothing, as $breaches is already an empty array
						break;

					default:
						\XF::logError(\XF::phraseDeferred('received_unexpected_response_code_x_message_y', [
							'code' => $response->getStatusCode(),
							'message' => $response->getReasonPhrase(),
						]));
						break;
				}

				$body->close();
			}
		}
		catch (RequestException $e)
		{
		}

		if (count($breaches))
		{
			$latestBreach = $user->dbtech_security_lastbreach;
			foreach ($breaches AS $key => &$breach)
			{
				$breach['BreachDate'] = strtotime($breach['BreachDate']);
				$breach['AddedDate'] = strtotime($breach['AddedDate']);

				if ($breach['AddedDate'] <= $user->dbtech_security_lastbreach)
				{
					unset($breaches[$key]);
					continue;
				}

				$latestBreach = max($breach['AddedDate'], $latestBreach);
			}

			if ($latestBreach > $user->dbtech_security_lastbreach)
			{
				if ($user->email)
				{
					// Create appropriate mail object
					\XF::app()->mailer()->newMail()
						->setTemplate('dbtech_security_alert_breach', [
							'breaches' => $breaches,
						])
						->setToUser($user)
						->queue();
				}

				$updateRecord = [
					'dbtech_security_lastbreach' => $latestBreach,
				];

				if ($user->dbtech_security_lastbreach)
				{
					// Initial breach records don't count
					$updateRecord['dbtech_security_breached'] = 1;
				}

				$user->fastUpdate($updateRecord);
			}
		}
	}

	/**
	 * @param User $user
	 *
	 * @throws PrintableException
	 */
	public function unlockAccount(User $user): void
	{
		$user->Option->dbtech_security_is_user_locked = 0;
		$user->Option->dbtech_security_is_admin_locked = 0;
		$user->Option->saveIfChanged();

		$user->DBTechSecurityAccountLock->delete();

		$ipRepo = \XF::app()->repository(IpRepository::class);
		$ipEnt = $ipRepo->logIp(
			$user->user_id,
			\XF::app()->request()->getIp(),
			'dbtech_security_lock',
			$user->user_id,
			'unlock'
		);
	}

	/**
	 * @return string
	 */
	protected function getUserAgent(): string
	{
		$version = \XF::$version;
		return 'XenForo/' . $version . ' | DBT-Security/' . $version;
	}
}