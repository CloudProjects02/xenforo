<?php

namespace SynapseThemes\TagsThreadFilter\XF\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

class Forum extends XFCP_Forum
{
    /**
     * Override to add tag filter support
     * 
     * @param \XF\Entity\Forum $forum
     * @return array
     */
    protected function getForumFilterInput(\XF\Entity\Forum $forum)
    {
        $filters = parent::getForumFilterInput($forum);
        
        // Check if we have an active tag filter from the request
        $activeTag = $this->request->get('active_tag_filter');
        
        if ($activeTag && $activeTag instanceof \XF\Entity\Tag)
        {
            $filters['tag_id'] = $activeTag->tag_id;
        }
        
        return $filters;
    }
    
    /**
     * Override to apply tag filter
     * 
     * @param \XF\Entity\Forum $forum
     * @param \XF\Finder\Thread $threadFinder
     * @param array $filters
     */
    protected function applyForumFilters(\XF\Entity\Forum $forum, \XF\Finder\Thread $threadFinder, array $filters)
    {
        parent::applyForumFilters($forum, $threadFinder, $filters);
        
        // Apply tag filter if present
        if (!empty($filters['tag_id']))
        {
            $threadFinder->whereTag($filters['tag_id']);
        }
    }

    /**
     * Custom method to find tag by URL
     * 
     * @param string $tagUrl
     * @return \XF\Entity\Tag|null
     */
    protected function findTagByUrl($tagUrl)
    {
        $tag = $this->finder('XF:Tag')
            ->where('tag_url', $tagUrl)
            ->fetchOne();
        
        return $tag;
    }

    public function actionIndex(ParameterBag $params)
    {
        // Get the tag filter before calling parent action
        $tagFilter = $this->filter('tag_filter', 'str');
        $activeTag = null;
        
        if ($tagFilter)
        {
            // Use our custom method instead of repository method
            $activeTag = $this->findTagByUrl($tagFilter);
        }
        
        // Store the active tag in the request so we can access it in getForumFilterInput
        if ($activeTag)
        {
            $this->request->set('active_tag_filter', $activeTag);
        }
        
        $response = parent::actionIndex($params);

        if ($response instanceof View)
        {
            $templateName = $response->getTemplateName();
            
            if ($templateName == 'forum_list')
            {
                if ($activeTag)
                {
                    // Add tag information to the response for template use
                    $response->setParam('activeTagFilter', $activeTag);
                    $response->setParam('synapseThreadTags', [$activeTag]);
                }
                else if (!isset($response->getParams()['synapseThreadTags']))
                {
                    // If no tag filter, display all available tags
                    $db = $this->app()->db();
                    $tagIds = $db->fetchAllColumn("
                        SELECT DISTINCT tc.tag_id
                        FROM xf_tag_content AS tc
                        INNER JOIN xf_thread AS th ON (th.thread_id = tc.content_id AND tc.content_type = 'thread')
                        WHERE th.discussion_state = 'visible'
                    ");
                    
                    if ($tagIds)
                    {
                        $tags = $this->finder('XF:Tag')
                            ->whereIds($tagIds)
                            ->order('tag', 'ASC')
                            ->fetch();
                            
                        if ($tags->count())
                        {
                            $response->setParam('synapseThreadTags', $tags);
                        }
                    }
                }
            }
            
            // If this is an AJAX request, return appropriate view
            if ($this->request->isXhr())
            {
                $reply = $this->view();
                
                if ($templateName == 'forum_list')
                {
                    $reply->setJsonParam('title', $response->getParam('pageTitle'));
                    $reply->setJsonParam('content', $response);
                }
                else if ($templateName == 'forum_view')
                {
                    $threadListHtml = $this->renderTemplate('forum_view_threads', $response->getParams());
                    $reply->setJsonParams([
                        'title' => $response->getParam('pageTitle'),
                        'html' => [
                            'content' => $threadListHtml
                        ]
                    ]);
                }
                
                return $reply;
            }
        }

        return $response;
    }

    public function actionForum(ParameterBag $params)
    {
        // Get the tag filter before calling parent action
        $tagFilter = $this->filter('tag_filter', 'str');
        $activeTag = null;
        
        if ($tagFilter)
        {
            // Use our custom method instead of repository method
            $activeTag = $this->findTagByUrl($tagFilter);
        }
        
        // Store the active tag in the request so we can access it in getForumFilterInput
        if ($activeTag)
        {
            $this->request->set('active_tag_filter', $activeTag);
        }
        
        $response = parent::actionForum($params);

        if ($response instanceof View)
        {
            $templateName = $response->getTemplateName();
            
            if ($templateName == 'forum_view')
            {
                if ($activeTag)
                {
                    // Add tag information to the response for template use
                    $response->setParam('activeTagFilter', $activeTag);
                    $response->setParam('synapseThreadTags', [$activeTag]);
                }
                else if (!isset($response->getParams()['synapseThreadTags']))
                {
                    // If no tag filter and no tags set by the listener, get all available tags for this forum
                    $forum = $response->getParam('forum');
                    if ($forum)
                    {
                        $db = $this->app()->db();
                        $tagIds = $db->fetchAllColumn("
                            SELECT DISTINCT tc.tag_id
                            FROM xf_tag_content AS tc
                            INNER JOIN xf_thread AS th ON (th.thread_id = tc.content_id AND tc.content_type = 'thread')
                            WHERE th.node_id = ?
                              AND th.discussion_state = 'visible'
                        ", $forum->node_id);
                        
                        if ($tagIds)
                        {
                            $tags = $this->finder('XF:Tag')
                                ->whereIds($tagIds)
                                ->order('tag', 'ASC')
                                ->fetch();
                                
                            if ($tags->count())
                            {
                                $response->setParam('synapseThreadTags', $tags);
                            }
                        }
                    }
                }
            }
            
            // If this is an AJAX request, return appropriate view
            if ($this->request->isXhr())
            {
                $reply = $this->view();
                
                if ($templateName == 'forum_view')
                {
                    $threadListHtml = $this->renderTemplate('forum_view_threads', $response->getParams());
                    $reply->setJsonParams([
                        'title' => $response->getParam('pageTitle'),
                        'html' => [
                            'content' => $threadListHtml
                        ]
                    ]);
                }
                
                return $reply;
            }
        }

        return $response;
    }
}