<?php

namespace DBTech\Shop\Service\Item;

use DBTech\Shop\Entity\Item;
use XF\App;
use XF\Http\Upload;
use XF\Phrase;
use XF\PrintableException;
use XF\Repository\IpRepository;
use XF\Service\AbstractService;
use XF\Util\File;

class IconService extends AbstractService
{
	protected Item $item;
	protected bool $logIp = true;
	protected ?string $fileName = null;
	protected string $extension;
	protected int $width;
	protected int $height;
	protected int $type;
	protected ?Phrase $error = null;
	protected array $allowedTypes = [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];


	/**
	 * @param App $app
	 * @param Item $item
	 */
	public function __construct(App $app, Item $item)
	{
		parent::__construct($app);
		$this->item = $item;
	}

	/**
	 * @return Item
	 */
	public function getItem(): Item
	{
		return $this->item;
	}

	/**
	 * @param bool $logIp
	 *
	 * @return $this
	 */
	public function logIp(bool $logIp): IconService
	{
		$this->logIp = $logIp;

		return $this;
	}

	/**
	 * @return Phrase|null
	 */
	public function getError(): ?Phrase
	{
		return $this->error;
	}

	/**
	 * @param string $fileName
	 *
	 * @return bool
	 * @throws \InvalidArgumentException
	 */
	public function setImage(string $fileName): bool
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

	/**
	 * @param string $fileName
	 *
	 * @return bool
	 */
	public function setSvgImage(string $fileName): bool
	{
		$this->_setImageBypass($fileName);
		return true;
	}

	/**
	 * @param string $fileName
	 *
	 * @return bool
	 */
	public function setWebpImage(string $fileName): bool
	{
		$this->_setImageBypass($fileName);
		return true;
	}

	/**
	 * @param string $fileName
	 *
	 * @return void
	 */
	protected function _setImageBypass(string $fileName): void
	{
		$this->width = \XF::app()->options()->dbtechShopItemIconMaxDimensions['width'] ?:
			\XF::app()->container('avatarSizeMap')['l'];

		$this->height = \XF::app()->options()->dbtechShopItemIconMaxDimensions['height'] ?:
			\XF::app()->container('avatarSizeMap')['l'];

		$this->fileName = $fileName;
	}

	/**
	 * @param Upload $upload
	 *
	 * @return bool
	 * @throws \InvalidArgumentException
	 */
	public function setImageFromUpload(Upload $upload): bool
	{
		$this->extension = strtolower($upload->getExtension());
		if ($this->extension === 'svg')
		{
			return $this->setSvgImage($upload->getTempFile());
		}
		else if ($this->extension === 'webp' && !\XF::isAddOnActive('DBTech/WebP'))
		{
			return $this->setWebpImage($upload->getTempFile());
		}
		else
		{
			$upload->requireImage();

			if (!$upload->isValid($errors))
			{
				$this->error = reset($errors);
				return false;
			}

			return $this->setImage($upload->getTempFile());
		}
	}

	/**
	 * @param string $fileName
	 * @param null $error
	 *
	 * @return bool
	 * @throws \InvalidArgumentException
	 */
	public function validateImageAsIcon(string $fileName, &$error = null): bool
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
		if (!in_array($type, $this->allowedTypes))
		{
			$error = \XF::phrase('provided_file_is_not_valid_image');
			return false;
		}

		[$width, $height] = $imageInfo;

		if (!\XF::app()->imageManager()->canResize($width, $height))
		{
			$error = \XF::phrase('uploaded_image_is_too_big');
			return false;
		}

		$this->width = $width;
		$this->height = $height;
		$this->type = $type;

		return true;
	}

	/**
	 * @return bool
	 * @throws \RuntimeException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function updateIcon(): bool
	{
		if (!$this->fileName)
		{
			throw new \LogicException('No source file for icon set');
		}

		$imageManager = \XF::app()->imageManager();

		if (\XF::app()->options()->offsetExists('dbtechShopItemIconMaxDimensions'))
		{
			$targetWidth = \XF::app()->options()->dbtechShopItemIconMaxDimensions['width'] ?:
				\XF::app()->container('avatarSizeMap')['l'];

			$targetHeight = \XF::app()->options()->dbtechShopItemIconMaxDimensions['height'] ?:
				null;
		}
		else
		{
			$targetWidth = \XF::app()->container('avatarSizeMap')['l'];
			$targetHeight = \XF::app()->container('avatarSizeMap')['l'];
		}

		$outputFile = null;

		if ($this->width != $targetWidth || ($targetHeight !== null && $this->height != $targetHeight))
		{
			$image = $imageManager->imageFromFile($this->fileName);
			if (!$image)
			{
				return false;
			}

			if ($targetHeight === null)
			{
				$image->resizeWidth($targetWidth);
			}
			else
			{
				$image->resizeAndCrop($targetWidth, $targetHeight);
			}

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
			throw new \RuntimeException('Failed to save image to temporary file; check internal_data/data permissions');
		}

		$dataFile = $this->item->getAbstractedIconPath();
		File::copyFileToAbstractedPath($outputFile, $dataFile);

		if (!$this->logIp)
		{
			$this->item->setOption('log_moderator', false);
			$this->item->setOption('is_automated', true);
		}

		$this->item->icon_date = \XF::$time;
		$this->item->save();

		if ($this->logIp)
		{
			$ip = ($this->logIp === true ? \XF::app()->request()->getIp() : $this->logIp);
			$this->writeIpLog('update', $ip);
		}

		return true;
	}

	/**
	 * @return bool
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function deleteIcon(): bool
	{
		$this->deleteIconFiles();

		$this->item->icon_date = 0;
		$this->item->save();

		if ($this->logIp)
		{
			$ip = ($this->logIp === true ? \XF::app()->request()->getIp() : $this->logIp);
			$this->writeIpLog('delete', $ip);
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function deleteIconForItemDelete(): bool
	{
		$this->deleteIconFiles();

		return true;
	}

	/**
	 *
	 */
	protected function deleteIconFiles(): void
	{
		if ($this->item->icon_date)
		{
			File::deleteFromAbstractedPath($this->item->getAbstractedIconPath());
		}
	}

	/**
	 * @param string $action
	 * @param string $ip
	 */
	protected function writeIpLog(string $action, string $ip): void
	{
		$item = $this->item;

		$ipRepo = \XF::app()->repository(IpRepository::class);
		$ipRepo->logIp(\XF::visitor()->user_id, $ip, 'dbtech_shop_item', $item->item_id, 'icon_' . $action);
	}
}