<?php

namespace XF\Tag;

use XF\Entity\DatableInterface;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;

/**
 * @template T of Entity
 */
abstract class AbstractHandler
{
	/**
	 * @var string
	 */
	protected $contentType;

	/**
	 * @param string $contentType
	 */
	public function __construct($contentType)
	{
		$this->contentType = $contentType;
	}

	/**
	 * @return array{
	 *     edit: bool,
	 *     removeOthers: bool,
	 *     minTotal: int,
	 * }
	 */
	abstract public function getPermissionsFromContext(Entity $entity);

	/**
	 * @param T $entity
	 *
	 * @return bool
	 */
	abstract public function getContentVisibility(Entity $entity);

	/**
	 * @param T $entity
	 * @param array<mixed> $options
	 *
	 * @return array<string, mixed>
	 */
	abstract public function getTemplateData(Entity $entity, array $options = []);

	/**
	 * Determines the date the given content was created.
	 *
	 * @param T $content
	 *
	 * @return int
	 */
	public function getContentDate(Entity $content)
	{
		if (!($content instanceof DatableInterface))
		{
			throw new \LogicException(
				'Could not determine content date; please override'
			);
		}

		return $content->getContentDate();
	}

	/**
	 * @param T $content
	 * @param array<int, array{tag: string, tag_url: string}> $cache
	 *
	 * @return void
	 */
	public function updateContentTagCache(Entity $content, array $cache)
	{
		if (!isset($content->tags))
		{
			throw new \LogicException("No 'tags' cache found; please override");
		}

		$content->tags = $cache;
		$content->save();
	}

	/**
	 * @param bool $forView
	 *
	 * @return list<string>
	 */
	public function getEntityWith($forView = false)
	{
		return [];
	}

	/**
	 * @return string
	 */
	public function getTemplateName()
	{
		return 'public:search_result_' . $this->contentType;
	}

	/**
	 * @param T $entity
	 * @param array<mixed> $options
	 *
	 * @return string
	 */
	public function renderResult(Entity $entity, array $options = [])
	{
		$template = $this->getTemplateName();
		$data = $this->getTemplateData($entity, $options);

		return \XF::app()->templater()->renderTemplate($template, $data);
	}

	/**
	 * @param T $entity
	 * @param string|\Stringable $error
	 *
	 * @return bool
	 */
	public function canViewContent(Entity $entity, &$error = null)
	{
		if (method_exists($entity, 'canView'))
		{
			return $entity->canView($error);
		}

		throw new \LogicException("Could not determine content viewability; please override");
	}

	/**
	 * @param int|list<int> $id
	 * @param bool $forView
	 *
	 * @return ($id is int ? T|null : AbstractCollection<T>)
	 */
	public function getContent($id, $forView = false)
	{
		return \XF::app()->findByContentType($this->contentType, $id, $this->getEntityWith($forView));
	}

	/**
	 * @param T $entity
	 * @param string|\Stringable $error
	 *
	 * @return bool
	 */
	public function canUseInlineModeration(Entity $entity, &$error = null)
	{
		return false;
	}

	/**
	 * @return string
	 */
	public function getContentType()
	{
		return $this->contentType;
	}
}
