<?php

namespace XF\ContentVote;

use XF\Entity\User;
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
	 * @param T $entity
	 *
	 * @return bool
	 */
	abstract public function isCountedForContentUser(Entity $entity);

	/**
	 * @param string $contentType
	 */
	public function __construct($contentType)
	{
		$this->contentType = $contentType;
	}

	/**
	 * @param T $entity
	 * @param string|\Stringable|null $error
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
	 * @param T $entity
	 *
	 * @return int
	 */
	public function getContentUserId(Entity $entity)
	{
		if (isset($entity->user_id))
		{
			return $entity->user_id;
		}
		else if (isset($entity->User))
		{
			$user = $entity->User;
			if ($user instanceof User)
			{
				return $user->user_id ?? 0;
			}
			else
			{
				throw new \LogicException("Found a User relation but it did not match a user; please override");
			}
		}

		throw new \LogicException("Could not determine content user ID; please override");
	}

	/**
	 * @param T $entity
	 * @param int $totalScore
	 * @param int $voteCount
	 * @param array{} $extra Any extra info that may be passed in (currently unused)
	 *
	 * @return void
	 */
	public function updateContentVotes(Entity $entity, $totalScore, $voteCount, array $extra = [])
	{
		$entity->vote_count = $voteCount;
		$entity->vote_score = $totalScore;
		$entity->save();
	}

	/**
	 * @return list<string>
	 */
	public function getEntityWith()
	{
		return [];
	}

	/**
	 * @param int|list<int> $id
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
		return ['upvote', 'downvote'];
	}
}
