<?php

namespace Jace\LatestUserActivity\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View as ViewReply;

class Member extends XFCP_Member
{
    public function actionView(ParameterBag $params)
    {
        $reply = parent::actionView($params);

        if (!($reply instanceof ViewReply))
        {
            return $reply;
        }

        /** @var \XF\Entity\User|null $profileUser */
        $profileUser = $reply->getParam('user');
        if (!$profileUser || !$profileUser->canViewLatestActivity())
        {
            return $reply;
        }

        $limit    = 20;
        $beforeId = (int) $this->filter('before_id', 'uint');

        // Fetch this member's feed entries
        $finder = $this->finder('XF:NewsFeed')
            ->where('user_id', $profileUser->user_id)   // actor is this user
            ->order('news_feed_id', 'DESC')
            ->with('User') // eager-load actor
            ->limit($limit);

        if ($beforeId)
        {
            $finder->where('news_feed_id', '<', $beforeId);
        }

        /** @var \XF\Entity\NewsFeed[]|\XF\Mvc\Entity\ArrayCollection $entries */
        $entries = $finder->fetch();

        $app       = \XF::app();
        $templater = $app->templater();
        $router    = $app->router('public');
        $formatter = $app->stringFormatter();

        // Add-on manager (XF 2.2 way to check XFRM presence/active)
        $am = $app->addOnManager();
        $xfrmAddOn = $am->getById('XFRM');
        $xfrmActive = ($xfrmAddOn && $xfrmAddOn->isInstalled() && $xfrmAddOn->isActive());

        $items = [];

        foreach ($entries as $e)
        {
            $actor     = $e->User;
            $actorName = $actor ? $actor->username : (string)\XF::phrase('guest');

            $base = [
                'news_feed_id' => $e->news_feed_id,
                'timestamp'    => $e->event_date,
                'actorName'    => $actorName,
                'actorProfileUrl' => $actor ? $router->buildLink('members', $actor) : '#',
                'actorAvatarUrl'  => $actor ? $actor->getAvatarUrl('s') : '',
                'actor'        => $actor, // if you want to use <xf:avatar> in template later
                'iconClass'    => null,
                'title'        => null,
                'titleUrl'     => null,
                'summaryHtml'  => null,   // safe HTML string
                'contentSnippet' => null,   // safe HTML string
                'thumbUrl'     => null,
                'tags'         => [],
                'isAction'     => false,
                // Optional time strings if your template wants them precomputed:
                'dateIso'      => date('c', $e->event_date),
                'dateTitle'    => \XF::language()->dateTime($e->event_date),
                'dateShort'    => \XF::language()->date($e->event_date, 'M j'),
                'dateLong'     => \XF::language()->date($e->event_date, 'monthDay'),
            ];

            $type       = $e->content_type;
            $action     = $e->action;
            $extra      = is_array($e->extra_data) ? $e->extra_data : [];

            // Dynamic content type handling
            $content = $this->getContentForFeedEntry($e, $type);
            
            if ($content)
            {
                $this->processContentType($content, $type, $action, $base, $router, $formatter, $app, $e);
            }
            else
            {
                // Fallback for unknown content types
                $base['isAction'] = true;
                $base['summaryHtml'] = ' ' . htmlspecialchars(ucfirst($type) . ' ' . $action);
            }

            if ($base['summaryHtml'] || $base['title'])
            {
                $items[] = $base;
            }
        }

        // expose to template
        $reply->setParam('xfdevsItems', $items);

        // Get followers data
        $followersData = $this->getFollowersData($profileUser);
        $reply->setParam('followersData', $followersData);

        // Get following data
        $followingData = $this->getFollowingData($profileUser);
        $reply->setParam('followingData', $followingData);

        // Get level badge data
        $levelBadgeData = $this->getLevelBadgeData($profileUser);
        $reply->setParam('levelBadgeData', $levelBadgeData);

        if ($entries->count())
        {
            /** @var \XF\Entity\NewsFeed $last */
            $last = $entries->last();
            $reply->setParam('newsFeedOldestItemId', $last->news_feed_id);
        }
        else
        {
            $reply->setParam('newsFeedOldestItemId', 0);
        }

        return $reply;
    }

    /**
     * Wrap username as a linked anchor for your theme.
     */
    protected function wrapUser(?\XF\Entity\User $user, \XF\Mvc\Router $router): string
    {
        if (!$user)
        {
            return '<span class="xfdevs__username">' . htmlspecialchars((string)\XF::phrase('guest')) . '</span>';
        }

        $url  = htmlspecialchars($router->buildLink('members', $user));
        $name = htmlspecialchars($user->username);

        return sprintf('<a href="%s" class="xfdevs__username">%s</a>', $url, $name);
    }

    /**
     * Get content entity for a feed entry
     */
    protected function getContentForFeedEntry(\XF\Entity\NewsFeed $entry, string $type)
    {
        // Get the entity class for this content type
        $entityClass = \XF::app()->getContentTypeEntity($type, false);
        if (!$entityClass)
        {
            return null;
        }

        // Get the appropriate handler for this content type
        $handler = $this->getContentTypeHandler($type);
        
        // Use the handler's getContent method or fallback to direct entity loading
        if ($handler && method_exists($handler, 'getContent'))
        {
            $content = $handler->getContent($entry->content_id);
        }
        else
        {
            // Fallback: load entity directly with common relations
            $relations = $this->getDefaultRelationsForType($type);
            $content = \XF::app()->findByContentType($type, $entry->content_id, $relations);
        }

        // Clean the content by removing image BBCode tags from message fields
        if ($content)
        {
            $this->cleanContentMessage($content, $type);
        }

        return $content;
    }

