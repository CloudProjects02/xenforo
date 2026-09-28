<?php

namespace SynapseThemes\CustomResourceViewPage\XFRM\Pub\Controller;

use XF\Mvc\ParameterBag;

class ResourceItem extends XFCP_ResourceItem
{
    public function actionView(ParameterBag $params)
    {
        // Call the parent actionView to get all the standard data
        $reply = parent::actionView($params);
        
        // Check if this is a successful view response
        if ($reply instanceof \XF\Mvc\Reply\View)
        {
            // Change the template to our custom one
            $reply->setTemplateName('xfdevs_custom_resource_view');
            
            // Add file information to the response
            $resource = $reply->getParam('resource');
            if ($resource && $resource->CurrentVersion)
            {
                // Load attachments with data
                $currentVersion = $resource->CurrentVersion;
                $currentVersion->hydrateRelation('Attachments', $this->finder('XF:Attachment')
                    ->where('content_type', 'resource_version')
                    ->where('content_id', $currentVersion->resource_version_id)
                    ->with('Data')
                    ->fetch()
                );
                
                $fileInfo = $this->getResourceFileInfo($resource);
                $reply->setParam('fileInfo', $fileInfo);
            }
        }
        
        return $reply;
    }
    
    protected function getResourceFileInfo($resource)
    {
        $fileInfo = [
            'size' => null,
            'extension' => null,
            'filename' => null,
            'debug' => [
                'method_called' => 'yes',
                'resource_exists' => $resource ? 'yes' : 'no'
            ]
        ];
        
        // Safety check - ensure we have a valid resource entity
        if (!$resource)
        {
            $fileInfo['debug']['error'] = 'no_resource';
            return $fileInfo;
        }
        
        $currentVersion = $resource->CurrentVersion;
        if (!$currentVersion)
        {
            $fileInfo['debug']['error'] = 'no_current_version';
            return $fileInfo;
        }
        
        $fileInfo['debug']['has_current_version'] = 'yes';
        
        // Try to get attachment data
        $attachments = $currentVersion->Attachments;
        $attachmentCount = $attachments ? $attachments->count() : 0;
        
        if ($attachments && $attachmentCount > 0)
        {
            $attachment = $attachments->first();
            if ($attachment && $attachment->Data)
            {
                $fileInfo['size'] = $attachment->Data->file_size;
                $fileInfo['filename'] = $attachment->Data->filename;
                
                // Extract extension from filename
                $pathInfo = pathinfo($attachment->Data->filename);
                if (isset($pathInfo['extension']))
                {
                    $fileInfo['extension'] = strtolower($pathInfo['extension']);
                }
            }
        }
        
        // Add debug info about the resource and version
        $fileInfo['debug']['resource_type'] = $resource->resource_type;
        $fileInfo['debug']['download_url'] = $currentVersion->download_url;
        $fileInfo['debug']['version_string'] = $currentVersion->version_string;
        $fileInfo['debug']['attachment_count'] = $attachmentCount;
        $fileInfo['debug']['version_id'] = $currentVersion->resource_version_id;
        $fileInfo['debug']['file_count'] = $currentVersion->file_count;
        
        return $fileInfo;
    }


} 