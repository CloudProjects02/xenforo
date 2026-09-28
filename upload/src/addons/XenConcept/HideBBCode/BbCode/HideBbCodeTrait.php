<?php
/**
 * Created by PhpStorm.
 * User: Remi
 * Date: 14/02/2021
 * Time: 01:51
 */

namespace XenConcept\HideBBCode\BbCode;

use XF;
use XF\BbCode\Renderer\EmailHtml;

trait HideBbCodeTrait
{
    protected $reactLikeId = 1;

    public function renderTagHide(array $children, $option, array $tag, array $options)
    {
        if (!($content = $this->getHideTagContent($children, $options)) )
        {
            return '';
        }

        if (!$this->checkEntity($options))
        {
            return $this->renderUnparsedTag($tag, $options);
        }

        $hideMappedTo = str_replace('xc', '', \XF::options()->xc_hide_bbcode_hideMappedTo);
        if ($hideMappedTo == 'HideReact' && \XF::options()->xc_hide_bbcode_hideReactMappedTo != 'default')
        {
            $hideMappedTo = str_replace('xc', '', \XF::options()->xc_hide_bbcode_hideReactMappedTo);
        }

        $checkFunc = 'check' . $hideMappedTo;

        if (!$this->checkHideContext() || !$this->$checkFunc($options['entity'], $option))
        {
            switch (strtolower($hideMappedTo))
            {
                case 'hidereact':
                    $content = $this->getReactErrorPhrase(\XF::phrase('xc_hide_bbcode_hide_bb_code_error_phrase.hidereact'), explode(',', $option));
                    break;
                case 'hidereplyreact':
                    $content = $this->getReactErrorPhrase(\XF::phrase('xc_hide_bbcode_hide_bb_code_error_phrase.hidereplyreact'), explode(',', $option));
                    break;
                case 'hidereplyorreact':
                    $content = $this->getReactErrorPhrase(\XF::phrase('xc_hide_bbcode_hide_bb_code_error_phrase.hidereplyorreact'), explode(',', $option));
                    break;
                case 'hideusergroups':
                    $content = $this->getUserGroupErrorPhrase($option);
                    break;
                case 'hideshowtogroups':
                    $content = $this->getShowUserGroupErrorPhrase($option);
                    break;
                case 'hideuser':
                    $content = $this->getUserErrorPhrase($option);
                    break;
                default:
                    $content = \XF::phrase('xc_hide_bbcode_hide_bb_code_error_phrase.' . strtolower($hideMappedTo), ['value' => $option]);
            }

            return $this->getRenderedHide($tag['tag'], $content, 'hidden');
        }

        return $this->getRenderedHide($tag['tag'], $content, 'visible');
    }

    public function renderTagHideReply(array $children, $option, array $tag, array $options)
    {
        if (!($content = $this->getHideTagContent($children, $options)))
        {
            return '';
        }

        if (!$this->checkEntity($options))
        {
            return $this->renderUnparsedTag($tag, $options);
        }

        if (!$this->checkHideContext() || !$this->checkHideReply($options['entity'], $option))
        {
            $content = \XF::phrase('xc_hide_bbcode_hide_bb_code_error_phrase.hidereply');
            return $this->getRenderedHide($tag['tag'], $content, 'hidden');
        }

        return $this->getRenderedHide($tag['tag'], $content, 'visible');
    }

    public function renderTagHideThanks(array $children, $option, array $tag, array $options)
    {
        if (!($content = $this->getHideTagContent($children, $options)))
        {
            return '';
        }

        if (!$this->checkEntity($options))
        {
            return $this->renderUnparsedTag($tag, $options);
        }

        if (!$this->checkHideContext() || !$this->checkHideThanks($options['entity'], $option))
        {
            $content = \XF::phrase('xc_hide_bbcode_hide_bb_code_error_phrase.hidethanks');
            return $this->getRenderedHide($tag['tag'], $content, 'hidden');
        }

        return $this->getRenderedHide($tag['tag'], $content, 'visible');
    }

