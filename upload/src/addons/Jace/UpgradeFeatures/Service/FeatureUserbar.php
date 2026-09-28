<?php

namespace Jace\UpgradeFeatures\Service;

use Jace\UpgradeFeatures\Entity\UpgradeFeatureUserbar;

class FeatureUserbar extends \XF\Service\AbstractService
{
    /**
     * @var \Jace\UpgradeFeatures\Entity\UpgradeFeatureUserbar
     */
    protected $featureUserbar;

    protected $logIp = false;
    protected $fileName;
    protected $width;
    protected $height;
    protected $type;
    public $extension;
    protected $error = null;
    protected $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct(\XF\App $app, UpgradeFeatureUserbar $featureUserbar)
    {
        parent::__construct($app);
        $this->featureUserbar = $featureUserbar;
    }

    public function getFeatureUserbar()
    {
        return $this->featureUserbar;
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

        $this->deleteFeatureIconFiles();
        $this->extension = $extension;
        $this->fileName = $upload->getTempFile();
        
        return true;
    }

    public function updateFeatureUserbar()
    {
        if (!$this->fileName || !$this->extension)
        {
            return false;
        }

        $dataFile = $this->featureUserbar->getAbstractedFeatureIconPath($this->extension);
        
        if (!\XF\Util\File::copyFileToAbstractedPath($this->fileName, $dataFile))
        {
            $this->error = 'Could not save userbar file';
            return false;
        }

        // Update entity
        $this->featureUserbar->icon_ext = $this->extension;
        $this->featureUserbar->icon_date = \XF::$time;

        if (!$this->featureUserbar->save())
        {
            $this->error = 'Could not save feature userbar';
            return false;
        }

        if ($this->logIp)
        {
            $ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
            $this->writeIpLog('update', $ip);
        }

        return true;
    }

    public function deleteUserbar()
    {
        $this->deleteFeatureIconFiles();

        if ($this->featureUserbar && !$this->featureUserbar->isDeleted())
        {
            $this->featureUserbar->icon_ext = '';
            $this->featureUserbar->icon_date = 0;
            $this->featureUserbar->save();
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
        $icon_ext = $this->featureUserbar->icon_ext;

        if ($this->featureUserbar->icon_date)
        {
            \XF\Util\File::deleteFromAbstractedPath($this->featureUserbar->getAbstractedFeatureIconPath($icon_ext));
        }
    }

    protected function writeIpLog($action, $ip)
    {
        $featureUserbar = $this->featureUserbar;

        /** @var \XF\Repository\Ip $ipRepo */
        $ipRepo = $this->repository('XF:Ip');
        $ipRepo->logIp(\XF::visitor()->user_id, $ip, 'upgradeFeatures', $featureUserbar->feature_id . '_' . $featureUserbar->user_upgrade_id, 'userbar_' . $action);
    }
}