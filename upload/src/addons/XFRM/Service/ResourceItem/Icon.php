<?php

namespace XFRM\Service\ResourceItem;

use XF\App;
use XF\Http\Upload;
use XF\Repository\Ip;
use XF\Service\AbstractService;
use XF\Util\File;
use XFRM\Entity\ResourceItem;

use function in_array;

class Icon extends AbstractService
{
	/**
	 * @var ResourceItem
	 */
	protected $resource;

	protected $logIp = true;

	protected $fileName;

	protected $width;

	protected $height;

	protected $type;

	protected $error = null;

	protected $allowedTypes = [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

	public function __construct(App $app, ResourceItem $resource)
	{
		parent::__construct($app);
		$this->resource = $resource;
	}

	public function getResource()
	{
		return $this->resource;
	}

	public function logIp($logIp)
	{
		$this->logIp = $logIp;
	}

	public function getError()
	{
		return $this->error;
	}

	public function setImage($fileName)
	{
		if (!$this->validateImageAsIcon($fileName, $error))
		{
			$this->error = $error;
			$this->fileName = null;
			return false;
		}

		$this->fileName = $fileName;
		return true;
	}

	public function setImageFromUpload(Upload $upload)
	{
		$upload->requireImage();

		if (!$upload->isValid($errors))
		{
			$this->error = reset($errors);
			return false;
		}

		return $this->setImage($upload->getTempFile());
	}

	public function setImageFromExisting(): bool
	{
		$path = $this->resource->getAbstractedIconPath();
		if (!$this->app->fs()->has($path))
		{
			throw new \InvalidArgumentException(
				"Resource does not have an icon ({$path})"
			);
		}

		$tempFile = File::copyAbstractedPathToTempFile($path);

		return $this->setImage($tempFile);
	}

	public function validateImageAsIcon($fileName, &$error = null)
	{
		$error = null;

		if (!file_exists($fileName))
		{
			throw new \InvalidArgumentException("Invalid file '$fileName' passed to icon service");
		}
		if (!is_readable($fileName))
		{
			throw new \InvalidArgumentException("'$fileName' passed to icon service is not readable");
		}

		$imageInfo = filesize($fileName) ? getimagesize($fileName) : false;
		if (!$imageInfo)
		{
			$error = \XF::phrase('provided_file_is_not_valid_image');
			return false;
		}

		$type = $imageInfo[2];
		if (!in_array($type, $this->allowedTypes, true))
		{
			$error = \XF::phrase('provided_file_is_not_valid_image');
			return false;
		}

		$width = $imageInfo[0];
		$height = $imageInfo[1];

		if (!$this->app->imageManager()->canResize($width, $height))
		{
			$error = \XF::phrase('uploaded_image_is_too_big');
			return false;
		}

		$this->width = $width;
		$this->height = $height;
		$this->type = $type;

		return true;
	}

	public function updateIcon()
	{
		if (!$this->fileName)
		{
			throw new \LogicException("No source file for icon set");
		}

		$imageManager = $this->app->imageManager();

		$targetSize = $this->app->container('xfrmIconSizeMap')['m'];
		$outputFile = null;

		$baseImage = $imageManager->imageFromFile($this->fileName);
		if (!$baseImage)
		{
			return false;
		}

		$isOptimized = $baseImage->getType() === IMAGETYPE_WEBP;
		unset($baseImage);

		if ($this->width != $targetSize || $this->height != $targetSize)
		{
			$image = $imageManager->imageFromFile($this->fileName);
			if (!$image)
			{
				return false;
			}

			$image->resizeAndCrop($targetSize);

			$newTempFile = File::getTempFile();
			if ($newTempFile && $image->save($newTempFile))
			{
				$outputFile = $newTempFile;
			}
		}
		else
		{
			$outputFile = $this->fileName;
		}

		if (!$outputFile)
		{
			throw new \RuntimeException("Failed to save image to temporary file; check internal_data/data permissions");
		}

		$dataFile = $this->resource->getAbstractedIconPath();
		File::copyFileToAbstractedPath($outputFile, $dataFile);

		$this->resource->bulkSet([
			'icon_date' => \XF::$time,
			'icon_optimized' => $isOptimized,
		]);
		$this->resource->save();

		if ($this->logIp)
		{
			$ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
			$this->writeIpLog('update', $ip);
		}

		return true;
	}

	public function optimizeExistingIcon(): void
	{
		if ($this->app->options()->imageOptimization !== 'optimize')
		{
			return;
		}

		$this->setImageFromExisting();

		$imageManager = $this->app->imageManager();

		$image = $imageManager->imageFromFile($this->fileName);
		if (!$image)
		{
			return;
		}

		$success = $image->optimizeImage($this->fileName);
		if ($success)
		{
			$this->updateIcon();
		}
	}

	public function deleteIcon()
	{
		$this->deleteIconFiles();

		$this->resource->bulkSet([
			'icon_date' => 0,
			'icon_optimized' => false,
		]);
		$this->resource->save();

		if ($this->logIp)
		{
			$ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
			$this->writeIpLog('delete', $ip);
		}

		return true;
	}

	public function deleteIconForResourceDelete()
	{
		$this->deleteIconFiles();

		return true;
	}

	protected function deleteIconFiles()
	{
		if ($this->resource->icon_date)
		{
			File::deleteFromAbstractedPath($this->resource->getAbstractedIconPath());
		}
	}

	protected function writeIpLog($action, $ip)
	{
		$resource = $this->resource;

		/** @var Ip $ipRepo */
		$ipRepo = $this->repository('XF:Ip');
		$ipRepo->logIp(\XF::visitor()->user_id, $ip, 'resource', $resource->resource_id, 'avatar_' . $action);
	}
}
