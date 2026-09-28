<?php

namespace Jace\UpgradeFeatures\Service;

use Jace\UpgradeFeatures\Entity\UpgradeFeatureIcon;

class FeatureIcon extends \XF\Service\AbstractService
{
    /**
     * @var \Jace\UpgradeFeatures\Entity\UpgradeFeatureIcon
     */
    protected $featureIcon;

    protected $logIp = false;
    protected $fileName;
    protected $width;
    protected $height;
    protected $type;
    public $extension;
    protected $error = null;
    protected $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct(\XF\App $app, UpgradeFeatureIcon $featureIcon)
    {
        parent::__construct($app);
        $this->featureIcon = $featureIcon;
    }

    public function getFeatureIcon()
    {
        return $this->featureIcon;
    }

    public function logIp($logIp)
    {
        $this->logIp = $logIp;
        return $this;
    }

    public function getError()
    {
        return $this->error;
    }

    public function setImageFromUpload(\XF\Http\Upload $upload)
    {
        if (!$upload->isValid())
        {
            $this->error = 'Invalid upload';
            return false;
        }

        $extension = strtolower($upload->getExtension());
        if (!in_array($extension, $this->allowedTypes))
        {
            $this->error = 'Invalid file type. Allowed types: ' . implode(', ', $this->allowedTypes);
            return false;
        }

        // Check file size (1MB max)
        if ($upload->getFileSize() > 1024 * 1024)
        {
            $this->error = 'File size too large. Maximum 1MB allowed.';
            return false;
        }

        $this->deleteFeatureIconFiles();
        $this->extension = $extension;
        $this->fileName = $upload->getTempFile();
        
        return true;
    }

    public function updateFeatureIcon()
    {
        if (!$this->fileName || !$this->extension)
        {
            return false;
        }

        $dataFile = $this->featureIcon->getAbstractedFeatureIconPath($this->extension);
        
        if (!\XF\Util\File::copyFileToAbstractedPath($this->fileName, $dataFile))
        {
            $this->error = 'Could not save icon file';
            return false;
        }

        // Update entity
        $this->featureIcon->icon_ext = $this->extension;
        $this->featureIcon->icon_date = \XF::$time;

        if (!$this->featureIcon->save())
        {
            $this->error = 'Could not save feature icon';
            return false;
        }

        if ($this->logIp)
        {
            $ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
            $this->writeIpLog('update', $ip);
        }

        return true;
    }

    public function deleteIcon()
    {
        $this->deleteFeatureIconFiles();

        if ($this->featureIcon && !$this->featureIcon->isDeleted())
        {
            $this->featureIcon->icon_ext = '';
            $this->featureIcon->icon_date = 0;
            $this->featureIcon->save();
        }

        if ($this->logIp)
        {
            $ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
            $this->writeIpLog('delete', $ip);
        }

        return true;
    }

    protected function deleteFeatureIconFiles()
    {
        $icon_ext = $this->featureIcon->icon_ext;

        if ($this->featureIcon->icon_date)
        {
            \XF\Util\File::deleteFromAbstractedPath($this->featureIcon->getAbstractedFeatureIconPath($icon_ext));
        }
    }

    protected function writeIpLog($action, $ip)
    {
        $featureIcon = $this->featureIcon;

        /** @var \XF\Repository\Ip $ipRepo */
        $ipRepo = $this->repository('XF:Ip');
        $ipRepo->logIp(\XF::visitor()->user_id, $ip, 'upgradeFeatures', $featureIcon->feature_id . '_' . $featureIcon->user_upgrade_id, 'icon_' . $action);
    }
}