    /**
     * Clean message content by removing image BBCode tags
     */
    protected function cleanContentMessage($content, string $type): void
    {
        // Define message fields to clean for different content types
        $messageFields = ['message', 'content', 'description'];
        
        // Add nested message fields for specific content types
        if ($type === 'thread' && isset($content->FirstPost))
        {
            $messageFields[] = 'FirstPost.message';
        }
        elseif ($type === 'resource_update' && isset($content->Description))
        {
            $messageFields[] = 'Description.message';
        }

        foreach ($messageFields as $field)
        {
            if (strpos($field, '.') !== false)
            {
                // Handle nested fields like FirstPost.message
                $parts = explode('.', $field);
                $obj = $content;
                foreach ($parts as $part)
                {
                    if (!$obj || !isset($obj->$part))
                    {
                        $obj = null;
                        break;
                    }
                    $obj = $obj->$part;
                }
                
                if ($obj && isset($obj->message))
                {
                    $obj->message = $this->removeImageBbCode($obj->message);
                }
            }
            elseif (isset($content->$field))
            {
                // Handle direct fields like message, content, description
                $content->$field = $this->removeImageBbCode($content->$field);
            }
        }
    }

    /**
     * Get content type handler
     */
    protected function getContentTypeHandler(string $type)
    {
        $app = \XF::app();
        
        // Try to get various handlers in order of preference
        $handlerTypes = [
            'news_feed_handler_class',
            'tag_handler_class', 
            'attachment_handler_class',
            'like_handler_class',
            'reaction_handler_class'
        ];

        foreach ($handlerTypes as $handlerField)
        {
            $handlerClass = $app->getContentTypeFieldValue($type, $handlerField);
            if ($handlerClass && class_exists($handlerClass))
            {
                $handlerClass = \XF::extendClass($handlerClass);
                return new $handlerClass($type);
            }
        }

        return null;
    }

    /**
     * Get default relations for content type
     */
    protected function getDefaultRelationsForType(string $type): array
    {
        // Common relations that most content types might need
        $commonRelations = ['User'];
        
        // Type-specific relations
        $typeRelations = [
            'thread' => ['Forum', 'FirstPost', 'FirstPost.Attachments'],
            'post' => ['Thread', 'Thread.Forum', 'Attachments'],
            'profile_post' => ['ProfileUser', 'Attachments'],
            'resource' => ['Category', 'CurrentVersion', 'Description'],
            'resource_update' => ['Resource', 'Resource.Category', 'Attachments'],
            'dbtech_shop_item' => ['Category'],
            'conversation' => ['User'],
            'conversation_message' => ['Conversation', 'User', 'Attachments'],
            'attachment' => ['Data'],
            'bookmark_item' => ['User'],
            'user' => ['Profile'],
            'profile_post_comment' => ['ProfilePost', 'ProfilePost.ProfileUser'],
            'poll' => ['Thread', 'Thread.Forum'],
            'tag' => ['User'],
            'warning' => ['User', 'WarningUser'],
            'user_upgrade' => ['User'],
            'trophy' => ['User'],
            'user_ban' => ['User', 'BanUser'],
            'user_ignored' => ['User', 'IgnoredUser'],
            'user_follow' => ['User', 'FollowUser'],
            'username_change' => ['User'],
            'edit_history' => ['User'],
            'deletion_log' => ['User'],
            'moderator_log' => ['User'],
            'admin_log' => ['User'],
            'ip' => ['User'],
            'feed' => ['User'],
            'cookie_consent_log' => ['User'],
            'error_log' => ['User'],
            'spam_cleaner_log' => ['User', 'ApplyingUser'],
            'spam_trigger_log' => ['User'],
            'content_vote' => ['User', 'ContentUser'],
            'poll_vote' => ['User'],
            'thread_reply_ban' => ['User', 'BanUser'],
            'thread_question' => ['User', 'SolutionUser'],
            'thread_user_post' => ['User'],
            'thread_watch' => ['User'],
            'forum_watch' => ['User'],
            'user_alert' => ['User'],
            'user_trophy' => ['User'],
            'user_upgrade_active' => ['User'],
            'user_upgrade_expired' => ['User'],
        ];

        return array_merge($commonRelations, $typeRelations[$type] ?? []);
    }

    /**
     * Process content type dynamically
     */
    protected function processContentType($content, string $type, string $action, array &$base, \XF\Mvc\Router $router, \XF\Str\Formatter $formatter, \XF\App $app, \XF\Entity\NewsFeed $entry)
    {
        if (!$content)
        {
            $base['isAction'] = true;
            $base['summaryHtml'] = ' ' . htmlspecialchars(ucfirst($type) . ' ' . $action);
            return;
        }

        // Special handling for reactions
        if ($action === 'reaction')
        {
            $this->processReactionContent($content, $type, $base, $router, $formatter, $app, $entry);
            return;
        }

        // Special handling for user actions
        if ($type === 'user')
        {
            $this->processUserAction($content, $action, $base, $router, $entry);
            return;
        }

        // Get content type info from XenForo's system
        $contentTypeInfo = $this->getContentTypeInfo($type);
        
        // Set basic properties
        $base['iconClass'] = $this->getContentTypeIcon($type);
        $base['title'] = $this->getContentTitle($content, $type);
        $base['titleUrl'] = $this->getContentUrl($content, $type, $router);

        // Get category/location info
        $categoryInfo = $this->getCategoryInfo($content, $type, $router);
        
        // Generate summary text
        $base['summaryHtml'] = $this->generateSummaryText($action, $type, $categoryInfo, $content);

        // Get content snippet
        $snippetData = $this->getContentSnippet($content, $type, $formatter, $app);
        if ($snippetData)
        {
            // Render the text-only BBCode snippet
            $base['contentSnippet'] = $this->renderBbCodeWithoutImages($snippetData[0], $type, $content, $app);
            
            // If we have extracted images, use the first one as thumbnail
            if (!empty($snippetData[1]))
            {
                $base['thumbUrl'] = $snippetData[1][0];
            }
        }

        // Get thumbnail - only if we don't have one from extracted images
        if (!isset($base['thumbUrl']))
        {
            $thumbUrl = $this->getContentThumbnail($content, $type);
            if ($thumbUrl)
            {
                $base['thumbUrl'] = $thumbUrl;
            }
        }

        // Get tags
        $tags = $this->getContentTags($content, $type, $router);
        if ($tags)
        {
            $base['tags'] = $tags;
        }
    }