    public function renderTagHideReplyThanks(array $children, $option, array $tag, array $options)
    {
        if (!($content = $this->getHideTagContent($children, $options)))
        {
            return '';
        }

        if (!$this->checkEntity($options))
        {
            return $this->renderUnparsedTag($tag, $options);
        }

        if (!$this->checkHideContext() || !$this->checkHideReplyThanks($options['entity'], $option))
        {
            $content = \XF::phrase('xc_hide_bbcode_hide_bb_code_error_phrase.hidereplythanks');
            return $this->getRenderedHide($tag['tag'], $content, 'hidden');
        }

        return $this->getRenderedHide($tag['tag'], $content, 'visible');
    }

    public function renderTagHidePosts(array $children, $option, array $tag, array $options)
    {
        if (!($content = $this->getHideTagContent($children, $options)))
        {
            return '';
        }

        if (!$this->checkEntity($options))
        {
            return $this->renderUnparsedTag($tag, $options);
        }

        if (!$this->checkHideContext() || !$this->checkHidePosts($options['entity'], $option))
        {
            $content = \XF::phrase('xc_hide_bbcode_hide_bb_code_error_phrase.hideposts', ['value' => intval($option)]);
            return $this->getRenderedHide($tag['tag'], $content, 'hidden');
        }

        return $this->getRenderedHide($tag['tag'], $content, 'visible');
    }

    public function renderTagHideReact(array $children, $option, array $tag, array $options)
    {
        if (!($content = $this->getHideTagContent($children, $options)))
        {
            return '';
        }

        if (!$this->checkEntity($options))
        {
            return $this->renderUnparsedTag($tag, $options);
        }

        if (!$this->checkHideContext() || !$this->checkHideReact($options['entity'], $option))
        {
            $content = $this->getReactErrorPhrase(\XF::phrase('xc_hide_bbcode_hide_bb_code_error_phrase.hidereact'), explode(',', $option));
            return $this->getRenderedHide($tag['tag'], $content, 'hidden');
        }

        return $this->getRenderedHide($tag['tag'], $content, 'visible');
    }

    public function renderTagHideReplyReact(array $children, $option, array $tag, array $options)
    {
        if (!($content = $this->getHideTagContent($children, $options)))
        {
            return '';
        }

        if (!$this->checkEntity($options))
        {
            return $this->renderUnparsedTag($tag, $options);
        }

        if (!$this->checkHideContext() || !$this->checkHideReact($options['entity'], $option))
        {
            $content = $this->getReactErrorPhrase(\XF::phrase('xc_hide_bbcode_hide_bb_code_error_phrase.hidereact'), explode(',', $option));
            return $this->getRenderedHide($tag['tag'], $content, 'hidden');
        }

        return $this->getRenderedHide($tag['tag'], $content, 'visible');
    }

    public function renderTagHideReplyOrReact(array $children, $option, array $tag, array $options)
    {
        if (!($content = $this->getHideTagContent($children, $options)))
        {
            return '';
        }

        if (!$this->checkEntity($options))
        {
            return $this->renderUnparsedTag($tag, $options);
        }

        if (!$this->checkHideContext() || !$this->checkHideReplyOrReact($options['entity'], $option))
        {
            $content = $this->getReactErrorPhrase(\XF::phrase('xc_hide_bbcode_hide_bb_code_error_phrase.hidereplyorreact'), explode(',', $option));
            return $this->getRenderedHide($tag['tag'], $content, 'hidden');
        }

        return $this->getRenderedHide($tag['tag'], $content, 'visible');
    }

