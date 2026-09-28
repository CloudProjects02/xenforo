<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XFRM\Entity;

use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Repository\AttachmentRepository;
use XFRM\Finder\ResourceVersion;

/**
 * @extends \XFRM\Entity\ResourceItem
 */
class ResourceItem extends XFCP_ResourceItem
{
	/**
	 * @throws \Exception
	 */
	protected function _preSave()
	{
		// Do parent stuff
		parent::_preSave();

		if (!$this->user_id)
		{
			return;
		}

		if ($this->isInsert())
		{
			if ($this->resource_type == 'download')
			{
				$attachRepo = \XF::app()->repository(AttachmentRepository::class);

				/** @var \XF\Mvc\Entity\AbstractCollection<\XF\Entity\Attachment> $attachments */
				$attachments = $attachRepo->findAttachmentsByContent('resource_version', $this->current_version_id)
					->with('Data')
					->fetch()
				;

				foreach ($attachments AS $attachment)
				{
					$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
					$eventTriggerRepo->getHandler('resourceupload')
						->testApply([
							'multiplier' => $attachment->getFileSize(),
							'extension' => $attachment->getExtension(),
						], $this->User)
					;
				}
			}
			else
			{
				$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
				$eventTriggerRepo->getHandler('resourceupload')
					->testApply([], $this->User)
				;
			}
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function _postSave()
	{
		// Do parent stuff
		parent::_postSave();

		if (!$this->user_id)
		{
			return;
		}

		if ($this->isInsert())
		{
			if ($this->resource_type == 'download')
			{
				$attachRepo = \XF::app()->repository(AttachmentRepository::class);

				/** @var \XF\Mvc\Entity\AbstractCollection<\XF\Entity\Attachment> $attachments */
				$attachments = $attachRepo->findAttachmentsByContent('resource_version', $this->current_version_id)
					->with('Data')
					->fetch()
				;

				foreach ($attachments AS $attachment)
				{
					$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
					$eventTriggerRepo->getHandler('resourceupload')
						->apply($attachment->attachment_id, [
							'multiplier' => $attachment->getFileSize(),
							'extension' => $attachment->getExtension(),
							'content_type' => 'resource',
							'content_id' => $this->resource_id,
						], $this->User)
					;
				}
			}
			else
			{
				$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
				$eventTriggerRepo->getHandler('resourceupload')
					->apply(0, [
						'content_type' => 'resource',
						'content_id' => $this->resource_id,
					], $this->User)
				;
			}
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function _preDelete()
	{
		// Do parent stuff
		parent::_preDelete();

		if (!$this->user_id)
		{
			return;
		}

		if ($this->resource_type == 'download')
		{
			/** @var \XFRM\Entity\ResourceVersion $firstVersion */
			$firstVersion = \XF::app()->finder(ResourceVersion::class)
				->where('resource_id', $this->resource_id)
				->order('resource_version_id')
				->fetchOne()
			;

			$attachRepo = \XF::app()->repository(AttachmentRepository::class);

			/** @var \XF\Mvc\Entity\AbstractCollection<\XF\Entity\Attachment> $attachments */
			$attachments = $attachRepo->findAttachmentsByContent('resource_version', $firstVersion->resource_version_id)
				->with('Data')
				->fetch()
			;

			foreach ($attachments AS $attachment)
			{
				$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
				$eventTriggerRepo->getHandler('resourceupload')
					->testUndo([
						'multiplier' => $attachment->getFileSize(),
						'extension' => $attachment->getExtension(),
					], $this->User)
				;
			}
		}
		else
		{
			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
			$eventTriggerRepo->getHandler('resourceupload')
				->testUndo([], $this->User)
			;
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function _postDelete()
	{
		parent::_postDelete();

		if (!$this->user_id)
		{
			return;
		}

		if ($this->resource_type == 'download')
		{
			/** @var \XFRM\Entity\ResourceVersion|null $firstVersion */
			$firstVersion = \XF::app()->finder(ResourceVersion::class)
				->where('resource_id', $this->resource_id)
				->order('resource_version_id')
				->fetchOne()
			;
			if ($firstVersion)
			{
				$attachRepo = \XF::app()->repository(AttachmentRepository::class);

				/** @var \XF\Mvc\Entity\AbstractCollection<\XF\Entity\Attachment> $attachments */
				$attachments = $attachRepo->findAttachmentsByContent(
					'resource_version',
					$firstVersion->resource_version_id
				)
					->with('Data')
					->fetch()
				;

				foreach ($attachments AS $attachment)
				{
					$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
					$eventTriggerRepo->getHandler('resourceupload')
						->undo($attachment->attachment_id, [
							'multiplier'   => $attachment->getFileSize(),
							'extension'    => $attachment->getExtension(),
							'content_type' => 'resource',
							'content_id'   => $this->resource_id,
						], $this->User)
					;
				}
			}
		}
		else
		{
			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
			$eventTriggerRepo->getHandler('resourceupload')
				->undo(0, [
					'content_type' => 'resource',
					'content_id' => $this->resource_id,
				], $this->User)
			;
		}
	}
}