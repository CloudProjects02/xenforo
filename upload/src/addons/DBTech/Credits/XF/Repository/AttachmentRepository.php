<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Repository;

use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Entity\Attachment;
use XF\Entity\Post;

/**
 * @extends \XF\Repository\AttachmentRepository
 */
class AttachmentRepository extends XFCP_AttachmentRepository
{
	/**
	 * @param Attachment $attachment
	 *
	 * @throws \Exception
	 */
	public function logAttachmentView(Attachment $attachment)
	{
		// Shorthand
		$visitor = \XF::visitor();

		$contentInfo = $attachment->getContainer();
		if ($contentInfo !== null)
		{
			$nodeId = 0;

			switch ($attachment->content_type)
			{
				case 'post':
					/** @var Post $contentInfo */
					if (!$contentInfo->Thread)
					{
						break;
					}

					$nodeId = $contentInfo->Thread->node_id;
					break;
			}

			if ($attachment->content_type == 'post')
			{
				$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

				$eventTriggerRepo->getHandler('download')
					->apply($attachment->attachment_id, [
						'node_id'      => $nodeId,
						'multiplier'   => $attachment->getFileSize(),
						'owner_id'     => $attachment->Data->user_id,
						'extension'    => strtolower($attachment->getExtension()),
						'content_type' => $attachment->content_type,
						'content_id'   => $attachment->content_id,
					], $visitor)
				;

				if ($visitor->user_id != $attachment->Data->user_id)
				{
					$eventTriggerRepo->getHandler('downloaded')
						->apply($attachment->attachment_id, [
							'node_id'        => $nodeId,
							'multiplier'     => $attachment->getFileSize(),
							'source_user_id' => $visitor->user_id,
							'extension'      => strtolower($attachment->getExtension()),
							'content_type'   => $attachment->content_type,
							'content_id'     => $attachment->content_id,
						], $attachment->Data->User)
					;
				}
			}
		}

		parent::logAttachmentView($attachment);
	}
}