    public function renderTagHideUserGroups(array $children, $option, array $tag, array $options)
    {
        if (!($content = $this->getHideTagContent($children, $options)))
        {
            return '';
        }

        if (!$this->checkEntity($options))
        {
            return $this->renderUnparsedTag($tag, $options);
        }

        if ($this->rules->getContext() === 'help' || $this->rules->getSubContext() === 'preview' || !$this->checkHideUserGroups($options['entity'], $option))
        {
            $content = $this->getUserGroupErrorPhrase($option);
            return $this->getRenderedHide($tag['tag'], $content, 'hidden');
        }

        return $this->getRenderedHide($tag['tag'], $content, 'visible');
    }

    public function renderTagHideShowtogroups(array $children, $option, array $tag, array $options)
    {
        if (!($content = $this->getHideTagContent($children, $options)))
        {
            return '';
        }

        if (!$this->checkEntity($options))
        {
            return $this->renderUnparsedTag($tag, $options);
        }

        if ($this->rules->getContext() === 'help' || $this->rules->getSubContext() === 'preview' || !$this->checkHideShowtogroups($options['entity'], $option))
        {
            $content = $this->getShowUserGroupErrorPhrase($option);
            return $this->getRenderedHide($tag['tag'], $content, 'hidden');
        }

        return $this->getRenderedHide($tag['tag'], $content, 'visible');
    }

    public function renderTagHideUser(array $children, $option, array $tag, array $options)
    {
        if (!($content = $this->getHideTagContent($children, $options)))
        {
            return '';
        }

        if (!$this->checkEntity($options))
        {
            return $this->renderUnparsedTag($tag, $options);
        }

        if (!$this->checkHideContext() || !$this->checkHideUser($options['entity'], $option))
        {
            $content = $this->getUserErrorPhrase($option);
            return $this->getRenderedHide($tag['tag'], $content, 'hidden');
        }

        return $this->getRenderedHide($tag['tag'], $content, 'visible');
    }

    public function renderTagHideGuest(array $children, $option, array $tag, array $options)
    {
        if (!($content = $this->getHideTagContent($children, $options)))
        {
            return '';
        }

        if (!$this->checkEntity($options))
        {
            return $this->renderUnparsedTag($tag, $options);
        }

        if (!$this->checkHideContext() || !$this->checkHideGuest($options['entity'], $option))
        {
            $content = \XF::phrase('xc_hide_bbcode_hide_bb_code_error_phrase.hideguest');
            return $this->getRenderedHide($tag['tag'], $content, 'hidden');
        }

        return $this->getRenderedHide($tag['tag'], $content, 'visible');
    }

    public function renderTagHideAge(array $children, $option, array $tag, array $options)
    {
        if (!($content = $this->getHideTagContent($children, $options)))
        {
            return '';
        }

        if (!$this->checkEntity($options))
        {
            return $this->renderUnparsedTag($tag, $options);
        }

        if (!$this->checkHideContext() || !$this->checkHideAge($options['entity'], $option))
        {
            $content = \XF::phrase('xc_hide_bbcode_hide_bb_code_error_phrase.hideage', ['value' => $option]);
            return $this->getRenderedHide($tag['tag'], $content, 'hidden');
        }

        return $this->getRenderedHide($tag['tag'], $content, 'visible');
    }

    public function renderTagHideReactScore(array $children, $option, array $tag, array $options)
    {
        if (!($content = $this->getHideTagContent($children, $options)))
        {
            return '';
        }

        if (!$this->checkEntity($options))
        {
            return $this->renderUnparsedTag($tag, $options);
        }

        if (!$this->checkHideContext() || !$this->checkHideReactScore($options['entity'], $option))
        {
            $content = \XF::phrase('xc_hide_bbcode_hide_bb_code_error_phrase.hidereactscore', ['value' => $option]);
            return $this->getRenderedHide($tag['tag'], $content, 'hidden');
        }

        return $this->getRenderedHide($tag['tag'], $content, 'visible');
    }

