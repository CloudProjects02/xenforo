<?php

namespace SynapseThemes\XFRMIndexSliders\XFRM\Entity;

class ResourceItem extends XFCP_ResourceItem
{
    protected function getSizeMap()
    {
        return [
            's' => 256,  // Small icon size
            'm' => 512,  // Medium icon size
            'l' => 768,  // Large icon size
            'h' => 1024  // High/extra large icon size
        ];
    }

    public function getIconUrl($sizeCode = null, $canonical = false)
    {
        $app = $this->app();

        if ($this->icon_date)
        {
            $group = floor($this->resource_id / 1000);
            $sizeMap = $this->getSizeMap();
            
            // If size code is provided and exists in our size map, use it
            if ($sizeCode && isset($sizeMap[$sizeCode]))
            {
                $size = $sizeMap[$sizeCode];
                return $app->applyExternalDataUrl(
                    "resource_icons/{$group}/{$this->resource_id}_{$size}x{$size}.jpg?{$this->icon_date}",
                    $canonical
                );
            }
            
            // Default to medium size if no size specified or size not found
            $defaultSize = $sizeMap['m'];
            return $app->applyExternalDataUrl(
                "resource_icons/{$group}/{$this->resource_id}_{$defaultSize}x{$defaultSize}.jpg?{$this->icon_date}",
                $canonical
            );
        }
        
        return null;
    }

    public function getAbstractedIconPath($sizeCode = null)
    {
        $group = floor($this->resource_id / 1000);
        $sizeMap = $this->app()->container('xfrmIconSizeMap');
        
        if ($sizeCode && isset($sizeMap[$sizeCode]))
        {
            $size = $sizeMap[$sizeCode];
            $width = is_array($size) ? $size['width'] : $size;
            $height = is_array($size) ? $size['height'] : $size;
            
            return "data://resource_icons/{$group}/{$this->resource_id}_{$width}x{$height}.jpg";
        }
        
        // Default to medium size if no size specified or size not found
        $defaultSize = $sizeMap['m'];
        $width = is_array($defaultSize) ? $defaultSize['width'] : $defaultSize;
        $height = is_array($defaultSize) ? $defaultSize['height'] : $defaultSize;
        
        return "data://resource_icons/{$group}/{$this->resource_id}_{$width}x{$height}.jpg";
    }

    public function getAbstractedIconPathForSize($size)
    {
        $sizeMap = $this->getSizeMap();
        $sizeValue = isset($sizeMap[$size]) ? $sizeMap[$size] : $size;
        return sprintf('data://resource_icons/%d/%d_%dx%d.jpg',
            floor($this->resource_id / 1000),
            $this->resource_id,
            $sizeValue,
            $sizeValue
        );
    }
} 