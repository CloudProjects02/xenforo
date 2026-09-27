<?php

namespace XF\Service\Attachment;

use XF\Attachment\AbstractHandler;
use XF\Entity\Attachment;
use XF\Entity\AttachmentData;
use XF\Entity\User;
use XF\FileWrapper;
use XF\Finder\AttachmentFinder;
use XF\PrintableException;
use XF\Repository\AttachmentRepository;
use XF\Service\AbstractService;
use XF\Util\File;

class PreparerService extends AbstractService
{
	/**
	 * @var string
	 */
	public const INLINE_VIDEO_PATH = 'data://video/%FLOOR%/%DATA_ID%-%HASH%.%EXTENSION%';

	/**
	 * @var string
	 */
	public const INLINE_AUDIO_PATH = 'data://audio/%FLOOR%/%DATA_ID%-%HASH%.%EXTENSION%';

	public function insertAttachment(
		AbstractHandler $handler,
		FileWrapper $file,
		User $user,
		$hash
	)
	{
		$extra = [];

		$extension = strtolower($file->getExtension());

		if (File::isVideoInlineDisplaySafe($extension))
		{
			$extra['file_path'] = strtr(static::INLINE_VIDEO_PATH, ['%EXTENSION%' => $extension]);
		}
		else if (File::isAudioInlineDisplaySafe($extension))
		{
			$extra['file_path'] = strtr(static::INLINE_AUDIO_PATH, ['%EXTENSION%' => $extension]);
		}

		$handler->beforeNewAttachment($file, $extra);

		$data = $this->insertDataFromFile($file, $user->user_id, $extra);
		return $this->insertTemporaryAttachment($handler, $data, $hash, $file);
	}

	public function insertDataFromFile(FileWrapper $file, $userId, array $extra = [])
	{
		$data = $this->setupDataInsertFromFile($file, $userId, $extra);
		if (!$data->preSave())
		{
			throw new PrintableException($data->getErrors());
		}

		$sourceFile = $file->getFilePath();

		if ($data->canCreateThumbnails())
		{
			$thumbnails = $this->generateAttachmentThumbnails($sourceFile);

			foreach ($thumbnails AS $size => $thumbnail)
			{
				if ($size === 1)
				{
					$data->set('thumbnail_width', $thumbnail['width'], ['forceSet' => true]);
					$data->set('thumbnail_height', $thumbnail['height'], ['forceSet' => true]);
				}

				if ($size === 2)
				{
					$data->set('thumbnail_retina', true, ['forceSet' => true]);
				}
			}
		}
		else
		{
			$thumbnails = [];
		}

		$this->db()->beginTransaction();

		$data->save(true, false);

		$dataPath = $data->getAbstractedDataPath();

		// if one of the writes fail, remove the data record
		try
		{
			File::copyFileToAbstractedPath($sourceFile, $dataPath);

			foreach ($thumbnails AS $size => $thumbnail)
			{
				$thumbnailPath = $data->getAbstractedThumbnailPathForSize($size);
				File::copyFileToAbstractedPath(
					$thumbnail['path'],
					$thumbnailPath
				);
			}
		}
		catch (\Exception $e)
		{
			$this->db()->rollback();
			$this->app->em()->detachEntity($data);

			File::deleteFromAbstractedPath($dataPath);

			foreach ($thumbnails AS $size => $thumbnail)
			{
				$thumbnailPath = $data->getAbstractedThumbnailPathForSize($size);
				File::deleteFromAbstractedPath($thumbnailPath);
				@unlink($thumbnail['path']);
			}

			throw $e;
		}

		$this->db()->commit();

		return $data;
	}

	/**
	 * @param FileWrapper $file
	 * @param int             $userId
	 * @param array           $extra
	 *
	 * @return AttachmentData
	 */
	protected function setupDataInsertFromFile(
		FileWrapper $file,
		$userId,
		array $extra = []
	)
	{
		$extra = array_replace([
			'file_path' => '',
			'upload_date' => null,
		], $extra);

		/** @var AttachmentData $data */
		$data = $this->app->em()->create(AttachmentData::class);
		$data->user_id = $userId;
		$data->optimized = $file->isOptimized();
		$data->set('filename', $file->getFileName(), ['forceConstraint' => true]);
		$data->file_size = $file->getFileSize();
		$data->file_hash = md5_file($file->getFilePath());
		$data->file_path = $extra['file_path'];
		$data->width = $file->getImageWidth();
		$data->height = $file->getImageHeight();

		if ($extra['upload_date'])
		{
			$data->upload_date = $extra['upload_date'];
		}

		return $data;
	}

	public function optimizeExistingAttachment(AttachmentData $data): void
	{
		if ($this->app->options()->imageOptimization !== 'optimize')
		{
			return;
		}

		if (!$data->width || !$data->height)
		{
			return;
		}

		$abstractedPath = $data->getAbstractedDataPath();
		if (!$this->app->fs()->has($abstractedPath))
		{
			return;
		}

		// temp files are automatically cleaned up at the end of the request
		$tempFile = File::copyAbstractedPathToTempFile($abstractedPath);

		$imageManager = $this->app->imageManager();

		$image = $imageManager->imageFromFile($tempFile);
		if (!$image)
		{
			return;
		}

		$success = $image->optimizeImage($tempFile);
		if (!$success)
		{
			return;
		}

		if (!$image->save($tempFile))
		{
			return;
		}

		$data->filename = pathinfo($data->filename, PATHINFO_FILENAME) . '.webp';
		$fileWrapper = new FileWrapper($tempFile, $data->filename);

		$this->updateDataFromFile($data, $fileWrapper);
	}

