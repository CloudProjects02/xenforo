<?php

namespace Andrew\ModeratorPanel\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Structure;


class UserNoteCategory extends \XF\Mvc\Entity\Entity
{
    public function isUsableByUser(?User $user = null)
    {
        $user = $user ?: \XF::visitor();

        // Ensure allowed_user_group_ids is an array
        $allowedUserGroupIds = $this->allowed_user_group_ids;

        foreach ($allowedUserGroupIds as $userGroupId) {
            if ($userGroupId == -1 || $user->isMemberOf($userGroupId)) {
                return true;
            }
        }

        return false;
    }
    protected function _preSave()
    {
        if ($this->isChanged('title'))
        {
            $existingCategory = $this->repository('Andrew\ModeratorPanel:UserNoteCategory')
                ->findCategoryByTitle($this->title)
                ->fetchOne();

            if ($existingCategory)
            {
                $this->error(\XF::phrase('andrew_moderatorpanel_category_title_must_be_unique'), 'title');
            }
        }
    }
    protected function _postDelete()
    {
        // category id on user note to 0 when the category is deleted
        $db = $this->db();
        $db->update('xf_andrew_mp_user_note', ['note_category_id' => 0], 'note_category_id = ?', $this->note_category_id);
    }


    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_andrew_mp_user_note_category';
        $structure->shortName = 'Andrew\ModeratorPanel:UserNoteCategory';
        $structure->primaryKey = 'note_category_id';
        $structure->contentType = 'andrew_user_note_category';
        $structure->columns = [
            'note_category_id' => ['type' => self::UINT, 'autoIncrement' => true, 'changeLog' => false],
            'title' => ['type' => self::STR, 'changeLog' => false],
            'use_count' => ['type' => self::UINT, 'default' => 0, 'changeLog' => false],
            'allowed_user_group_ids' => ['type' => self::LIST_COMMA, 'default' => [-1]],
            'last_used_date' => ['type' => self::UINT, 'default' => 0, 'changeLog' => false],
            'display_order' => ['type' => self::UINT, 'default' => 0, 'changeLog' => false],
        ];
        $structure->getters = [
        ];
        $structure->behaviors = [
        ];
        $structure->options = [
        ];
        $structure->relations = [
        ];

        return $structure;
    }

}