    /**
     * Process user-specific actions
     */
    protected function processUserAction($content, string $action, array &$base, \XF\Mvc\Router $router, \XF\Entity\NewsFeed $entry)
    {
        $base['isAction'] = true;
        $base['iconClass'] = $this->getContentTypeIcon('user');

        switch ($action)
        {
            case 'follow':
                $targetUserId = $entry->extra_data['target_user_id'] ?? null;
                if ($targetUserId)
                {
                    $targetUser = $this->em()->find('XF:User', $targetUserId);
                    if ($targetUser)
                    {
                        $base['title'] = $targetUser->username;
                        $base['titleUrl'] = $router->buildLink('members', $targetUser);
                        $base['summaryHtml'] = ' started following ' . $this->wrapUser($targetUser, $router);
                    }
                    else
                    {
                        $base['summaryHtml'] = ' started following a user';
                    }
                }
                else
                {
                    $base['summaryHtml'] = ' started following a user';
                }
                break;

            case 'avatar_change':
                $base['summaryHtml'] = ' changed their profile photo';
                break;

            case 'username_change':
                $oldUsername = $entry->extra_data['old_username'] ?? '';
                $newUsername = $entry->extra_data['new_username'] ?? '';
                if ($oldUsername && $newUsername)
                {
                    $base['summaryHtml'] = sprintf(
                        ' changed username from <span class="xfdevs__username">%s</span> to <span class="xfdevs__username">%s</span>',
                        htmlspecialchars($oldUsername),
                        htmlspecialchars($newUsername)
                    );
                }
                else
                {
                    $base['summaryHtml'] = ' changed their username';
                }
                break;

            case 'registration':
                $base['summaryHtml'] = ' joined the community';
                break;

            case 'email_change':
                $base['summaryHtml'] = ' changed their email address';
                break;

            case 'password_change':
                $base['summaryHtml'] = ' changed their password';
                break;

            case 'tfa_enable':
                $base['summaryHtml'] = ' enabled two-factor authentication';
                break;

            case 'tfa_disable':
                $base['summaryHtml'] = ' disabled two-factor authentication';
                break;

            case 'privacy_change':
                $base['summaryHtml'] = ' updated their privacy settings';
                break;

            case 'preference_change':
                $base['summaryHtml'] = ' updated their preferences';
                break;

            case 'signature_change':
                $base['summaryHtml'] = ' updated their signature';
                break;

            case 'custom_title_change':
                $base['summaryHtml'] = ' updated their custom title';
                break;

            case 'location_change':
                $base['summaryHtml'] = ' updated their location';
                break;

            case 'website_change':
                $base['summaryHtml'] = ' updated their website';
                break;

            case 'occupation_change':
                $base['summaryHtml'] = ' updated their occupation';
                break;

            case 'about_change':
                $base['summaryHtml'] = ' updated their about information';
                break;

            case 'status_change':
                $base['summaryHtml'] = ' updated their status';
                break;

            default:
                $base['summaryHtml'] = ' ' . htmlspecialchars(ucfirst($action));
                break;
        }
    }

    /**
     * Process reaction content specifically
     */
    protected function processReactionContent($content, string $type, array &$base, \XF\Mvc\Router $router, \XF\Str\Formatter $formatter, \XF\App $app, \XF\Entity\NewsFeed $entry)
    {
        // Get the reaction ID from the news feed entry
        $reactionId = $this->getReactionIdFromNewsFeed($entry);
        if (!$reactionId)
        {
            $base['isAction'] = true;
            $base['summaryHtml'] = ' reacted to content';
            return;
        }

        // Get reaction information
        $reaction = $this->em()->find('XF:Reaction', $reactionId);
        if (!$reaction || !$reaction->active)
        {
            $base['isAction'] = true;
            $base['summaryHtml'] = ' reacted to content';
            return;
        }

        // Set reaction-specific properties
        $base['iconClass'] = $this->getReactionIcon($reaction);
        $base['title'] = $this->getContentTitle($content, $type);
        $base['titleUrl'] = $this->getContentUrl($content, $type, $router);

        // Get category/location info
        $categoryInfo = $this->getCategoryInfo($content, $type, $router);
        
        // Generate reaction summary text
        $base['summaryHtml'] = $this->generateReactionSummaryText($reaction, $type, $categoryInfo, $content);

        // Get content snippet
        $snippetData = $this->getContentSnippet($content, $type, $formatter, $app);
        if ($snippetData)
        {
            // Render the text-only BBCode snippet
            $base['contentSnippet'] = $this->renderBbCodeWithoutImages($snippetData[0], $type, $content, $app);
            
            // If we have extracted images, use the first one as thumbnail
            if (!empty($snippetData[1]))
            {
                $base['thumbUrl'] = $snippetData[1][0];
            }
        }

        // Get thumbnail - only if we don't have one from extracted images
        if (!isset($base['thumbUrl']))
        {
            $thumbUrl = $this->getContentThumbnail($content, $type);
            if ($thumbUrl)
            {
                $base['thumbUrl'] = $thumbUrl;
            }
        }

        // Get tags
        $tags = $this->getContentTags($content, $type, $router);
        if ($tags)
        {
            $base['tags'] = $tags;
        }
    }

