<?php

namespace Jace\TokenDownloads\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $package_id
 * @property string $title
 * @property string $description
 * @property float $cost_amount
 * @property string $cost_currency
 * @property int $download_limit
 * @property bool $active
 * @property int $display_order
 * @property array|null $payment_profile_ids
 */
class Package extends Entity
{
    public function canPurchase()
    {
        return $this->active && $this->download_limit > 0;
    }

    public function getAbstractedPackageIconPath($extension)
    {
        $packageId = $this->package_id;

        return sprintf('data://jace/tokendownloads/icons/%d.%s',
            $packageId,
            $extension
        );
    }

    public function getIconUrl($sizeCode = null, $canonical = false)
    {
        $app = $this->app();

        if ($this->package_icon_date)
        {
            $extension = $this->package_icon_ext;

            return $app->applyExternalDataUrl(
                "jace/tokendownloads/icons/{$this->package_id}.{$extension}?{$this->package_icon_date}",
                $canonical
            );
        }
        else
        {
            return null;
        }
    }

    protected function _postDelete()
    {
        $packageService = $this->app()->service('Jace\TokenDownloads:Package\PackageIcon', $this);
        $packageService->deletePackageIcon();

    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'jace_token_package';
        $structure->shortName = 'Jace\TokenDownloads:Package';
        $structure->primaryKey = 'package_id';
        $structure->columns = [
            'package_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'title' => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
            'package_icon_ext' => ['type' => self::STR, 'default' => ''],
            'package_icon_date' => ['type' => self::UINT, 'default' => 0],
            'description' => ['type' => self::STR, 'required' => true],
            'cost_amount' => ['type' => self::FLOAT, 'required' => true, 'min' => 0],
            'cost_currency' => ['type' => self::STR, 'required' => true, 'maxLength' => 3],
            'download_limit' => ['type' => self::UINT, 'required' => true, 'min' => 0],
            'active' => ['type' => self::BOOL, 'default' => true],
            'display_order' => ['type' => self::UINT, 'default' => 0],
            'payment_profile_ids' => ['type' => self::LIST_COMMA, 'default' => null]
        ];
        $structure->getters = [
            'icon_url' => true,
        ];
        $structure->relations = [];

        return $structure;
    }
} 