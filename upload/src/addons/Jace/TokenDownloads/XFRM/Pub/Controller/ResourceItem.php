<?php

namespace Jace\TokenDownloads\XFRM\Pub\Controller;

use XF\Mvc\ParameterBag;

class ResourceItem extends XFCP_ResourceItem
{
    public function actionDownload(ParameterBag $params)
    {
        $resource = $this->assertViewableResource($params->resource_id);
        $version = $resource->CurrentVersion;

        $error = null;
        if (!$version || !$version->canDownload($error))
        {
            return $this->noPermission($error);
        }

        $visitor = \XF::visitor();
        
        // Check if this should use token system instead of normal permissions
        $shouldUseTokens = $this->shouldUseTokenSystem($resource, $visitor);
        
        if ($shouldUseTokens)
        {
            // Guest users cannot use tokens
            if (!$visitor->user_id)
            {
                return $this->noPermission(\XF::phrase('jace_token_downloads_guests_cannot_use_tokens'));
            }
            
            // Check if the user has available tokens
            /** @var \Jace\TokenDownloads\Repository\Purchase $purchaseRepo */
            $purchaseRepo = $this->repository('Jace\TokenDownloads:Purchase');
            
            if (!$purchaseRepo->canUserDownloadResource($visitor->user_id, $resource->resource_id))
            {
                return $this->error('You do not have enough tokens to download this resource.');
            }
            
            // Automatically use a token for the download
            if (!$purchaseRepo->useTokenForDownload($visitor->user_id, $resource, $version))
            {
                return $this->error('You do not have enough tokens to download this resource.');
            }
        }

        // Continue with the normal download process
        return parent::actionDownload($params);
    }
    
    /**
     * Determine if this resource should use the token system
     */
    protected function shouldUseTokenSystem($resource, $visitor)
    {
        return true;
    }
}