    /**
     * Get reaction ID from the current news feed entry
     */
    protected function getReactionIdFromNewsFeed(\XF\Entity\NewsFeed $entry): ?int
    {
        // Get reaction_id from the news feed entry's extra_data
        $extraData = $entry->extra_data;
        if (is_array($extraData) && isset($extraData['reaction_id']))
        {
            return (int)$extraData['reaction_id'];
        }

        return null;
    }

    /**
     * Get reaction icon
     */
    protected function getReactionIcon(\XF\Entity\Reaction $reaction): string
    {
        // Try to get icon from reaction image or use default
        if ($reaction->image_url)
        {
            // For now, use a default reaction icon
            return 'xfdevs__fa-solid xfdevs__fa-heart';
        }

        // Map common reaction types to icons
        $iconMap = [
            'like' => 'xfdevs__fa-solid xfdevs__fa-heart',
            'love' => 'xfdevs__fa-solid xfdevs__fa-heart',
            'laugh' => 'xfdevs__fa-solid xfdevs__fa-laugh',
            'wow' => 'xfdevs__fa-solid xfdevs__fa-surprise',
            'sad' => 'xfdevs__fa-solid xfdevs__fa-sad-tear',
            'angry' => 'xfdevs__fa-solid xfdevs__fa-angry',
        ];

        $reactionText = strtolower($reaction->title ?? '');
        foreach ($iconMap as $key => $icon)
        {
            if (strpos($reactionText, $key) !== false)
            {
                return $icon;
            }
        }

        return 'xfdevs__fa-solid xfdevs__fa-heart';
    }

    /**
     * Generate reaction summary text
     */
    protected function generateReactionSummaryText(\XF\Entity\Reaction $reaction, string $type, ?array $categoryInfo, $content = null): string
    {
        $reactionTitle = $reaction->title ?? 'reacted';
        
        if ($categoryInfo)
        {
            return sprintf(
                ' %s to content in <a href="%s">%s</a>',
                htmlspecialchars($reactionTitle),
                htmlspecialchars($categoryInfo['url']),
                htmlspecialchars($categoryInfo['title'])
            );
        }

        return ' ' . htmlspecialchars($reactionTitle) . ' to content';
    }

    /**
     * Get content type information from XenForo
     */
    protected function getContentTypeInfo(string $type): array
    {
        $app = \XF::app();
        
        return [
            'phrase' => $app->getContentTypePhrase($type),
            'phrase_plural' => $app->getContentTypePhrase($type, true),
            'entity' => $app->getContentTypeEntity($type, false),
            'route' => $app->getContentTypeFieldValue($type, 'route'),
            'public_prefix' => $app->getContentTypeFieldValue($type, 'public_prefix'),
        ];
    }

    /**
     * Get content type icon
     */
    protected function getContentTypeIcon(string $type): string
    {
        // Map content types to icons based on common patterns
        $iconMap = [
            'thread' => 'xfdevs__fa-solid xfdevs__fa-comment',
            'post' => 'xfdevs__fa-regular xfdevs__fa-comments',
            'profile_post' => 'xfdevs__fa-solid xfdevs__fa-comment',
            'profile_post_comment' => 'xfdevs__fa-solid xfdevs__fa-comment-dots',
            'resource' => 'xfdevs__fa-solid xfdevs__fa-download',
            'resource_update' => 'xfdevs__fa-solid xfdevs__fa-download',
            'dbtech_shop_item' => 'xfdevs__fa-solid xfdevs__fa-shopping-cart',
            'user' => 'xfdevs__fa-solid xfdevs__fa-user',
            'conversation' => 'xfdevs__fa-solid xfdevs__fa-envelope',
            'conversation_message' => 'xfdevs__fa-solid xfdevs__fa-envelope-open',
            'attachment' => 'xfdevs__fa-solid xfdevs__fa-paperclip',
            'bookmark_item' => 'xfdevs__fa-solid xfdevs__fa-bookmark',
            'poll' => 'xfdevs__fa-solid xfdevs__fa-poll',
            'tag' => 'xfdevs__fa-solid xfdevs__fa-tag',
            'warning' => 'xfdevs__fa-solid xfdevs__fa-exclamation-triangle',
            'user_upgrade' => 'xfdevs__fa-solid xfdevs__fa-crown',
            'trophy' => 'xfdevs__fa-solid xfdevs__fa-trophy',
            'user_ban' => 'xfdevs__fa-solid xfdevs__fa-ban',
            'user_ignored' => 'xfdevs__fa-solid xfdevs__fa-user-slash',
            'user_follow' => 'xfdevs__fa-solid xfdevs__fa-user-plus',
            'username_change' => 'xfdevs__fa-solid xfdevs__fa-user-edit',
            'edit_history' => 'xfdevs__fa-solid xfdevs__fa-history',
            'deletion_log' => 'xfdevs__fa-solid xfdevs__fa-trash',
            'moderator_log' => 'xfdevs__fa-solid xfdevs__fa-gavel',
            'admin_log' => 'xfdevs__fa-solid xfdevs__fa-user-shield',
            'ip' => 'xfdevs__fa-solid xfdevs__fa-network-wired',
            'feed' => 'xfdevs__fa-solid xfdevs__fa-rss',
            'cookie_consent_log' => 'xfdevs__fa-solid xfdevs__fa-cookie-bite',
            'error_log' => 'xfdevs__fa-solid xfdevs__fa-exclamation-circle',
            'spam_cleaner_log' => 'xfdevs__fa-solid xfdevs__fa-shield-alt',
            'spam_trigger_log' => 'xfdevs__fa-solid xfdevs__fa-shield-virus',
            'content_vote' => 'xfdevs__fa-solid xfdevs__fa-thumbs-up',
            'poll_vote' => 'xfdevs__fa-solid xfdevs__fa-vote-yea',
            'thread_reply_ban' => 'xfdevs__fa-solid xfdevs__fa-comment-slash',
            'thread_question' => 'xfdevs__fa-solid xfdevs__fa-question-circle',
            'thread_user_post' => 'xfdevs__fa-solid xfdevs__fa-user-edit',
            'thread_watch' => 'xfdevs__fa-solid xfdevs__fa-eye',
            'forum_watch' => 'xfdevs__fa-solid xfdevs__fa-eye',
            'user_alert' => 'xfdevs__fa-solid xfdevs__fa-bell',
            'user_trophy' => 'xfdevs__fa-solid xfdevs__fa-medal',
            'user_upgrade_active' => 'xfdevs__fa-solid xfdevs__fa-star',
            'user_upgrade_expired' => 'xfdevs__fa-solid xfdevs__fa-star-half-alt',
        ];

        return $iconMap[$type] ?? 'xfdevs__fa-solid xfdevs__fa-file';
    }

