<?php

namespace XF\Attachment;

use XF\Entity\Attachment;
use XF\Entity\Forum;
use XF\Entity\Post;
use XF\Entity\Thread;
use XF\Mvc\Entity\Entity;
use XF\Repository\AttachmentRepository;

use function intval;

/**
 * @phpstan-type TContext array{
 *     post_id?: int|null,
 *     thread_id?: int|null,
 *     node_id?: int|null,
 * }
 *
 * @extends AbstractHandler<Post, TContext>
 */
class PostHandler extends AbstractHandler
{
	public function getContainerWith()
	{
		$visitor = \XF::visitor();

		return ['Thread', 'Thread.Forum', 'Thread.Forum.Node.Permissions|' . $visitor->permission_combination_id];
	}

	public function canView(Attachment $attachment, Entity $container, &$error = null)
	{
		if (!$container->canView())
		{
			return false;
		}

		$thread = $container->Thread;
		if ($thread === null)
		{
			return false;
		}

		return $thread->canViewAttachments($error);
	}

	public function canManageAttachments(array $context, &$error = null)
	{
		$forum = $this->getForumFromContext($context);
		return ($forum && $forum->canUploadAndManageAttachments());
	}

	public function onAttachmentDelete(Attachment $attachment, ?Entity $container = null)
	{
		if (!$container)
		{
			return;
		}

		$container->attach_count--;
		$container->save();

		\XF::app()->logger()->logModeratorAction($this->contentType, $container, 'attachment_deleted', [], false);
	}

	public function getConstraints(array $context)
	{
		$attachRepo = \XF::repository(AttachmentRepository::class);

		$constraints = $attachRepo->getDefaultAttachmentConstraints();

		$forum = $this->getForumFromContext($context);
		if ($forum && $forum->canUploadVideos())
		{
			$constraints = $attachRepo->applyVideoAttachmentConstraints($constraints);
		}

		return $constraints;
	}

	public function getContainerIdFromContext(array $context)
	{
		return isset($context['post_id']) ? intval($context['post_id']) : null;
	}

	public function getContext(?Entity $entity = null, array $extraContext = [])
	{
		if ($entity instanceof Post)
		{
			$extraContext['post_id'] = $entity->post_id;
		}
		else if ($entity instanceof Thread)
		{
			$extraContext['thread_id'] = $entity->thread_id;
		}
		else if ($entity instanceof Forum)
		{
			$extraContext['node_id'] = $entity->node_id;
		}
		else
		{
			throw new \InvalidArgumentException("Entity must be post, thread or forum");
		}

		return $extraContext;
	}

	/**
	 * @param TContext $context
	 *
	 * @return Forum|null
	 */
	protected function getForumFromContext(array $context)
	{
		$em = \XF::em();
		$forum = null;

		if (!empty($context['post_id']))
		{
			$post = $em->find(Post::class, intval($context['post_id']), ['Thread', 'Thread.Forum']);
			if (!$post || !$post->canView() || !$post->canEdit())
			{
				return null;
			}

			$thread = $post->Thread;
			if ($thread === null)
			{
				return null;
			}

			$forum = $thread->Forum;
		}
		else if (!empty($context['thread_id']))
		{
			$thread = $em->find(Thread::class, intval($context['thread_id']), ['Forum']);
			if (!$thread || !$thread->canView())
			{
				return null;
			}

			$forum = $thread->Forum;
		}
		else if (!empty($context['node_id']))
		{
			$forum = $em->find(Forum::class, intval($context['node_id']));
			if (!$forum || !$forum->canView())
			{
				return null;
			}
		}
		else
		{
			return null;
		}

		return $forum;
	}
}
