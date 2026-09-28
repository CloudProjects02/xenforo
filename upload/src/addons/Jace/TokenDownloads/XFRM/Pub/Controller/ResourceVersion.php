<?php

namespace Jace\TokenDownloads\XFRM\Pub\Controller;

use XF\Mvc\ParameterBag;

class ResourceVersion extends XFCP_ResourceVersion
{
    public function actionDownload(ParameterBag $params)
    {
        $version = $this->assertViewableVersion($params->resource_version_id);
        $resource = $version->Resource;
        
        $error = null;
        if (!$version->canDownload($error))
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

    protected function shouldUseTokenSystem($resource, $visitor)
    {
        return true;
    }
} 