    /**
     * Get content title
     */
    protected function getContentTitle($content, string $type): string
    {
        if (!$content)
        {
            return '';
        }

        // Try to get title from common fields
        $titleFields = ['title', 'name', 'subject'];
        foreach ($titleFields as $field)
        {
            if (isset($content->$field))
            {
                return $content->$field;
            }
        }

        // For nested relations, try common patterns
        if ($type === 'post' && isset($content->Thread))
        {
            return $content->Thread->title ?? '';
        }
        elseif ($type === 'resource_update' && isset($content->Resource))
        {
            return $content->Resource->title ?? '';
        }

        // Fallback to content type phrase
        $app = \XF::app();
        return (string)$app->getContentTypePhrase($type);
    }

    /**
     * Get content URL
     */
    protected function getContentUrl($content, string $type, \XF\Mvc\Router $router): string
    {
        if (!$content)
        {
            return '#';
        }

        // Try to get URL from content if it implements LinkableInterface
        if ($content instanceof \XF\Entity\LinkableInterface)
        {
            return $content->getContentUrl();
        }

        // Try common route patterns
        $routeMap = [
            'thread' => 'threads',
            'post' => 'posts',
            'profile_post' => 'profile-posts',
            'resource' => 'resources',
            'resource_update' => 'resources',
            'dbtech_shop_item' => 'dbtech-shop',
        ];

        $route = $routeMap[$type] ?? $type;
        
        try
        {
            return $router->buildLink($route, $content);
        }
        catch (\Exception $e)
        {
            return '#';
        }
    }

    /**
     * Get category information
     */
    protected function getCategoryInfo($content, string $type, \XF\Mvc\Router $router): ?array
    {
        if (!$content)
        {
            return null;
        }

        // Try to find category/location information
        $categoryFields = ['Category', 'Forum', 'Thread.Forum', 'Resource.Category'];
        $category = null;
        $categoryField = null;

        foreach ($categoryFields as $field)
        {
            if (strpos($field, '.') !== false)
            {
                $parts = explode('.', $field);
                $obj = $content;
                foreach ($parts as $part)
                {
                    if (!$obj || !isset($obj->$part))
                    {
                        $obj = null;
                        break;
                    }
                    $obj = $obj->$part;
                }
                if ($obj)
                {
                    $category = $obj;
                    $categoryField = $field;
                    break;
                }
            }
            elseif (isset($content->$field))
            {
                $category = $content->$field;
                $categoryField = $field;
                break;
            }
        }

        if (!$category)
        {
            return null;
        }

        // Determine route based on category type
        $routeMap = [
            'Category' => 'categories',
            'Forum' => 'forums',
            'Thread.Forum' => 'forums',
            'Resource.Category' => 'resources/categories',
        ];

        $route = $routeMap[$categoryField] ?? 'categories';
        
        return [
            'title' => $category->title ?? 'Content',
            'url' => $router->buildLink($route, $category)
        ];
    }

    /**
     * Generate summary text
     */
    protected function generateSummaryText(string $action, string $type, ?array $categoryInfo, $content = null): string
    {
        // Handle special cases
        if ($type === 'profile_post' && $content)
        {
            $targetName = $this->getProfilePostTargetName($content);
            return ' posted on ' . htmlspecialchars($targetName) . '\'s profile';
        }

        // Get action text based on content type
        $actionText = $this->getActionText($action, $type);

        if ($categoryInfo)
        {
            return sprintf(
                ' %s <a href="%s">%s</a>',
                $actionText,
                htmlspecialchars($categoryInfo['url']),
                htmlspecialchars($categoryInfo['title'])
            );
        }

        return ' ' . $actionText;
    }

