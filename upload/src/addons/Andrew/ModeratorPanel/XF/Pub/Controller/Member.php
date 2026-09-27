<?php

namespace Andrew\ModeratorPanel\XF\Pub\Controller;
use XF\Mvc\ParameterBag;
use XF\Entity\User;
use XF\Mvc\FormAction;

class Member extends XFCP_Member
{

    public function actionEdit(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id, [], true);
        if (!$user->canEdit())
        {
            return $this->noPermission();
        }

        $response = parent::actionEdit($params);
        $notAdmin = $this->filter('not_admin', 'bool');

        if ($this->isPost())
        {
            $this->memberSaveProcess($user)->run();
            return $this->redirect($this->buildLink('members/edit', $user, $notAdmin ? ['not_admin' => 1] : []));
        }
        else
        {

            if (\XF::visitor()->hasAdminPermission('user') && !$notAdmin)
            {
                $router = $this->app->container('router.admin');
                return $this->redirect($router->buildLink('users/edit', $user));

            }

        }

        $options = $this->app()->options();
        $moderatedUsers = $options->andrewModeratorPanelModeratedUsergroup;

        $groupIds = $user->secondary_group_ids;

        foreach ($groupIds as $groupId)
        {
            if ($groupId == $moderatedUsers)
            {
                $response->setParam('is_moderated', true);
                break;
            }
        }

        if ($user->canModerate())
        {
            $response->setParam('moderatedProtected', false);
        }
        else
        {
            $response->setParam('moderatedProtected', true);
        }

        if ($user->canDiscourage())
        {
            $response->setParam('discourageProtected', false);
        }
        else
        {
            $response->setParam('discourageProtected', true);
        }

