<?php

namespace XFES\Search\Query;

use XF\Mvc\Entity\Entity;
use XF\Search\Query\Query;

class MoreLikeThisQuery extends Query
{
	/**
	 * @var Entity[]
	 */
	protected $entities = [];

	/**
	 * @var bool
	 */
	protected $titleOnly = false;

	/**
	 * @param Entity $entity
	 *
	 * @return static
	 *
	 * @throws \InvalidArgumentException
	 */
	public function like(Entity $entity)
	{
		$contentType = $entity->getEntityContentType();
		if (!$contentType)
		{
			throw new \InvalidArgumentException(
				'The entity does not specify a content type'
			);
		}

		try
		{
			$this->search->handler($contentType);
		}
		catch (\InvalidArgumentException $e)
		{
			throw new \InvalidArgumentException(
				"The content type '{$contentType}' does not have a search handler"
			);
		}

		$this->entities[] = $entity;

		return $this;
	}

	/**
	 * @return Entity[]
	 */
	public function getEntities()
	{
		return $this->entities;
	}

	/**
	 * @return array
	 */
	public function getUniqueQueryComponents()
	{
		$components = [];

		$entities = [];
		foreach ($this->entities AS $entity)
		{
			$entities[] = $entity->getEntityContentTypeId();
		}
		$components['entities'] = $entities;

		$components['titleOnly'] = $this->titleOnly;

		return $components;
	}
}