    /**
     * Get action text
     */
    protected function getActionText(string $action, string $type): string
    {
        $actionMap = [
            'thread' => [
                'insert' => 'posted a topic in',
                'update' => 'updated a topic in'
            ],
            'post' => [
                'insert' => 'replied in',
                'update' => 'edited a reply in'
            ],
            'profile_post' => [
                'insert' => 'posted on',
                'update' => 'updated a post on'
            ],
            'profile_post_comment' => [
                'insert' => 'commented on',
                'update' => 'edited a comment on'
            ],
            'resource' => [
                'insert' => 'posted a file in',
                'update' => 'updated a file in'
            ],
            'resource_update' => [
                'insert' => 'updated a file in',
                'update' => 'updated a file in'
            ],
            'dbtech_shop_item' => [
                'insert' => 'added a new item to',
                'update' => 'updated an item in'
            ],
            'conversation' => [
                'insert' => 'started a conversation',
                'update' => 'updated a conversation'
            ],
            'conversation_message' => [
                'insert' => 'sent a message in',
                'update' => 'edited a message in'
            ],
            'attachment' => [
                'insert' => 'uploaded an attachment',
                'update' => 'updated an attachment'
            ],
            'bookmark_item' => [
                'insert' => 'bookmarked',
                'update' => 'updated a bookmark'
            ],
            'poll' => [
                'insert' => 'created a poll in',
                'update' => 'updated a poll in'
            ],
            'tag' => [
                'insert' => 'tagged',
                'update' => 'updated a tag'
            ],
            'warning' => [
                'insert' => 'received a warning',
                'update' => 'warning was updated'
            ],
            'user_upgrade' => [
                'insert' => 'purchased an upgrade',
                'update' => 'upgrade was updated'
            ],
            'trophy' => [
                'insert' => 'earned a trophy',
                'update' => 'trophy was updated'
            ],
            'user_ban' => [
                'insert' => 'was banned',
                'update' => 'ban was updated'
            ],
            'user_ignored' => [
                'insert' => 'ignored',
                'update' => 'ignore was updated'
            ],
            'user_follow' => [
                'insert' => 'started following',
                'update' => 'follow was updated'
            ],
            'username_change' => [
                'insert' => 'changed username',
                'update' => 'username change was updated'
            ],
            'edit_history' => [
                'insert' => 'edited',
                'update' => 'edit history was updated'
            ],
            'deletion_log' => [
                'insert' => 'deleted',
                'update' => 'deletion was updated'
            ],
            'moderator_log' => [
                'insert' => 'moderated',
                'update' => 'moderation was updated'
            ],
            'admin_log' => [
                'insert' => 'administered',
                'update' => 'admin action was updated'
            ],
            'ip' => [
                'insert' => 'logged IP',
                'update' => 'IP log was updated'
            ],
            'feed' => [
                'insert' => 'created a feed',
                'update' => 'feed was updated'
            ],
            'cookie_consent_log' => [
                'insert' => 'consented to cookies',
                'update' => 'cookie consent was updated'
            ],
            'error_log' => [
                'insert' => 'logged an error',
                'update' => 'error log was updated'
            ],
            'spam_cleaner_log' => [
                'insert' => 'cleaned spam',
                'update' => 'spam cleaning was updated'
            ],
            'spam_trigger_log' => [
                'insert' => 'triggered spam detection',
                'update' => 'spam trigger was updated'
            ],
            'content_vote' => [
                'insert' => 'voted on',
                'update' => 'vote was updated'
            ],
            'poll_vote' => [
                'insert' => 'voted in poll',
                'update' => 'poll vote was updated'
            ],
            'thread_reply_ban' => [
                'insert' => 'banned from replying',
                'update' => 'reply ban was updated'
            ],
            'thread_question' => [
                'insert' => 'asked a question',
                'update' => 'question was updated'
            ],
            'thread_user_post' => [
                'insert' => 'posted in thread',
                'update' => 'thread post was updated'
            ],
            'thread_watch' => [
                'insert' => 'started watching thread',
                'update' => 'thread watch was updated'
            ],
            'forum_watch' => [
                'insert' => 'started watching forum',
                'update' => 'forum watch was updated'
            ],
            'user_alert' => [
                'insert' => 'received an alert',
                'update' => 'alert was updated'
            ],
            'user_trophy' => [
                'insert' => 'earned a trophy',
                'update' => 'trophy was updated'
            ],
            'user_upgrade_active' => [
                'insert' => 'activated upgrade',
                'update' => 'upgrade was updated'
            ],
            'user_upgrade_expired' => [
                'insert' => 'upgrade expired',
                'update' => 'upgrade was updated'
            ],
        ];

        // Special handling for reactions
        if ($action === 'reaction')
        {
            return 'reacted to';
        }

        return $actionMap[$type][$action] ?? $actionMap[$type]['insert'] ?? 'created';
    }

    /**
     * Get content snippet
     */
    protected function getContentSnippet($content, string $type, \XF\Str\Formatter $formatter, \XF\App $app): ?array
    {
        if (!$content)
        {
            return null;
        }

        // Try to find message content
        $messageFields = ['message', 'content', 'description', 'FirstPost.message', 'Description.message'];
        $message = null;

        foreach ($messageFields as $field)
        {
            if (strpos($field, '.') !== false)
            {
                $parts = explode('.', $field);
                $obj = $content;
                foreach ($parts as $part)
                {
                    if (!$obj || !isset($obj->$part))
                    {
                        $obj = null;
                        break;
                    }
                    $obj = $obj->$part;
                }
                if ($obj)
                {
                    $message = $obj;
                    break;
                }
            }
            elseif (isset($content->$field))
            {
                $message = $content->$field;
                break;
            }
        }

        if (!$message)
        {
            return null;
        }

        // Extract images from BBCode before removing them
        $images = $this->extractImagesFromBbCode($message);
        
        // Remove image BBCode tags from the message
        $message = $this->removeImageBbCode($message);
        
        // Create snippet from the cleaned message content
        $snippet = $formatter->snippetString($message, 320);
        
        return [$snippet, $images];
    }