    public function renderTagHideTrophy(array $children, $option, array $tag, array $options)
    {
        if (!($content = $this->getHideTagContent($children, $options)))
        {
            return '';
        }

        if (!$this->checkEntity($options))
        {
            return $this->renderUnparsedTag($tag, $options);
        }

        if (!$this->checkHideContext() || !$this->checkHideTrophy($options['entity'], $option))
        {
            $content = \XF::phrase('xc_hide_bbcode_hide_bb_code_error_phrase.hidetrophy', ['value' => $option]);
            return $this->getRenderedHide($tag['tag'], $content, 'hidden');
        }

        return $this->getRenderedHide($tag['tag'], $content, 'visible');
    }

    // -------------------------------------------------------

    protected function getHideTagContent($children, $options)
    {
        if (!$children)
        {
            return '';
        }

        $this->trimChildrenList($children);

        $content = $this->renderSubTree($children, $options);

        return $content;
    }

    // -------------------------------------------------------

    protected function getReactErrorPhrase($phrase, $reactIds)
    {
        return $this->templater->func('xc_hide_phrases', [$phrase, $reactIds]);
    }

    protected function getUserErrorPhrase($userIds)
    {
        $userIds = array_map('intval', explode(',', $userIds));
        $users   = \XF::app()->em()->getFinder('XF:User')->whereIds($userIds)->fetch();

        $newUsers = [];

        foreach ($users AS $user)
        {
            $newUsers[] = $user->username;
        }

        $newUsers = implode(', ', $newUsers);

        return \XF::phrase('xc_hide_bbcode_hide_bb_code_error_phrase.hideuser',['users' => $newUsers]);
    }

    protected function getUserGroupErrorPhrase($userGroupIds)
    {
        $groupExplode = explode(',', $userGroupIds);
        $userGroups   = \XF::app()->em()->getFinder('XF:UserGroup')->whereIds($groupExplode)->fetch();

        $groups = [];

        foreach ($userGroups AS $userGroup)
        {
            $groups[] = $userGroup->title;
        }

        $groups = implode(', ', $groups);

        return \XF::phrase('xc_hide_bbcode_hide_bb_code_error_phrase.hideusergroups',['groups' => $groups]);
    }

    protected function getShowUserGroupErrorPhrase($userGroupIds)
    {
        $groupExplode = explode(',', $userGroupIds);
        $userGroups   = \XF::app()->em()->getFinder('XF:UserGroup')->whereIds($groupExplode)->fetch();

        $groups = [];

        foreach ($userGroups AS $userGroup)
        {
            $groups[] = $userGroup->title;
        }

        $groups = implode(', ', $groups);

        return \XF::phrase('xc_hide_bbcode_hide_bb_code_error_phrase.hideshowtogroups',['groups' => $groups]);
    }

    protected function getRenderedHide($tag, $content, $visibility)
    {
        $renderParams = [
            'hideTag' => $tag,
            'content' => $content,
            'visibility' => $visibility
        ];

        return $this->templater->renderTemplate('public:xc_hide_bbcode_bb_code_tag_hide', $renderParams);
    }

    // -------------------------------------------------------

    protected function checkEntity(array $options)
    {
        if ($this->rules->getContext() === 'help' || $this->rules->getSubContext() === 'preview')
        {
            return true;
        }

        if (!isset($options['entity']))
        {
            return false;
        }

        if (!$options['entity'] instanceof \XF\Entity\Post)
        {
            return false;
        }

        return true;
    }

    protected function checkHideContext()
    {
        if ($this->rules->getContext() === 'help' || $this->rules->getSubContext() === 'preview')
        {
            return false;
        }

        if ($this instanceof EmailHtml)
        {
            return false;
        }

        return true;
    }

    protected function checkIsAuthor($userId, $validUserIds)
    {
        return in_array($userId, $validUserIds);
    }