       return $response;

    }

    protected function memberSaveProcess(\XF\Entity\User $user)
    {

        $response = parent::memberSaveProcess($user);
        $visitor = \XF::visitor();
        $form = $this->formAction();

        $input = $this->filter([
            'user' => [
                'email' => 'str',
                'user_state' => 'str',
                'security_lock' => 'str',
                'style_id' => 'uint',
                'language_id' => 'uint',
                'timezone' => 'str',
                'visible' => 'bool',
                'activity_visible' => 'bool',
            ],
            'privacy' => [
                'allow_view_profile' => 'str',
                'allow_post_profile' => 'str',
                'allow_send_personal_conversation' => 'str',
                'allow_view_identities' => 'str',
                'allow_receive_news_feed' => 'str',
            ],

        ]);

        if ($visitor->canEditUserStateMP())
        {
            $user->user_state = $input['user']['user_state'];
        }
        if ($visitor->canEditUserLockMP())
        {
            $user->security_lock = $input['user']['security_lock'];
        }


        $is_moderated = $this->filter('is_moderated','bool' );

        if ($user->canModerate())
        {
            $this->moderateUser($user,$is_moderated);
        }

        if ($user->canDiscourage())
        {
            $this->discourageUser($user,$response);
        }

        return $response;

    }
    public function actionDiscourage(ParameterBag $params)
    {
        $userId = $params->user_id;

        $finder = \XF::finder('XF:User');
        $user = $finder->where('user_id',$userId)->fetchOne();

        if (!$user->canDiscourage())
        {
            return $this->noPermission();
        }

        $viewParams = [
            'user' => $user
        ];

        return $this->view('XF:Member\Discourage', 'andrew_moderatorpanel_discourage_user_overlay',$viewParams);
    }

    public function actionRemoveDiscourage(ParameterBag $params)
    {
        $userId = $params->user_id;

        $finder = \XF::finder('XF:User');
        $user = $finder->where('user_id',$userId)->fetchOne();

        if (!$user->canDiscourage())
        {
            return $this->noPermission();
        }

        $viewParams = [
            'user' => $user
        ];

        return $this->view('XF:Member\RemoveDiscourage', 'andrew_moderatorpanel_remove_discourage_user_overlay',$viewParams);
    }

    public function actionDiscourageSave(ParameterBag $params)
    {
        $userId = $params->user_id;
        $finder = \XF::finder('XF:User');
        $user = $finder->where('user_id',$userId)->fetchOne();

        if (!$user->canDiscourage())
        {
            return $this->noPermission();
        }

        $input = $this->filter([
            'option' => [
                'is_discouraged' => 'bool'
            ]
        ]);

        $userOptions = $user->getRelationOrDefault('Option');
        $userOptions->is_discouraged = $input['option']['is_discouraged'];
        $userOptions->save();

        return $this->redirect($this->getDynamicRedirect());
    }

    protected function discourageUser($user, $response)
    {
        $input = $this->filter([
            'option' => [
                'is_discouraged' => 'bool'
            ]
        ]);

        $userOptions = $user->getRelationOrDefault('Option');
        $response->setupEntityInput($userOptions, $input['option']);
    }
    public function actionModerate(ParameterBag $params)
    {
        $userId = $params->user_id;

        $finder = \XF::finder('XF:User');
        $user = $finder->where('user_id',$userId)->fetchOne();

        if (!$user->canModerate())
        {
            return $this->noPermission();
        }
        if ($this->options()->andrewModeratorPanelModeratedUsergroup == 0)
        {
            return $this->error(\XF::phrase('andrew_moderatorpanel_no_moderated_usergroup_configured_in_admin_panel'));
        }

        $viewParams = [
            'user' => $user
        ];

        return $this->view('XF:Member\Moderate', 'andrew_moderatorpanel_moderate_user_overlay',$viewParams);
    }

    public function actionRemoveModerate(ParameterBag $params)
    {
        $userId = $params->user_id;

        $finder = \XF::finder('XF:User');
        $user = $finder->where('user_id',$userId)->fetchOne();

        if (!$user->canModerate())
        {
            return $this->noPermission();
        }
        if ($this->options()->andrewModeratorPanelModeratedUsergroup == 0)
        {
            return $this->error(\XF::phrase('andrew_moderatorpanel_no_moderated_usergroup_configured_in_admin_panel'));
        }

        $viewParams = [
            'user' => $user
        ];

        return $this->view('XF:Member\RemoveModerate', 'andrew_moderatorpanel_remove_moderate_user_overlay',$viewParams);
    }
    public function actionModerateSave(ParameterBag $params)
    {
        $userId = $params->user_id;
        $finder = \XF::finder('XF:User');
        $user = $finder->where('user_id',$userId)->fetchOne();

        if (!$user->canModerate())
        {
            return $this->noPermission();
        }
        if ($this->options()->andrewModeratorPanelModeratedUsergroup == 0)
        {
            return $this->error(\XF::phrase('andrew_moderatorpanel_no_moderated_usergroup_configured_in_admin_panel'));

        }

        $is_moderated = $this->filter('is_moderated', 'bool');

        $this->moderateUser($user, $is_moderated);

        return $this->redirect($this->getDynamicRedirect());
    }

    protected function moderateUser($user, $is_moderated)
    {
        $options = $this->app()->options();
        $moderatedUsers = array($options->andrewModeratorPanelModeratedUsergroup);
        $userGroupChange = \XF::service('XF:User\UserGroupChange');

        if($is_moderated == true)
        {
            /*
            Retrieves existing user groups that are not part of group options and merges it what is being added.
            This ensures that any user group added in the admin panel and not an option to edit is never removed
            through the MCP.
            */

            $existingSecondaryGroups = $user->secondary_group_ids;
            $validated = array_merge($moderatedUsers, $existingSecondaryGroups);

            $user->set('secondary_group_ids', $validated);
            $user->save();
        }
        else
        {
            $existingSecondaryGroups = $user->secondary_group_ids;

            $existingSecondaryGroups = \array_diff($existingSecondaryGroups, [$options->andrewModeratorPanelModeratedUsergroup]);
            $validated = $existingSecondaryGroups;

            $user->set('secondary_group_ids', $validated);
            $user->save();
        }
    }
    public function actionUpdateUserGroup(ParameterBag $params)
    {
        $userId = $params->user_id;

        $finder = \XF::finder('XF:User');
        $user = $finder->where('user_id',$userId)->fetchOne();

        if (!$user->canUpdateUserGroup())
        {
            return $this->noPermission();
        }

        $userGroupRepo = $this->repository('XF:UserGroup');
        $userGroups = $userGroupRepo->getUserGroupTitlePairsMP();

        $viewParams = [
            'user' => $user,
            'userGroups' => $userGroups
        ];

        return $this->view('XF:Member\Moderate', 'andrew_moderatorpanel_update_usergroup_overlay',$viewParams);
    }
    public function actionUpdateUserGroupSave(ParameterBag $params)
    {
        $userGroupInput = $this->filter('secondary_group_ids', 'array-uint');
        $options = $this->app()->options();
        $groupOptions = $options->andrewModeratorPaneUpdateUserGroup;

        $userId = $params->user_id;
        $finder = \XF::finder('XF:User');
        $user = $finder->where('user_id',$userId)->fetchOne();

        if (!$user->canUpdateUserGroup())
        {
            return $this->noPermission();
        }

        /* Validates the user group selected is eligible based on the options in the admin panel */

        $validations = array_diff($userGroupInput,$groupOptions);
        if(!empty($validations))
        {
            return $this->noPermission();
        }

        /*
        Retrieves existing user groups that are not part of group options and merges it what is being added.
        This ensures that any user group added in the admin panel and not an option to edit is never removed
        through the MCP.
        */
        $addUserGroup = $userGroupInput;
        $existingSecondaryGroups = $user->secondary_group_ids;
        $existingACP = array_diff($existingSecondaryGroups, $groupOptions);
        $validated = array_merge($addUserGroup, $existingACP);

        $user->set('secondary_group_ids', $validated);
        $user->save();

        return $this->redirect($this->getDynamicRedirect());
    }

    public function actionForceIgnore(ParameterBag $params)
    {
        $visitor = \XF::visitor();
        $userId = $params->user_id;
        $finder = \XF::finder('XF:User');
        $user = $finder->where('user_id',$userId)->fetchOne();

        if (!$visitor->canForceIgnoreMP())
        {
            return $this->noPermission();
        }

        $finder = \XF::finder('XF:UserIgnored');
        $ignores = $finder->where('user_id',$userId)
            ->where('andrew_forced',1)
            ->fetch();

        $viewParams = [
            'user' => $user,
            'ignores' => $ignores
        ];

        return $this->view('XF:Member\ForceIgnore', 'andrew_moderatorpanel_force_ignore_overlay',$viewParams);
    }
    public function actionForceIgnoreSave(ParameterBag $params)
    {
        $visitor = \XF::visitor();

        if (!$visitor->canForceIgnoreMP())
        {
            return $this->noPermission();
        }

        $userId = $params->user_id;
        $username = $this->filter('username', 'str');
        $both_ways = $this->filter('both_ways', 'bool');

        $deleteIgnores = $this->filter('delete', 'array-uint');

        $deleteIgnores = array_unique($deleteIgnores);

        if($deleteIgnores)
        {
            foreach ($deleteIgnores as $deleteIgnore => $value)
            {
                if($value == 1)
                {
                    $finder = \XF::finder('XF:UserIgnored');
                    $ignore = $finder->where('ignored_user_id', $deleteIgnore)
                        ->where('user_id', $userId)
                        ->fetchOne();

                    $ignore->delete();
                }
            }
        }

        if($username)
        {
            $finder = \XF::finder('XF:User');
            $user = $finder->where('username', $username)->fetchOne();
            $ignoredUserId = $user['user_id'];

            if (!$visitor->canIgnoreUser($user, $error))
            {
                return $this->noPermission($error);
            }

            $userIgnore = \XF::em()->create('XF:UserIgnored');
            $userIgnore->ignored_user_id = $ignoredUserId;
            $userIgnore->user_id = $userId;
            $userIgnore->andrew_forced = 1;
            $userIgnore->andrew_forced_user_id = $visitor->user_id;
            $userIgnore->save();

            if($both_ways)
            {
                $userIgnore2 = \XF::em()->create('XF:UserIgnored');
                $userIgnore2->ignored_user_id = $userId;
                $userIgnore2->user_id = $ignoredUserId;
                $userIgnore2->andrew_forced = 1;
                $userIgnore2->andrew_forced_user_id = $visitor->user_id;
                $userIgnore2->save();
            }
        }

        return $this->redirect($this->getDynamicRedirect());
    }
}