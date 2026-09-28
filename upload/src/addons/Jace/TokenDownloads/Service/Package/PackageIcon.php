<?php

namespace Jace\TokenDownloads\Service\Package;

use Jace\TokenDownloads\Entity\Package;

class PackageIcon extends \XF\Service\AbstractService
{
    /**
     * @var \Jace\TokenDownloads\Entity\Package
     */
    protected $package;

    protected $logIp = false;

    protected $fileName;

    protected $width;

    protected $height;

    protected $type;

    public $extension;

    protected $error = null;

    protected $allowedTypes = [];

    public function __construct(\XF\App $app, Package $package)
    {
        parent::__construct($app);
        $this->package = $package;
    }

    public function getPackage()
    {
        return $this->package;
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

    public function setImageFromUpload(\XF\Http\Upload $upload)
    {
        $upload->requireImage();

        if (!$upload->isValid($errors))
        {
            $this->error = reset($errors);
            return false;
        }

        $this->deletePackageIconFiles();
        $this->extension = $upload->getExtension();

        return $this->setImage($upload->getTempFile());
    }

    public function validateImageAsIcon($fileName, &$error = null)
    {
        $error = null;

        if (!file_exists($fileName))
        {
            throw new \InvalidArgumentException("Invalid file '$fileName' passed to package icon service");
        }
        if (!is_readable($fileName))
        {
            throw new \InvalidArgumentException("'$fileName' passed to package icon service is not readable");
        }

        $imageInfo = filesize($fileName) ? getimagesize($fileName) : false;
        if (!$imageInfo)
        {
            $error = \XF::phrase('provided_file_is_not_valid_image');
            return false;
        }

        $type = $imageInfo[2];
        if (!in_array($type, [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG]))
        {
            $error = \XF::phrase('provided_file_is_not_valid_image');
            return false;
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];

        $this->type = $type;
        $this->width = $width;
        $this->height = $height;

        return true;
    }

    public function updatePackageIcon()
    {
        if (!$this->fileName)
        {
            throw new \LogicException("No source file for package icon set");
        }

        $outputFile = \XF\Util\File::getTempFile();

        $imageManager = $this->app->imageManager();
        $image = $imageManager->imageFromFile($this->fileName);
        if (!$image)
        {
            return false;
        }

        $image->save($outputFile, null, 85);

        $dataFile = $this->package->getAbstractedPackageIconPath($this->extension);
        \XF\Util\File::copyFileToAbstractedPath($outputFile, $dataFile);

        $this->package->package_icon_ext = $this->extension;
        $this->package->package_icon_date = \XF::$time;
        $this->package->save();

        if ($this->logIp)
        {
            $ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
            $this->writeIpLog('update', $ip);
        }

        return true;
    }

    public function deletePackageIcon()
    {
        $this->deletePackageIconFiles();

        if ($this->package && !$this->package->isDeleted())
        {
            $this->package->package_icon_ext = '';
            $this->package->package_icon_date = 0;
            $this->package->save();
        }

        if ($this->logIp)
        {
            $ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
            $this->writeIpLog('delete', $ip);
        }

        return true;
    }

    public function deletePackageIconForResourceDelete()
    {
        $this->deletePackageIconFiles();

        return true;
    }

    protected function deletePackageIconFiles()
    {
        $package_ext = $this->package->package_icon_ext;

        if ($this->package->package_icon_date)
        {
            \XF\Util\File::deleteFromAbstractedPath($this->package->getAbstractedPackageIconPath($package_ext));
        }
    }

    protected function writeIpLog($action, $ip)
    {
        $package = $this->package;

        /** @var \XF\Repository\Ip $ipRepo */
        $ipRepo = $this->repository('XF:Ip');
        $ipRepo->logIp(\XF::visitor()->user_id, $ip, 'jace_token_downloads', $package->package_id, 'icon_' . $action);
    }
}