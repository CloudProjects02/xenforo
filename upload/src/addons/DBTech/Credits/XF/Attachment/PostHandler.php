<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Attachment;

use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Attachment\Manipulator;
use XF\Entity\Attachment;
use XF\Entity\Post;
use XF\Entity\Thread;
use XF\Http\Upload;
use XF\Mvc\Entity\Entity;
use XF\PrintableException;

/**
 * @extends \XF\Attachment\PostHandler
 */
class PostHandler extends XFCP_PostHandler
{
	/**
	 * @param Upload $upload
	 * @param Manipulator $manipulator
	 *
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function validateAttachmentUpload(Upload $upload, Manipulator $manipulator)
	{
		$nodeId = 0;

		$context = $manipulator->getContext();
		if (isset($context['post_id']))
		{
			$content = \XF::app()->em()->find(Post::class, $context['post_id']);
			$nodeId = $content->Thread->node_id;
		}
		else if (isset($context['thread_id']))
		{
			$content = \XF::app()->em()->find(Thread::class, $context['thread_id']);
			$nodeId = $content->node_id;
		}
		else if (isset($context['node_id']))
		{
			$nodeId = $context['node_id'];
		}

		if ($nodeId)
		{
			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
			$eventTriggerRepo->getHandler('upload')
				->testApply([
					'node_id'      => $nodeId,
					'multiplier'   => $upload->getFileSize(),
					'extension'    => strtolower($upload->getExtension()),
				], \XF::visitor())
			;
		}

		parent::validateAttachmentUpload($upload, $manipulator);
	}

	/**
	 * @param Attachment $attachment
	 * @param Entity|null $container
	 *
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function onAssociation(Attachment $attachment, ?Entity $container = null)
	{
		/** @var Post $container */

		if ($container
			&& $container->isVisible()
			&& $container->Thread
		)
		{
			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
			$eventTriggerRepo->getHandler('upload')
				->apply($attachment->attachment_id, [
					'node_id'      => $container->Thread->node_id,
					'multiplier'   => $attachment->getFileSize(),
					'extension'    => strtolower($attachment->getExtension()),
					'content_type' => $attachment->content_type,
					'content_id'   => $attachment->content_id,
				], $attachment->Data->User)
			;
		}

		parent::onAssociation($attachment, $container);
	}

	/**
	 * @param Attachment $attachment
	 * @param Entity|null $container
	 *
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function beforeAttachmentDelete(Attachment $attachment, ?Entity $container = null)
	{
		/** @var Post $container */

		if ($container && $container->Thread)
		{
			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
			$eventTriggerRepo->getHandler('upload')
				->testUndo([
					'node_id'      => $container->Thread->node_id,
					'multiplier'   => $attachment->getFileSize(),
					'extension'    => strtolower($attachment->getExtension()),
					'content_type' => $attachment->content_type,
					'content_id'   => $attachment->content_id,
				], $attachment->Data->User)
			;
		}
		parent::beforeAttachmentDelete($attachment, $container);
	}

	/**
	 * @param Attachment $attachment
	 * @param Entity|null $container
	 *
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function onAttachmentDelete(Attachment $attachment, ?Entity $container = null)
	{
		if ($container && $container->Thread)
		{
			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
			$eventTriggerRepo->getHandler('upload')
				->undo($attachment->attachment_id, [
					'node_id'      => $container->Thread->node_id,
					'multiplier'   => $attachment->getFileSize(),
					'extension'    => strtolower($attachment->getExtension()),
					'content_type' => $attachment->content_type,
					'content_id'   => $attachment->content_id,
				], $attachment->Data->User)
			;
		}

		parent::onAttachmentDelete($attachment, $container);
	}
}