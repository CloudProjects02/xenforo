<?php

namespace XF\Poll;

use XF\Entity\Poll;
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
	 * @param T $content
	 * @param string|\Stringable $error
	 *
	 * @return bool
	 */
	abstract public function canCreate(Entity $content, &$error = null);

	/**
	 * @param T $content
	 * @param string|\Stringable $error
	 *
	 * @return bool
	 */
	abstract public function canEdit(Entity $content, Poll $poll, &$error = null);

	/**
	 * @param T $content
	 * @param string|\Stringable $error
	 *
	 * @return bool
	 */
	abstract public function canAlwaysEditDetails(Entity $content, Poll $poll, &$error = null);

	/**
	 * @param T $content
	 * @param string|\Stringable $error
	 *
	 * @return bool
	 */
	abstract public function canDelete(Entity $content, Poll $poll, &$error = null);

	/**
	 * @param T $content
	 * @param string|\Stringable $error
	 *
	 * @return bool
	 */
	abstract public function canVote(Entity $content, Poll $poll, &$error = null);

	/**
	 * @param string $action
	 * @param T $content
	 * @param array<string, string> $extraParams
	 *
	 * @return string
	 */
	abstract public function getPollLink($action, Entity $content, array $extraParams = []);

	/**
	 * @param T $content
	 *
	 * @return void
	 */
	abstract public function finalizeCreation(Entity $content, Poll $poll);

	/**
	 * @param T $content
	 *
	 * @return void
	 */
	abstract public function finalizeDeletion(Entity $content, Poll $poll);

	/**
	 * @param T $content
	 * @param string|\Stringable $error
	 *
	 * @return bool
	 */
	public function canViewContent(Entity $content, &$error = null)
	{
		if (!method_exists($content, 'canView'))
		{
			throw new \LogicException(
				'Could not determine content viewability; please override'
			);
		}

		return $content->canView($error);
	}

	/**
	 * @return list<string>
	 */
	public function getEntityWith()
	{
		return [];
	}

	/**
	 * @param int|list<string> $id
	 *
	 * @return ($id is int ? T|null : AbstractCollection<T>)
	 */
	public function getContent($id)
	{
		return \XF::app()->findByContentType($this->contentType, $id, $this->getEntityWith());
	}

	/**
	 * @return string
	 */
	public function getContentType()
	{
		return $this->contentType;
	}

	/**
	 * @return list<string>
	 */
	public static function getWebhookEvents(): array
	{
		return ['poll_vote'];
	}
}
