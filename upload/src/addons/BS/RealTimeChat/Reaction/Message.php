<?php

namespace BS\RealTimeChat\Reaction;

use XF\Entity\ReactionContent;
use XF\Mvc\Entity\Entity;
use XF\Reaction\AbstractHandler;

class Message extends AbstractHandler
{
    public function reactionsCounted(Entity $entity)
    {
        return \XF::options()->realTimeChatCountReactions;
    }

    public function publishReactionNewsFeed(\XF\Entity\User $sender, $contentId, Entity $content, $reactionId)
    {
        // We don't want to publish reaction news feed items for this content type
    }

    public function unpublishReactionNewsFeed(ReactionContent $reactionContent)
    {
        // We don't want to publish reaction news feed items for this content type
    }
}