    /**
     * Extract images from BBCode content
     */
    protected function extractImagesFromBbCode(string $message): array
    {
        $images = [];
        
        // Match [img] tags
        if (preg_match_all('/\[img\](.*?)\[\/img\]/i', $message, $matches))
        {
            foreach ($matches[1] as $imageUrl)
            {
                $images[] = trim($imageUrl);
            }
        }
        
        // Match [img=url] tags
        if (preg_match_all('/\[img=(.*?)\]/i', $message, $matches))
        {
            foreach ($matches[1] as $imageUrl)
            {
                $images[] = trim($imageUrl);
            }
        }
        
        return $images;
    }

    /**
     * Remove image BBCode tags from message
     */
    protected function removeImageBbCode(string $message): string
    {
        // Remove [img] tags
        $message = preg_replace('/\[img\].*?\[\/img\]/i', '', $message);
        
        // Remove [img=url] tags
        $message = preg_replace('/\[img=.*?\]/i', '', $message);
        
        return trim($message);
    }

    /**
     * Get content thumbnail
     */
    protected function getContentThumbnail($content, string $type): ?string
    {
        if (!$content)
        {
            return null;
        }

        // First, check for attachments
        if (isset($content->Attachments) && $content->Attachments->count() > 0)
        {
            foreach ($content->Attachments as $attachment)
            {
                if ($attachment->Data && $attachment->Data->getTypeGrouping() === 'image')
                {
                    return $attachment->Data->getThumbnailUrl('l');
                }
            }
        }

        // Check for nested attachments (like FirstPost.Attachments for threads)
        if ($type === 'thread' && isset($content->FirstPost) && $content->FirstPost->Attachments->count() > 0)
        {
            foreach ($content->FirstPost->Attachments as $attachment)
            {
                if ($attachment->Data && $attachment->Data->getTypeGrouping() === 'image')
                {
                    return $attachment->Data->getThumbnailUrl('l');
                }
            }
        }

        // Try common thumbnail methods
        $thumbnailMethods = ['getIconUrl', 'getThumbnailUrl', 'getImageUrl'];
        
        foreach ($thumbnailMethods as $method)
        {
            if (method_exists($content, $method))
            {
                $url = $content->$method();
                if ($url)
                {
                    return $url;
                }
            }
        }

        // Special handling for resources
        if (in_array($type, ['resource', 'resource_update']))
        {
            $res = $type === 'resource_update' ? $content->Resource : $content;
            if ($res && method_exists($res, 'hasIcon') && $res->hasIcon())
            {
                return $res->getIconUrl('l');
            }
        }

        return null;
    }

    /**
     * Get content tags
     */
    protected function getContentTags($content, string $type, \XF\Mvc\Router $router): ?array
    {
        if (!$content || !isset($content->tags) || empty($content->tags))
        {
            return null;
        }

        $tags = [];
        foreach ($content->tags as $tagText)
        {
            $tags[] = [
                'label' => is_array($tagText) ? $tagText['tag'] : $tagText,
                'href' => $router->buildLink('tags', null, ['tag' => is_array($tagText) ? $tagText['tag'] : $tagText])
            ];
        }
        
        return $tags;
    }

    /**
     * Get profile post target name
     */
    protected function getProfilePostTargetName($content): string
    {
        return $content->ProfileUser ? $content->ProfileUser->username : (string)\XF::phrase('user');
    }

    /**
     * Custom BBCode rendering method that strips image tags before rendering.
     */
    protected function renderBbCodeWithoutImages(string $bbCode, string $type, $entity, \XF\App $app): string
    {
        // Remove image BBCode tags before rendering
        $bbCode = $this->removeImageBbCode($bbCode);
        
        // Now render the cleaned BBCode
        $rendered = $app->bbCode()->render($bbCode, 'html', $type, $entity, [
            'viewAttachments' => false, // Don't render attachments inline
            'unfurls' => isset($entity->Unfurls) ? $entity->Unfurls : [],
        ]);
        
        // Remove any remaining attachment image references from the rendered HTML
        $rendered = $this->removeAttachmentImagesFromHtml($rendered);
        
        return $rendered;
    }

    /**
     * Remove attachment image references from rendered HTML
     */
    protected function removeAttachmentImagesFromHtml(string $html): string
    {
        // Remove attachment image links and their content
        $html = preg_replace('/<a[^>]*data-template-name="public:bb_code_tag_attach"[^>]*>.*?<\/a>/is', '', $html);
        
        // Remove any remaining img tags that might be attachment images
        $html = preg_replace('/<img[^>]*class="bbImage"[^>]*>/i', '', $html);
        
        // Clean up multiple consecutive line breaks and whitespace
        $html = preg_replace('/\s*<br\s*\/?>\s*<br\s*\/?>\s*/i', '', $html);
        $html = preg_replace('/<br\s*\/?>\s*<br\s*\/?>/i', '', $html);
        
        // Remove leading/trailing line breaks
        $html = preg_replace('/^(<br\s*\/?>\s*)+/i', '', $html);
        $html = preg_replace('/(<br\s*\/?>\s*)+$/i', '', $html);
        
        // Clean up any remaining excessive whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        
        return trim($html);
    }

