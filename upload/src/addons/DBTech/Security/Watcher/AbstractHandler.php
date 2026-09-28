<?php

namespace DBTech\Security\Watcher;

use DBTech\Security\Entity\Watcher;
use DBTech\Security\Finder\WatcherFinder;
use DBTech\Security\Repository\WatcherRepository;
use DBTech\Security\Service\Watcher\Trigger;
use XF\App;
use XF\Entity\User;
use XF\Language;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Manager;
use XF\Phrase;
use XF\PrintableException;

abstract class AbstractHandler
{
	protected array $options = [
		'closeForum' => true,
		'emailWebmaster' => true,
		'banIp' => true,
		'banUser' => true,
		'emailUser' => false,
		'lockChange' => false,
		'lockReset' => false,
		'lockUser' => false,
		'adminLockUser' => false,
	];
	protected string $contentType;
	protected Language $defaultLanguage;

	/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Security\Entity\Watcher> */
	protected AbstractCollection $watchers;


	/**
	 * @param Watcher $watcher
	 *
	 * @return Phrase
	 */
	abstract public function getParsedRule(Watcher $watcher): Phrase;

	/**
	 * @param Watcher $watcher
	 * @param array $params
	 * @param User|null $user
	 * @param string|null $logMessage
	 *
	 * @return bool
	 */
	abstract protected function checkWatcher(
		Watcher $watcher,
		array &$params = [],
		?User $user = null,
		?string &$logMessage = null
	): bool;

	/**
	 * @param string $contentType
	 */
	public function __construct(string $contentType)
	{
		$this->contentType = $contentType;

		$this->init();
	}

	/**
	 * Designed to be overridden if need be
	 */
	protected function init(): void
	{
		$watcherRepo = \XF::app()->repository(WatcherRepository::class);

		$allWatchers = $watcherRepo->getWatchers();
		if ($allWatchers === null)
		{
			$watcherRepo->rebuildCache();
		}
		else if (!empty($allWatchers[$this->getContentType()]))
		{
			$this->watchers = $allWatchers[$this->getContentType()];
		}
		else
		{
			$this->watchers = \XF::app()->em()->getEmptyCollection();
		}

		$this->defaultLanguage = \XF::app()->language(\XF::app()->options()->defaultLanguageId);
	}

	/**
	 * @return array
	 */
	public function getOptions(): array
	{
		return $this->options;
	}

	/**
	 * @param string $key
	 *
	 * @return mixed|null
	 */
	public function getOption(string $key): ?bool
	{
		return $this->options[$key] ?? null;
	}

	/**
	 * @return string
	 */
	public function getContentType(): string
	{
		return $this->contentType;
	}

	/**
	 * @param \XF\Mvc\Entity\AbstractCollection<\DBTech\Security\Entity\Watcher>|null $watchers
	 */
	public function setWatchers(?AbstractCollection $watchers = null): void
	{
		$this->watchers = $watchers;
	}

	/**
	 * @param bool $onlyViewable
	 * @param bool $force
	 *
	 * @return \XF\Mvc\Entity\AbstractCollection<\DBTech\Security\Entity\Watcher>
	 */
	public function getWatchers(bool $onlyViewable = false, bool $force = false): AbstractCollection
	{
		if ($force)
		{
			$watchers = \XF::app()->finder(WatcherFinder::class)
				->where('watcher_type', $this->getContentType())
				->fetch()
			;

			$this->watchers = $watchers;
		}

		if ($onlyViewable)
		{
			return $this->watchers->filterViewable();
		}

		return $this->watchers;
	}

	/**
	 * @return Phrase
	 */
	public function getTitle(): Phrase
	{
		return \XF::phrase('dbtech_security_watcher_title.' . $this->contentType);
	}

	/**
	 * @return Phrase
	 */
	public function getDescription(): Phrase
	{
		return \XF::phrase('dbtech_security_watcher_description.' . $this->contentType);
	}

	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return true;
	}

	/**
	 * @param array $params
	 * @param User|null $user
	 *
	 * @return void
	 * @throws PrintableException
	 */
	public function trigger(array $params = [], ?User $user = null): void
	{
		if (!$this->preCheck($params, $user))
		{
			return;
		}

		$watchers = $this->getWatchers(true);
		if (!$watchers->count())
		{
			return;
		}

		foreach ($watchers AS $watcher)
		{
			$this->triggerWatcher($watcher, $params, $user);
		}
	}

	/**
	 * @param array $params
	 * @param User|null $user
	 *
	 * @return bool
	 */
	protected function preCheck(array &$params, ?User $user = null): bool
	{
		return true;
	}

	/**
	 * @param Watcher $watcher
	 * @param array $params
	 * @param User|null $user
	 *
	 * @return void
	 * @throws PrintableException
	 */
	public function triggerWatcher(Watcher $watcher, array $params = [], ?User $user = null): void
	{
		$logMessage = null;
		if ($this->checkWatcher($watcher, $params, $user, $logMessage))
		{
			$watcherService = \XF::app()->service(Trigger::class, $watcher, $user);
			if ($logMessage)
			{
				$watcherService->setLogMessage($logMessage);
			}
			$watcherService->setParams($params);
			if ($watcherService->trigger())
			{
				$this->postTrigger($watcher, $params, $user, $logMessage);
			}
		}
	}

	/**
	 * @param Watcher $watcher
	 * @param array $params
	 * @param User|null $user
	 * @param string|null $logMessage
	 *
	 * @return void
	 */
	protected function postTrigger(Watcher $watcher, array $params = [], ?User $user = null, ?string $logMessage = null)
	{
	}

	/**
	 * @param Watcher $watcher
	 *
	 * @return string
	 */
	public function renderOptions(Watcher $watcher): string
	{
		$templateName = $this->getOptionsTemplate();
		if (!$templateName)
		{
			return '';
		}
		return \XF::app()->templater()->renderTemplate(
			$templateName,
			array_merge($this->getDefaultTemplateParams('options'), ['watcher' => $watcher])
		);
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate(): ?string
	{
		return 'admin:dbtech_security_watcher_edit_' . $this->contentType;
	}

	/**
	 * @param array $input
	 *
	 * @return array
	 */
	public function filterOptions(array $input = []): array
	{
		return $input;
	}

	/**
	 * @param string $context
	 *
	 * @return array
	 */
	protected function getDefaultTemplateParams(string $context): array
	{
		return [
			'title' => $this->getTitle(),
			'options' => $this->options,
		];
	}

	/**
	 * @param string $type
	 * @param bool $throw
	 *
	 * @return AbstractHandler|null
	 * @throws \Exception
	 */
	protected function getHandler(string $type, bool $throw = true): ?AbstractHandler
	{
		return \XF::app()->repository(WatcherRepository::class)
			->getHandler($type, $throw)
		;
	}

	/**
	 * @return \ArrayObject
	 */
	protected function options(): \ArrayObject
	{
		return \XF::app()->options();
	}

	/**
	 * @return Manager
	 */
	protected function em(): Manager
	{
		return \XF::app()->em();
	}

	/**
	 * @return App
	 */
	protected function app(): App
	{
		return \XF::app();
	}
}