	public function updateDataFromFile(AttachmentData $data, FileWrapper $file, array $extra = [])
	{
		$this->setupDataUpdateFromFile($data, $file, $extra);
		if (!$data->preSave())
		{
			throw new PrintableException($data->getErrors());
		}

		$sourceFile = $file->getFilePath();

		$thumbnails = [];
		if ($data->isChanged('file_hash'))
		{
			if ($data->canCreateThumbnails())
			{
				$thumbnails = $this->generateAttachmentThumbnails($sourceFile);
				foreach ($thumbnails AS $size => $thumbnail)
				{
					if ($size === 1)
					{
						$data->set('thumbnail_width', $thumbnail['width'], ['forceSet' => true]);
						$data->set('thumbnail_height', $thumbnail['height'], ['forceSet' => true]);
					}

					if ($size === 2)
					{
						$data->set('thumbnail_retina', true, ['forceSet' => true]);
					}
				}
			}
		}

		$this->db()->beginTransaction();

		$previousDataPath = null;

		$fileIsChanged = $data->isChanged(['file_hash', 'file_path']);
		if ($fileIsChanged)
		{
			$previousDataPath = $data->getExistingAbstractedDataPath();
		}

		$data->saveIfChanged($dataChanged, true, false);

		if ($fileIsChanged && $dataChanged)
		{
			$dataPath = $data->getAbstractedDataPath();

			try
			{
				File::copyFileToAbstractedPath($sourceFile, $dataPath);

				foreach ($thumbnails AS $size => $thumbnail)
				{
					$thumbnailPath = $data->getAbstractedThumbnailPathForSize($size);
					File::copyFileToAbstractedPath(
						$thumbnail['path'],
						$thumbnailPath
					);
				}
			}
			catch (\Exception $e)
			{
				$this->db()->rollback();
				$this->app->em()->detachEntity($data);

				throw $e;
			}

			if ($dataPath !== $previousDataPath)
			{
				File::deleteFromAbstractedPath($previousDataPath);
			}

			foreach ($thumbnails AS $size => $thumbnail)
			{
				$thumbnailPath = $data->getAbstractedThumbnailPathForSize($size);
				$previousThumbnailPath = $data->getExistingAbstractedThumbnailPathForSize($size);
				if ($thumbnailPath === $previousThumbnailPath)
				{
					continue;
				}

				File::deleteFromAbstractedPath($previousThumbnailPath);
			}
		}

		$this->db()->commit();

		return $data;
	}

	/**
	 * @param AttachmentData $data
	 * @param FileWrapper $file
	 * @param array                     $extra
	 */
	protected function setupDataUpdateFromFile(
		AttachmentData $data,
		FileWrapper $file,
		array $extra = []
	)
	{
		$data->optimized = $file->isOptimized();
		$data->file_size = $file->getFileSize();
		$data->file_hash = md5_file($file->getFilePath());
		$data->width = $file->getImageWidth();
		$data->height = $file->getImageHeight();

		if (isset($extra['file_path']))
		{
			$data->file_path = $extra['file_path'];
		}
	}

	/**
	 * @return array<int, array{path: string, width: int, height: int}>
	 */
	public function generateAttachmentThumbnails(string $sourceFile): array
	{
		$thumbnails = [];

		$attachmentRepo = \XF::repository(AttachmentRepository::class);
		$sizes = $attachmentRepo->getThumbnailSizes();

		foreach ($sizes AS $sizeCode => $size)
		{
			$image = $this->app->imageManager()->imageFromFile($sourceFile);
			if (!$image)
			{
				continue;
			}

			$image->resizeShortEdge($size);
			$newTempFile = File::getTempFile();
			if (!$newTempFile)
			{
				continue;
			}

			if (!$image->save($newTempFile))
			{
				continue;
			}

			$thumbnails[$sizeCode] = [
				'path' => $newTempFile,
				'width' => $image->getWidth(),
				'height' => $image->getHeight(),
			];
		}

		return $thumbnails;
	}

	/**
	 * @deprecated Use generateAttachmentThumbnails() instead
	 */
	public function generateAttachmentThumbnail($sourceFile, &$width = null, &$height = null)
	{
		$thumbnails = $this->generateAttachmentThumbnails($sourceFile);

		$m = $thumbnails[1] ?? null;
		if (!$m)
		{
			return null;
		}

		$width = $m['width'];
		$height = $m['height'];

		return $m['path'];
	}

	public function insertTemporaryAttachment(
		AbstractHandler $handler,
		AttachmentData $data,
		$tempHash,
		FileWrapper $file
	)
	{
		/** @var Attachment $attachment */
		$attachment = $this->app->em()->create(Attachment::class);

		$attachment->data_id = $data->data_id;
		$attachment->content_type = $handler->getContentType();
		$attachment->temp_hash = $tempHash;
		$attachment->save();

		$handler->onNewAttachment($attachment, $file);

		return $attachment;
	}

	public function associateAttachmentsWithContent($tempHash, $contentType, $contentId)
	{
		$associated = 0;

		$attachmentFinder = $this->finder(AttachmentFinder::class)
			->where('temp_hash', $tempHash);

		/** @var Attachment $attachment */
		foreach ($attachmentFinder->fetch() AS $attachment)
		{
			$attachment->content_type = $contentType;
			$attachment->content_id = $contentId;
			$attachment->temp_hash = '';
			$attachment->unassociated = 0;

			$attachment->save();

			$container = $attachment->getContainer();
			$attachment->getHandler()->onAssociation($attachment, $container);

			$associated++;
		}

		return $associated;
	}
}