    /**
     * @param \XF\Entity\Post $entity
     * @return bool
     */
    protected function checkHideReply($entity, $option)
    {
        if (in_array(\XF::visitor()->user_id, [$entity->user_id, $entity->Thread->user_id]))
        {
            return true;
        }

        if (\XF::visitor()->hasNodePermission($entity->Thread->node_id, 'bypassHideReply'))
        {
            return true;
        }

        if ($this->hasReplied(\XF::visitor()->user_id, $entity->thread_id))
        {
            return true;
        }

        return false;
    }

    protected function checkHidePosts($entity, $postCount)
    {
        if (in_array(\XF::visitor()->user_id, [$entity->user_id, $entity->Thread->user_id]))
        {
            return true;
        }

        if (\XF::visitor()->hasNodePermission($entity->Thread->node_id, 'bypassHidePosts'))
        {
            return true;
        }

        if ($postCount <= \XF::visitor()->message_count)
        {
            return true;
        }

        return false;
    }

    public function checkHideThanks($entity, $option)
    {
        if (in_array(\XF::visitor()->user_id, [$entity->user_id, $entity->Thread->user_id]))
        {
            return true;
        }

        if (\XF::visitor()->hasNodePermission($entity->Thread->node_id, 'bypassHideThanks'))
        {
            return true;
        }

        return $this->hasReacted(\XF::visitor()->user_id, $entity, [$this->reactLikeId]);
    }

    public function checkHideReplyThanks($entity, $option)
    {
        if (in_array(\XF::visitor()->user_id, [$entity->user_id, $entity->Thread->user_id]))
        {
            return true;
        }

        if (\XF::visitor()->hasNodePermission($entity->Thread->node_id, 'bypassHideReplyThanks'))
        {
            return true;
        }

        if ($this->hasReplied(\XF::visitor()->user_id, $entity->thread_id) && $this->hasReacted(\XF::visitor()->user_id, $entity, [$this->reactLikeId]))
        {
            return true;
        }

        return false;
    }

    public function checkHideReact($entity, $reactIds)
    {
        if (in_array(\XF::visitor()->user_id, [$entity->user_id, $entity->Thread->user_id]))
        {
            return true;
        }

        if (\XF::visitor()->hasNodePermission($entity->Thread->node_id, 'bypassHideReact'))
        {
            return true;
        }

        return $this->hasReacted(\XF::visitor()->user_id, $entity, explode(',', $reactIds));
    }

    public function checkHideReplyReact($entity, $reactIds)
    {
        if (in_array(\XF::visitor()->user_id, [$entity->user_id, $entity->Thread->user_id]))
        {
            return true;
        }

        if (\XF::visitor()->hasNodePermission($entity->Thread->node_id, 'bypassHideReplyReact'))
        {
            return true;
        }

        return $this->hasReacted(\XF::visitor()->user_id, $entity, explode(',', $reactIds));
    }

    public function checkHideReplyOrReact($entity, $reactIds)
    {
        if (in_array(\XF::visitor()->user_id, [$entity->user_id, $entity->Thread->user_id]))
        {
            return true;
        }

        if (\XF::visitor()->hasNodePermission($entity->Thread->node_id, 'bypassHideReplyOrReact'))
        {
            return true;
        }

        return ($this->hasReplied(\XF::visitor()->user_id, $entity->thread_id) || $this->hasReacted(\XF::visitor()->user_id, $entity, explode(',', $reactIds)));
    }

    public function checkHideUser($entity, $userIds)
    {
        //in_array(\XF::visitor()->user_id, [$entity->user_id, $entity->Thread->user_id])
        if (\XF::visitor()->user_id == $entity->user_id)
        {   
            return true;
        }

        if (\XF::visitor()->hasNodePermission($entity->Thread->node_id, 'bypassHideUser'))
        {
            return true;
        }

        $userIds = array_map('intval', explode(',', $userIds));
        if (in_array(\XF::visitor()->user_id, $userIds))
        {
            return true;
        }

        return false;
    }

