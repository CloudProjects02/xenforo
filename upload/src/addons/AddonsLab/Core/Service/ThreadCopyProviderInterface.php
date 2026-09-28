<?php
namespace AddonsLab\Core\Service;

interface ThreadCopyProviderInterface
{
    public function copyThread($threadId, $targetNodeId, $newMessage = '');
}