    /**
     * Get followers data for the profile user
     */
    protected function getFollowersData(\XF\Entity\User $profileUser): array
    {
        $followersData = [
            'count' => 0,
            'followers' => [],
            'showMore' => false
        ];

        // Get followers count by querying UserFollow table
        $followersCount = \XF::app()->db()->fetchOne("
            SELECT COUNT(*)
            FROM xf_user_follow
            WHERE follow_user_id = ?
        ", $profileUser->user_id);
        
        $followersData['count'] = $followersCount;

        if ($followersCount > 0)
        {
            // Get recent followers (limit to 5 for display)
            $followersFinder = $this->finder('XF:UserFollow')
                ->where('follow_user_id', $profileUser->user_id)
                ->with('User')
                ->order('follow_date', 'DESC')
                ->limit(5);

            $followers = $followersFinder->fetch();
            
            foreach ($followers as $follow)
            {
                $follower = $follow->User;
                if ($follower)
                {
                    $followersData['followers'][] = [
                        'user_id' => $follower->user_id,
                        'username' => $follower->username,
                        'avatar_url' => $follower->getAvatarUrl('s'),
                        'profile_url' => $this->router()->buildLink('members', $follower),
                        'follow_date' => $follow->follow_date
                    ];
                }
            }

            // Show "more" link if there are more than 5 followers
            if ($followersCount > 5)
            {
                $followersData['showMore'] = true;
                $followersData['moreUrl'] = $this->router()->buildLink('members', $profileUser, ['tab' => 'followers']);
            }
        }

        return $followersData;
    }

    /**
     * Get following data for the profile user
     */
    protected function getFollowingData(\XF\Entity\User $profileUser): array
    {
        $followingData = [
            'count' => 0,
            'following' => [],
            'showMore' => false
        ];

        // Get following count from the Profile entity
        $followingCount = count($profileUser->Profile->following);
        $followingData['count'] = $followingCount;

        if ($followingCount > 0)
        {
            // Get recent following (limit to 5 for display)
            $followingFinder = $this->finder('XF:UserFollow')
                ->where('user_id', $profileUser->user_id)
                ->with('FollowUser')
                ->order('follow_date', 'DESC')
                ->limit(5);

            $following = $followingFinder->fetch();
            
            foreach ($following as $follow)
            {
                $followedUser = $follow->FollowUser;
                if ($followedUser)
                {
                    $followingData['following'][] = [
                        'user_id' => $followedUser->user_id,
                        'username' => $followedUser->username,
                        'avatar_url' => $followedUser->getAvatarUrl('s'),
                        'profile_url' => $this->router()->buildLink('members', $followedUser),
                        'follow_date' => $follow->follow_date
                    ];
                }
            }

            // Show "more" link if there are more than 5 following
            if ($followingCount > 5)
            {
                $followingData['showMore'] = true;
                $followingData['moreUrl'] = $this->router()->buildLink('members', $profileUser, ['tab' => 'following']);
            }
        }

        return $followingData;
    }

    /**
     * Get level badge data for the profile user
     */
    protected function getLevelBadgeData(\XF\Entity\User $profileUser): array
    {
        $levelBadgeData = [
            'has_level' => false,
            'current_level' => 0,
            'next_level' => 0,
            'current_xp' => 0,
            'percentage' => 0,
            'points_needed' => 0,
            'level_badge' => null,
            'level_badge_image' => null,
            'next_level_badge' => null
        ];

        // Check if RankingSystem addon is active
        $addonManager = \XF::app()->addOnManager();
        $rankingAddon = $addonManager->getById('XC/RankingSystem');
        
        if (!$rankingAddon || !$rankingAddon->isActive()) {
            return $levelBadgeData;
        }

        try {
            // Get level badge service
            $levelBadgeService = \XF::service('XC\RankingSystem:LevelBadge');
            $levelProgress = $levelBadgeService->getUserLevelProgress($profileUser);

            if ($levelProgress && $levelProgress['current_level'] > 0) {
                $levelBadgeData['has_level'] = true;
                $levelBadgeData['current_level'] = $levelProgress['current_level'];
                $levelBadgeData['next_level'] = $levelProgress['next_level'];
                $levelBadgeData['current_xp'] = $profileUser->total_points ?? 0;
                $levelBadgeData['percentage'] = $levelProgress['percentage'];
                $levelBadgeData['points_needed'] = $levelProgress['points_needed'];

                // Get level badge information
                if ($levelProgress['level_badge']) {
                    $levelBadge = $levelProgress['level_badge'];
                    $levelBadgeData['level_badge'] = [
                        'title' => $levelBadge->title,
                        'description' => $levelBadge->description,
                        'level' => $levelBadge->level
                    ];

                    // Get badge image if it exists
                    if ($levelBadge->getImageExists()) {
                        $levelBadgeData['level_badge_image'] = $levelBadge->getImgPath();
                    }
                }

                // Get next level badge information
                if ($levelProgress['next_level'] > $levelProgress['current_level']) {
                    $nextLevelBadge = $levelBadgeService->getLevelBadgeByLevel($levelProgress['next_level']);
                    if ($nextLevelBadge) {
                        $levelBadgeData['next_level_badge'] = [
                            'title' => $nextLevelBadge->title,
                            'description' => $nextLevelBadge->description,
                            'level' => $nextLevelBadge->level
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail if there's an error with the ranking system
            \XF::logException($e, false, 'Level badge error: ');
        }

        return $levelBadgeData;
    }
}