    public function checkHideGuest($entity, $userIds)
    {
        if (in_array(\XF::visitor()->user_id, [$entity->user_id, $entity->Thread->user_id]))
        {
            return true;
        }

        if (\XF::visitor()->hasNodePermission($entity->Thread->node_id, 'bypassHideGuest'))
        {
            return true;
        }

        if (\XF::visitor()->user_id)
        {
            return true;
        }

        return false;
    }

    public function checkHideAge($entity, $age)
    {
        if (in_array(\XF::visitor()->user_id, [$entity->user_id, $entity->Thread->user_id]))
        {
            return true;
        }

        if (\XF::visitor()->hasNodePermission($entity->Thread->node_id, 'bypassHideAge'))
        {
            return true;
        }

        if (\XF::visitor()->Profile->getAge() > $age)
        {
            return true;
        }

        return false;
    }

    public function checkHideUserGroups($entity, $userGroupIds)
    {
        if (in_array(\XF::visitor()->user_id, [$entity->user_id, $entity->Thread->user_id]))
        {
            return true;
        }

        if (\XF::visitor()->hasNodePermission($entity->Thread->node_id, 'bypassHideUserGroups'))
        {
            return true;
        }

        $groups = explode(',', $userGroupIds);
//        $userGroups   = \XF::visitor()->secondary_group_ids;
        $userGroups[] = \XF::visitor()->user_group_id;

        $result = false;

        foreach ($userGroups AS $userGroup)
        {
            if (in_array($userGroup, $groups))
            {
                $result = true;
                break;
            }
        }

        return $result;
    }

    public function checkHideShowtogroups($entity, $userGroupIds)
    {
        if (in_array(\XF::visitor()->user_id, [$entity->user_id, $entity->Thread->user_id]))
        {
            return true;
        }

        if (\XF::visitor()->hasNodePermission($entity->Thread->node_id, 'bypassHideShowtogroup'))
        {
            return true;
        }

        $groups = explode(',', $userGroupIds);
        $userGroups   = \XF::visitor()->secondary_group_ids;
        $userGroups[] = \XF::visitor()->user_group_id;

        $result = false;

        foreach ($userGroups AS $userGroup)
        {
            if (in_array($userGroup, $groups))
            {
                $result = true;
                break;
            }
        }

        return $result;
    }

    public function checkHideReactScore($entity, $reactScore)
    {
        if (in_array(\XF::visitor()->user_id, [$entity->user_id, $entity->Thread->user_id]))
        {
            return true;
        }

        if (\XF::visitor()->hasNodePermission($entity->Thread->node_id, 'bypassHideReactScore'))
        {
            return true;
        }

        if ($reactScore <= \XF::visitor()->reaction_score)
        {
            return true;
        }

        return false;
    }

    public function checkHideTrophy($entity, $trophyPoints)
    {
        if (in_array(\XF::visitor()->user_id, [$entity->user_id, $entity->Thread->user_id]))
        {
            return true;
        }

        if (\XF::visitor()->hasNodePermission($entity->Thread->node_id, 'bypassHideTrophy'))
        {
            return true;
        }

        if ($trophyPoints <= \XF::visitor()->trophy_points)
        {
            return true;
        }

        return false;
    }

    // -------------------------------------------------------

    protected function hasReplied($userId, $threadId)
    {
        $postFinder = \XF::app()->em()->getFinder('XF:Post');
        $postFinder
            ->where('user_id', $userId)
            ->where('thread_id', $threadId)
            ->where('message_state', 'visible');

        return $postFinder->fetchOne();
    }

    protected function hasReacted($userId, \XF\Entity\Post $post, array $reactIds)
    {
        $reactionFinder = \XF::app()->em()->getFinder('XF:ReactionContent');

        $reactionFinder
            ->where('content_type', 'post')
            ->where('content_id', $post->post_id)
            ->where('reaction_id', $reactIds)
            ->where('reaction_user_id', $userId);

        return $reactionFinder->fetchOne();
    }

}