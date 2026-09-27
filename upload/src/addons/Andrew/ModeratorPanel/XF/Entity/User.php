<?php

namespace Andrew\ModeratorPanel\XF\Entity;
use XF\Mvc\Entity\Structure;

class User extends XFCP_User
{

    public function canViewModeratorPanel(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->hasPermission('andrew_moderatorpanel', 'canViewModeratorPanel'));
    }
    public function canBanProtectedUsers(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'canBanProtectedUsers'));
    }
    public function canSearchUsers(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'searchUsers'));
    }
    public function canSearchEmailMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'searchEmailMP'));
    }
    public function canSearchUserGroupMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'searchUserGroupMP'));
    }
    public function canSearchRegisteredDateMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'searchRegistrationDateMP'));
    }
    public function canSearchLastVisitedMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'searchLastVisitedMP'));
    }
    public function canSearchMessageCountMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'searchMessageCountMP'));
    }
    public function canSearchQuestionsSolvedMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'searchQuestionsSolvedMP'));
    }
    public function canSearchTrophyPointsMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'searchTrophyPointsMP'));
    }
    public function canSearchReactionScoreMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'searchReactionScoreMP'));
    }
    public function canSearchUserStateMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'searchUserStateMP'));
    }
    public function canSearchSecurityLockMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'searchSecurityLockMP'));
    }
    public function canSearchBannedStateMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'searchBannedStateMP'));
    }
    public function canSearchDiscouragedStateMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'searchDiscourageStateMP'));
    }
    public function canSearchStaffStateMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'searchStaffStateMP'));
    }
    public function canSearchActivityEmailMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'searchActivityEmailMP'));
    }
    public function canSearchCustomFieldsMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'searchCustomFieldsMP'));
    }
    public function canViewBannedUsersListMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewBannedUsersList'));
    }
    public function canViewDiscouragedUsersListMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewDiscouragedUsersList'));
    }
    public function canDiscourageUsers(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'addDiscouragedUsers'));
    }
    public function canDiscourageProtectedUsers(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'discourageProtectedUsers'));
    }
    public function canUpdateUsrGrpUsers(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'updateUsrGrpUsers'));
    }
    public function canUpdateUsrGrpPrtcdUsers(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'updateUsrGrpPrtcdUsers'));
    }
    public function canViewThreadBan(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewThreadBan'));
    }
    public function canViewRecentlyRegistered(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewRecentlyRegistered'));
    }
    public function canViewWarnedUsers(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewWarnedUsers'));
    }
    public function canViewReportedUsers(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewReportedUsers'));
    }
    public function canViewIgnoredUsers(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewIgnoredUsers'));
    }
    public function canViewModeratorLog(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewModeratorLog'));
    }
    public function canViewChangeLog(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewChangeLog'));
    }
    public function canViewUsernameChangeLog(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewUserNameChangeLog'));
    }

    public function canViewUserNotes(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewUserNotes'));
    }
    public function canViewRejectedUserLog(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewRejectedUserLog'));
    }
    public function canViewSpamCleanerLog(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewSpamCleanerLog'));
    }
    public function canViewSpamTriggerLog(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewSpamTriggerLog'));
    }
    public function canDeleteOwnUserNote(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'deleteOwnUserNote'));
    }
    public function canDeleteOtherUserNote(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'deleteOtherUserNote'));
    }
    public function canAddUserNote(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'addUserNote'));
    }
    public function canEditOwnUserNote(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'editOwnUserNote'));
    }
    public function canEditOtherUserNote(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'editOtherUserNote'));
    }
    public function canViewModeratedUsers(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewModeratedUsers'));
    }
    public function canModerateUsers(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'addModeratedUsers'));
    }
    public function canModerateProtectedUsers(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'moderateProtectedUsers'));
    }
    public function canViewMemberStatsMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewMemberStatsMP'));
    }
    public function canViewStatsGraphsMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewStatsGraphMP'));
    }
    public function canViewThreadWarningsMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewThreadWarningsMP'));
    }
    public function canForceIgnoreMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'forceIgnoreMP'));
    }
    public function canViewForceIgnoreMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewForceIgnoreMP'));
    }
    public function canEditUserStateMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'editUserStateMP'));
    }
    public function canEditUserLockMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'editUserLockMP'));
    }
    public function canEditUserNoteSilently(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'editUserNoteSilently'));
    }
    public function canViewPrivilegedUserNotes(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewPrivilegedUserNotes'));
    }
    public function canAddPrivilegedUserNotes(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'addPrivilegedUserNotes'));
    }
    public function canEditOwnPrivilegedUserNote(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'editOwnPrivilegedUserNote'));
    }
    public function canEditOtherPrivilegedUserNote(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'editOtherPrivilegedUN'));
    }
    public function canEditPrivilegedUserNoteSilently(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'editPrivilegedUNSilently'));
    }
    public function canDeleteOwnPrivilegedUserNote(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'deleteOwnPrivilegedUN'));
    }
    public function canDeleteOtherPrivilegedUserNote(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'deleteOtherPrivilegedUN'));
    }

    public function canViewRecentLoginsMP(&$error = null)
    {
        $visitor = \XF::visitor();
        return ($visitor->user_id && $visitor->hasPermission('andrew_moderatorpanel', 'viewRecentLogin'));
    }

    public function canBan(&$error = null)
    {

        $visitor = \XF::visitor();
        $options = $this->app()->options();

        $protectedUser = $options->andrewModeratorPanelProtected;
        $protectedEnabled = $options->andrewModeralPanelEnabledProtectedUsers;

        if($protectedEnabled == true)
        {
            foreach ($this->secondary_group_ids as $groupId)
            {
                if ($groupId == $protectedUser && !$visitor->canBanProtectedUsers())
                {
                    return false;
                }
            }
        }

        return parent::canBan($error);

    }
    public function canUpdateUserGroup(&$error = null)
    {
        $visitor = \XF::visitor();
        $options = $this->app()->options();

        $protectedUser = $options->andrewModeratorPanelProtected;
        $protectedEnabled = $options->andrewModeralPanelEnabledProtectedUsers;
        $isStaffEnabled = $options->andrewModeralPanelEnabledIsStaff;
        $isModeratorEnabled = $options->andrewModeratorPanelEnabledIsMod;
        $groupOptions = $options->andrewModeratorPaneUpdateUserGroup;

        if(array_sum($groupOptions) == 0)
        {
            return false;
        }

        if($isStaffEnabled == false && $this->is_staff == true)
        {
            return false;
        }

        if($isModeratorEnabled == false && $this->is_moderator == true)
        {
            return false;
        }

        if($this->is_admin == true)
        {
            return false;
        }

        if($this->user_id == $visitor->user_id)
        {
            return false;
        }

        if($protectedEnabled == true)
        {
            foreach ($this->secondary_group_ids as $groupId)
            {
                if ($groupId == $protectedUser && !$visitor->canUpdateUsrGrpPrtcdUsers() || $this->user_id == $visitor->user_id)
                {
                    return false;
                }
            }

        }

        return $this->canUpdateUsrGrpUsers();
    }
    public function canDiscourage(&$error = null)
    {

        $visitor = \XF::visitor();
        $options = $this->app()->options();

        $protectedUser = $options->andrewModeratorPanelProtected;
        $protectedEnabled = $options->andrewModeralPanelEnabledProtectedUsers;

        if($protectedEnabled == true)
        {
            foreach ($this->secondary_group_ids as $groupId)
            {
                if ($groupId == $protectedUser && !$visitor->canDiscourageProtectedUsers() || $this->user_id == $visitor->user_id)
                {
                    return false;
                }
            }

        }
        return $this->canDiscourageUsers();
    }

    public function canModerate(&$error = null)
    {

        $visitor = \XF::visitor();
        $options = $this->app()->options();

        $protectedUser = $options->andrewModeratorPanelProtected;
        $protectedEnabled = $options->andrewModeralPanelEnabledProtectedUsers;

        if($protectedEnabled == true)
        {
            foreach ($this->secondary_group_ids as $groupId)
            {
                if ($groupId == $protectedUser && !$visitor->canModerateProtectedUsers() || $this->user_id == $visitor->user_id)
                {
                    return false;
                }
            }
        }
        return $this->canModerateUsers();
    }

    public function getUserNoteCount()
    {
        $visitor = \XF::visitor();
        $totalNoteCount = $this->andrew_user_note_count;

        if ($visitor->canViewPrivilegedUserNotes()) {
            $count = $this->getCountForUsableCategories($visitor, $this->user_id);
        } else {
            $privilegedNoteCount = $this->andrew_privileged_user_note_count;
            $count = $this->getCountForUsableCategories($visitor, $this->user_id) - $privilegedNoteCount;
        }

        return $count;
    }

    protected function getCountForUsableCategories($visitor, $userId)
    {
        // Fetch all categories and filter them based on usability by the visitor
        $categoryFinder = \XF::finder('Andrew\ModeratorPanel:UserNoteCategory');
        $categories = $categoryFinder->fetch();

        $usableCategoryIds = [];
        foreach ($categories as $category) {
            if ($category->isUsableByUser($visitor)) {
                $usableCategoryIds[] = $category->note_category_id;
            }
        }

        $noteFinder = \XF::finder('Andrew\ModeratorPanel:UserNote');

        $noteFinder->where('note_user_id', $userId);

        if (!empty($usableCategoryIds)) {
            $noteFinder->whereOr([
                ['note_category_id', 0],
                ['note_category_id', $usableCategoryIds]
            ]);
        } else {
            // If no usable categories, only include notes with no category
            $noteFinder->where('note_category_id', 0);
        }

        return $noteFinder->total();
    }


    public function rebuildAndrewUserNotesCounter()
    {
        $counters = $this->db()->fetchRow("select
                                                note_user_id,
                                                count(1) as andrew_user_note_count
                                                from xf_andrew_mp_user_note
                                                where note_user_id = ?
                                                group by note_user_id", $this->user_id);

        if($counters) {
            $this->andrew_user_note_count = $counters['andrew_user_note_count'];
        } else {
            $this->andrew_user_note_count = 0;
        }

        $this->rebuildAndrewPrivilegedUserNotesCounter();
    }
    public function rebuildAndrewPrivilegedUserNotesCounter()
    {
        $counters = $this->db()->fetchRow("select
                                                note_user_id,
                                                count(1) as andrew_user_note_count
                                                from xf_andrew_mp_user_note
                                                where note_user_id = ?
                                                and is_privileged = 1
                                                group by note_user_id", $this->user_id);

        if($counters) {
            $this->andrew_privileged_user_note_count = $counters['andrew_user_note_count'];
        } else {
            $this->andrew_privileged_user_note_count = 0;
        }
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['andrew_user_note_count'] = [
            'type' => self::UINT,
            'default' => 0,
            'changeLog' => false,
            'api' => true
        ];
        $structure->columns['andrew_privileged_user_note_count'] = [
            'type' => self::UINT,
            'default' => 0,
            'changeLog' => false,
            'api' => true
        ];
        $structure->columns['andrew_reg_country'] = [
            'type' => self::STR,
            'changeLog' => false,
            'nullable' => true
        ];
        $structure->relations['SpamCleanerLog'] = [
            'entity' => 'XF:SpamCleanerLog',
            'type' => self::TO_ONE,
            'conditions' => 'user_id',
            'primary' => true
        ];

        return $structure;
    }

    protected function _postSave()
    {
        parent::_postSave();

        if ($this->isInsert()) {
            $ipAddress = \XF::app()->request()->getIp();
            $country = null;

            try {
                // Ensure an API is selected
                $ipAPI = \XF::app()->options()->andrewModeratorPanelIPService;
                if ($ipAPI != 0) {
                    $country = \XF::repository('XF:User')->getIpCountry($ipAddress);
                }

                if ($country !== null && $country !== '') {
                    $this->fastUpdate('andrew_reg_country', $country);
                }
            } catch (\Exception $e) {
                // Log the exception or handle it as needed
                \XF::logException($e, false, "Error fetching IP country for IP: $ipAddress");
            }
        }
    }

}