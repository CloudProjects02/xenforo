<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XFMG\Service\Media;

use DBTech\Credits\Repository\EventTriggerRepository;
use XF\PrintableException;
use XFMG\Entity\MediaItem;

/**
 * @extends \XFMG\Service\Media\Creator
 */
class Creator extends XFCP_Creator
{
	/**
	 * @return array
	 * @throws PrintableException
	 * @throws \Exception
	 */
	protected function _validate()
	{
		$previous = parent::_validate();

		if (empty($previous))
		{
			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

			if ($this->attachment)
			{
				$eventTriggerRepo->getHandler('galleryupload')
					->testApply([
						'multiplier' => $this->attachment->getFileSize(),
						'extension'  => $this->attachment->getExtension(),
					], $this->mediaItem->User)
				;
			}
			else
			{
				$eventTriggerRepo->getHandler('galleryupload')
					->testApply([], $this->mediaItem->User)
				;
			}
		}

		return $previous;
	}

	/**
	 * @return MediaItem
	 * @throws PrintableException
	 * @throws \Exception
	 */
	protected function _save()
	{
		$mediaItem = parent::_save();

		if ($mediaItem && $mediaItem->isVisible())
		{
			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
			if ($mediaItem->Attachment)
			{
				$eventTriggerRepo->getHandler('galleryupload')
					->apply($mediaItem->media_id, [
						'multiplier' => $mediaItem->Attachment->getFileSize(),
						'extension'  => $mediaItem->Attachment->getExtension(),
						'content_type' => 'xfmg_media',
						'content_id' => $mediaItem->media_id,
					], $mediaItem->User)
				;
			}
			else
			{
				$eventTriggerRepo->getHandler('galleryupload')
					->apply($mediaItem->media_id, [
						'content_type' => 'xfmg_media',
						'content_id' => $mediaItem->media_id,
					], $mediaItem->User)
				;
			}
		}

		return $mediaItem;
	}
}