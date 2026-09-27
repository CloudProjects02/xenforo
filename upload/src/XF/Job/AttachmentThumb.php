<?php

namespace XF\Job;

use XF\Entity\AttachmentData;
use XF\Repository\AttachmentRepository;
use XF\Service\Attachment\PreparerService;
use XF\Util\File;

class AttachmentThumb extends AbstractRebuildJob
{
	public function run($maxRunTime)
	{
		$result = parent::run($maxRunTime);

		File::cleanUpTempFiles();

		return $result;
	}

	protected function getNextIds($start, $batch): array
	{
		$db = \XF::db();

		return $db->fetchAllColumn(
			$db->limit(
				"SELECT data_id
					FROM xf_attachment_data
					WHERE data_id > ?
					ORDER BY data_id",
				$batch
			),
			$start
		);
	}

	protected function rebuildById($id): void
	{
		$attachmentData = \XF::em()->find(AttachmentData::class, $id);
		if (!$attachmentData)
		{
			return;
		}

		if (!$attachmentData->canCreateThumbnails())
		{
			return;
		}

		if (!\XF::fs()->has($attachmentData->getAbstractedDataPath()))
		{
			return;
		}

		$this->resetThumbnailData($attachmentData);

		$attachmentPreparer = \XF::app()->service(PreparerService::class);
		// temp files are cleaned up at the end of the run
		$tempFile = File::copyAbstractedPathToTempFile($attachmentData->getAbstractedDataPath());
		$thumbnails = $attachmentPreparer->generateAttachmentThumbnails($tempFile);

		try
		{
			foreach ($thumbnails AS $size => $thumbnail)
			{
				$source = $thumbnail['path'];
				$destination = $attachmentData->getAbstractedThumbnailPathForSize($size);
				File::copyFileToAbstractedPath($source, $destination);

				if ($size === 1)
				{
					$attachmentData->thumbnail_width = $thumbnail['width'];
					$attachmentData->thumbnail_height = $thumbnail['height'];
				}

				if ($size === 2)
				{
					$attachmentData->thumbnail_retina = true;
				}
			}
		}
		catch (\Exception $e)
		{
			$this->resetThumbnailData($attachmentData);

			\XF::logException(
				$e,
				false,
				"Thumbnail rebuild failed for attachment #{$id}:"
			);
		}

		$attachmentData->save();
	}

	protected function resetThumbnailData(AttachmentData $attachmentData): void
	{
		$attachmentRepo = \XF::repository(AttachmentRepository::class);
		$sizes = array_keys($attachmentRepo->getThumbnailSizes(true));

		foreach ($sizes AS $size)
		{
			$path = $attachmentData->getAbstractedThumbnailPathForSize($size);
			File::deleteFromAbstractedPath($path);
		}

		$attachmentData->thumbnail_width = 0;
		$attachmentData->thumbnail_height = 0;
		$attachmentData->thumbnail_retina = false;
	}

	protected function getStatusType()
	{
		return \XF::phrase('attachment_thumbnails');
	}
}
