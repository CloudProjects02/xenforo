<?php

namespace Andrew\ModeratorPanel\Entity;

use XF\Mvc\Entity\Structure;


class UserNote extends \XF\Mvc\Entity\Entity
{

    protected $_originalIsPrivileged;
    protected $_originalCategory;

    protected function _preSave()
    {
        // Store the original value of the checkbox before saving
        $this->_originalIsPrivileged = $this->getExistingValue('is_privileged');
        $this->_originalCategory = $this->getExistingValue('note_category_id');
    }

    protected function _postSave()
    {
        $db = $this->db();
        $userId = $this->note_user_id;
        $noteCategoryId = $this->note_category_id;

        if ($this->isInsert())
        {
            // Increment the user note count and category use count on insert
            $db->query("
            UPDATE xf_user
            SET andrew_user_note_count = andrew_user_note_count + 1
            WHERE user_id = ?", $userId);

            $db->query("
            UPDATE xf_andrew_mp_user_note_category
            SET use_count = use_count + 1
            WHERE note_category_id = ?", $noteCategoryId);

            // Handle privileged note count
            if ($this->is_privileged)
            {
                $db->query("
                UPDATE xf_user
                SET andrew_privileged_user_note_count = andrew_privileged_user_note_count + 1
                WHERE user_id = ?", $userId);
            }
        } elseif ($this->isUpdate()) {
            // Handle change in privilege status
            if ($this->_originalIsPrivileged != $this->is_privileged) {
                $db->query("
                UPDATE xf_user
                SET andrew_privileged_user_note_count = CASE WHEN andrew_privileged_user_note_count > 0 THEN andrew_privileged_user_note_count + " . ($this->is_privileged ? "1" : "-1") . " ELSE 0 END
                WHERE user_id = ?", $userId);
            }

            // Handle change in category
            if ($this->_originalCategory != $this->note_category_id) {
                if ($this->_originalCategory) {
                    $db->query("
                    UPDATE xf_andrew_mp_user_note_category
                    SET use_count = CASE WHEN use_count > 0 THEN use_count - 1 ELSE 0 END
                    WHERE note_category_id = ?", $this->_originalCategory);
                }

                if ($this->note_category_id) {
                    $db->query("
                    UPDATE xf_andrew_mp_user_note_category
                    SET use_count = use_count + 1
                    WHERE note_category_id = ?", $noteCategoryId);
                }
            }
        }
    }

    protected function _postDelete()
    {

        if ($this->getOption('log_moderator'))
        {
            $this->app()->logger()->logModeratorAction('user_note', $this, 'delete_hard');
        }

        $db = $this->db();
        $userId = $this->note_user_id;
        $noteCategoryId = $this->note_category_id;

        // Decrement the andrew_user_note_count on delete
        $db->query("
        UPDATE xf_user
        SET andrew_user_note_count = andrew_user_note_count - 1
        WHERE user_id = ?", $userId);

        // Decrement the category use count on insert
        $db->query("
            UPDATE xf_andrew_mp_user_note_category
            SET use_count = use_count - 1
            WHERE note_category_id = ?", $noteCategoryId);

        // Handle the privileged note count on delete
        if ($this->is_privileged) {
            $db->query("
            UPDATE xf_user
            SET andrew_privileged_user_note_count = andrew_privileged_user_note_count - 1
            WHERE user_id = ?", $userId);
        }
    }

    public function canDeleteNote()
    {
        $visitor = \XF::visitor();
        $isPrivileged = $this->is_privileged;

        if($visitor->user_id == $this->user_id)
        {
            if($isPrivileged) {
                return $visitor->canDeleteOwnPrivilegedUserNote();
            } else {
                return $visitor->canDeleteOwnUserNote();
            }
        } else {
            if($isPrivileged) {
                return $visitor->canDeleteOtherPrivilegedUserNote();
            } else {
                return $visitor->canDeleteOtherUserNote();
            }
        }

    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_andrew_mp_user_note';
        $structure->shortName = 'Andrew\ModeratorPanel:UserNote';
        $structure->primaryKey = 'note_id';
        $structure->contentType = 'andrew_user_note';
        $structure->columns = [
            'note_id' => ['type' => self::UINT, 'autoIncrement' => true, 'changeLog' => false],
            'note_user_id' => ['type' => self::UINT, 'changeLog' => false],
            'user_id' => ['type' => self::UINT, 'changeLog' => false],
            'edit_user_id' => ['type' => self::UINT, 'changeLog' => false],
            'username' => ['type' => self::STR, 'changeLog' => false],
            'create_date' => ['type' => self::UINT, 'default' => \XF::$time, 'changeLog' => false],
            'last_edit_date' => ['type' => self::UINT, 'default' => 0, 'changeLog' => false],
            'message' => ['type' => self::STR, 'changeLog' => false],
            'is_privileged' => ['type' => self::UINT, 'default' => 0, 'changeLog' => false],
            'note_category_id' => ['type' => self::UINT, 'default' => 0, 'changeLog' => false],
        ];
        $structure->getters = [

        ];
        $structure->behaviors = [

        ];
        $structure->options = [
            'log_moderator' => true
        ];
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'NoteUser' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => [['user_id', '=', '$note_user_id']],
                'primary' => true
            ],
            'EditUser' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => [['user_id', '=', '$edit_user_id']],
                'primary' => true
            ],
            'Category' => [
                'entity' => 'Andrew\ModeratorPanel:UserNoteCategory',
                'type' => self::TO_ONE,
                'conditions' => 'note_category_id',
                'primary' => true
            ],
        ];

        return $structure;
    }

}
