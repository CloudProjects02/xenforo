<?php

namespace DBTech\Credits\ContentAccess;

use XF\App;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Manager;
use XF\Phrase;

abstract class AbstractHandler
{
	/** @var string */
	protected string $contentType;


	/**
	 * @param string $contentType
	 */
	public function __construct(string $contentType)
	{
		$this->contentType = $contentType;

		$this->setupOptions();
	}

	/**
	 * Designed to be overridden if need be
	 */
	protected function setupOptions(): void
	{
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
	public function getOption(string $key): mixed
	{
		return $this->options[$key] ?? null;
	}

	/**
	 * @param string $key
	 * @param $value
	 *
	 * @return $this
	 */
	public function setOption(string $key, $value): AbstractHandler
	{
		$this->options[$key] = $value;

		return $this;
	}

	/**
	 * @param bool $forView
	 *
	 * @return array
	 */
	public function getEntityWith(bool $forView = false): array
	{
		return [];
	}

	/**
	 * @param int|string $id
	 *
	 * @return null|AbstractCollection|Entity
	 */
	public function getContent(int|string $id): Entity|AbstractCollection|null
	{
		return $this->findByContentType($this->contentType, $id, $this->getEntityWith());
	}

	/**
	 * @return string
	 */
	public function getContentType(): string
	{
		return $this->contentType;
	}

	/**
	 * @param string $contentType
	 * @param int|array $contentId
	 * @param array $with
	 *
	 * @return null|AbstractCollection|Entity
	 */
	public function findByContentType(string $contentType, int|array $contentId, array $with = []): Entity|AbstractCollection|null
	{
		$entity = $this->getContentTypeEntity($contentType);

		if (is_array($contentId))
		{
			return \XF::app()->em()->findByIds($entity, $contentId, $with);
		}
		else
		{
			return \XF::app()->em()->find($entity, $contentId, $with);
		}
	}

	/**
	 * @param string $contentType
	 * @param bool $throw
	 *
	 * @return string|null
	 */
	public function getContentTypeEntity(string $contentType, bool $throw = true): ?string
	{
		$entityId = \XF::app()->getContentTypeFieldValue($contentType, 'dbtech_credits_entity');
		if (!$entityId && $throw)
		{
			throw new \LogicException("Content type $contentType must define a 'dbtech_credits_entity' value");
		}

		return $entityId;
	}

	/**
	 * @return Phrase
	 */
	public function getTitle(): Phrase
	{
		return \XF::phrase('dbtech_credits_content_access_handler_title.' . $this->contentType);
	}

	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return true;
	}

	/**
	 * @param int $lastId
	 * @param int $amount
	 *
	 * @return mixed
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public function rebuildRange(int $lastId, int $amount)
	{
		$entities = $this->getContentInRange($lastId, $amount);
		if (!$entities->count())
		{
			return false;
		}

		$this->rebuildEntities($entities);

		$keys = $entities->keys();
		return $keys ? max($keys) : false;
	}

	/**
	 * @param int $lastId
	 * @param int $amount
	 * @param bool $forView
	 *
	 * @return AbstractCollection
	 */
	public function getContentInRange(int $lastId, int $amount, bool $forView = false): AbstractCollection
	{
		$entityId = $this->getContentTypeEntity($this->contentType);

		$em = \XF::em();
		try
		{
			$key = \XF::app()->em()->getEntityStructure($entityId)->primaryKey;
		}
		catch (\LogicException $e)
		{
			return $em->getEmptyCollection();
		}

		if (is_array($key))
		{
			if (count($key) > 1)
			{
				throw new \LogicException("Entity $entityId must only have a single primary key");
			}
			$key = reset($key);
		}

		$finder = \XF::app()->em()->getFinder($entityId)
			->where($key, '>', $lastId)
			->order($key)
			->with($this->getEntityWith($forView));

		$this->applyFinderConstraints($finder);

		return $finder->fetch($amount);
	}

	/**
	 * @param AbstractCollection $entities
	 */
	public function rebuildEntities(AbstractCollection $entities): void
	{
		foreach ($entities AS $entity)
		{
			$this->rebuild($entity);
		}
	}

	/**
	 * @param Entity $entity
	 */
	public function rebuild(Entity $entity): void
	{
	}

	/**
	 * @param Finder $finder
	 *
	 * @return void
	 */
	protected function applyFinderConstraints(Finder $finder): void
	{
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