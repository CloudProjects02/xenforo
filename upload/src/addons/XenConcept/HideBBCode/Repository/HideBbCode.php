<?php

/*************************************************************************
 * Hide BBCode - XenConcept (c) 2021
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\HideBBCode\Repository;

use XF\Mvc\Entity\Repository;
use XF\Mvc\RouteMatch;

use \XenConcept\HideBBCode\Data\Hide;
use XF;

class HideBbCode extends Repository
{
    /**
     * @var RouteMatch
     */
    protected $route;

    /**
     * @var string
     */
    protected $controller;

    /**
     * @var array
     */
    protected $params;

    // -------------------------------------------------------

    protected function getDiscussionControllers()
    {
        return [
            'XF:Forum',
            'XF:Thread',
            'XF:Post'
        ];
    }

    protected function getAllowedContentType()
    {
        return [
            'forum',
            'thread',
            'post'
        ];
    }

    // -------------------------------------------------------

    public function getHideBbCodes()
    {
        $data = new Hide();
        return $data->getHideBbCodes();
    }

    public function getHideBbCode($bbCode)
    {
        $hideBbCodes = $this->getHideBbCodes();

        return isset($hideBbCodes[$bbCode]) ? $hideBbCodes[$bbCode] : null;
    }

    public function getHideButtons()
    {
        $data = new Hide();
        return $data->getHideButtons();
    }

    public function getLegacyHideBbCodes()
    {
        $data = new Hide();
        return $data->getLegacyHideBbCodes();
    }

    public function getHideTags()
    {
        return $this->getHideBbCodes();
    }

    public function getHideOptionsData($includeDefault)
    {
        $choices = [];

        if ($includeDefault)
        {
            $choices = [
                'default' => [
                    'value' => 'default',
                    'label' => \XF::phrase('(default)')
                ]
            ];
        }

        foreach ($this->getHideButtons() AS $k => $button)
        {
            $choices[$k] = [
                'label' => $button['title'],
                'value' => $k
            ];
        }

        return $choices;
    }

    public function getHideBbCodesForHelpPage()
    {
        $hideBbCodes = $this->getHideBbCodes();

        $disabledHideBbCodes = \XF::options()->xc_hide_bbcode_disabled_hide_help_page;

        foreach ($hideBbCodes AS $tag => $bbCode)
        {
            if (in_array($bbCode['name'], $disabledHideBbCodes))
            {
                unset($hideBbCodes[$tag]);
            }
            else {
                $hideBbCodes[$tag]['title'] = \XF::phrase('xc_hide_bbcode_hide_bb_code_title.' . $tag);
                $hideBbCodes[$tag]['description'] = \XF::phrase('xc_hide_bbcode_hide_bb_code_desc.' . $tag);
                $hideBbCodes[$tag]['example'] = \XF::phrase('xc_hide_bbcode_hide_bb_code_example.' . $tag);
                $hideBbCodes[$tag]['output'] = \XF::phrase('xc_hide_bbcode_hide_bb_code_output.' . $tag);
            }

        }

        return $hideBbCodes;
    }

    // -------------------------------------------------------

    // -------------------------------------------------------

    public function getAllowedHideButtons($entity = null)
    {
        if (!$entity)
        {
            $entity = $this->em->find($this->route->getController(), $this->route->getParams());
        }

        if (!$entity instanceof \XF\Mvc\Entity\Entity)
        {
            return  [];
        }

        $contentType = $entity->getEntityContentType();

        if (in_array($contentType, ['forum', 'thread', 'post']))
        {
            $optionName = 'xc_hide_bbcode_allow_hide_buttons_create_thread';
            $contentId  = ($contentType == 'post') ? $entity->Thread->node_id : $entity->node_id;

            if ($contentType == 'thread' || ($contentType == 'post' && !$entity->isFirstPost()))
            {
                $optionName = 'xc_hide_bbcode_allow_hide_buttons_reply_thread';
            }

            if ($contentType == 'post' && $entity->isFirstPost())
            {
                $optionName = 'xc_hide_bbcode_allow_hide_buttons_create_thread';
            }

            return $this->getAllowedHideButtonsForContent($contentId, $optionName, 'hasNodePermission');
        }

        return [];
    }

    public function getRemovedHideButtons($allowedHideButtons)
    {
        $hideButtons = array_keys($this->getHideButtons());

        return array_values(array_diff($hideButtons, $allowedHideButtons));
    }

    public function getDisabledHideTagsForEntity(\XF\Mvc\Entity\Entity $entity = null)
    {
        $hideTags = array_keys($this->getHideTags());

        if (!$entity)
        {
            return $hideTags;
        }

        $allowedHideTags = $this->hideButtonsToHideTags($this->getAllowedHideButtons($entity));

        return array_values(array_diff($hideTags, $allowedHideTags));
    }

    // -------------------------------------------------------

    public function getAllowControllers()
    {
        return $this->getDiscussionControllers();
    }

    public function getAllowContentTypes()
    {
        return $this->getAllowedContentType();
    }

    public function canUseHideBbCodes()
    {
        $route = \XF::app()->router('public')->routeToController(\XF::app()->request()->getRoutePath());

        if (!$route || !in_array($route->getController(), $this->getAllowControllers()))
        {
            return false;
        }

        $this->route = $route;
        $this->controller = $route->getController();
        $this->params = $route->getParams();

        return true;
    }

    // -------------------------------------------------------

    protected function getAllowedHideButtonsForContent($contentId, $optionName, $permissionFuncName)
    {
        $hideButtons = \XF::options()->{$optionName};

        if (empty($hideButtons))
        {
            return [];
        }

        $visitor = \XF::visitor();

        $allowedHideButtons = [];

        foreach ($hideButtons AS $k => $button)
        {
            $permName = str_replace('xc', 'use', $button);

            if ($visitor->$permissionFuncName($contentId, $permName))
            {
                $allowedHideButtons[] = $button;
            }
        }

        return $allowedHideButtons;
    }

    protected function hideButtonsToHideTags($hideButtons)
    {
        return array_map(function ($hideButton)
        {
            return strtolower(str_replace('xc', '', str_replace('Default', '', $hideButton)))
;        }, $hideButtons);
    }

    // -------------------------------------------------------

    protected function hasReplied($userId, $threadId)
    {
        $postFinder = $this->finder('XF:Post');
        $postFinder
            ->where('user_id', $userId)
            ->where('thread_id', $threadId)
            ->where('message_state', 'visible');

        return $postFinder->fetchOne();
    }

    // -------------------------------------------------------

    public function getReactionTitlePairs($where = [])
    {

        return $this->repository('XF:Reaction')->findReactionsForList(true)->where($where)->fetch()->pluckNamed('title', 'reaction_id');
    }

    // -------------------------------------------------------

    public function addonUwFcsIsActive()
    {
        return $this->addonIsActive('UW/FCS');
    }

    public function addonIsActive($addonId)
    {
        $addOns = \XF::app()->container('addon.cache');
        return isset($addOns[$addonId]);
    }


}