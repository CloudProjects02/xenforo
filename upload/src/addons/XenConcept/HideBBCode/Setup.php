<?php

/*************************************************************************
 * Hide BBCode - XenConcept (c) 2021
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\HideBBCode;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

    // ################################ INSTALLATION ####################

	public function installStep1()
    {
        $this->getEditorRepo()->addHideDropdown();
    }

    public function installStep2()
    {
        $this->getEditorRepo()->addDeleteHideDropdownInToolbarConfig('add');
    }

    public function installStep3()
    {
        $db = $this->db();

        $firstPostIds = $db->fetchAllColumn("SELECT first_post_id FROM xf_thread");

        if (!empty($firstPostIds))
        {
            $posts = $db->fetchAll("SELECT post_id, message FROM xf_post WHERE post_id IN(" . $db->quote($firstPostIds) . ")");

            $db->beginTransaction();

            foreach ($posts AS $post)
            {
                $message = $post['message'];

                foreach ($this->getHideBbCodeRepo()->getLegacyHideBbCodes() AS $oldHide => $newHide)
                {
                    $regex = "#\[({$oldHide})[^\]]*\](.*)\[/\\1\]#siU";

                    $message = preg_replace_callback($regex, function ($matches) use ($oldHide, $newHide)
                    {
                        $fullBBCode = $matches[0];

                        if (in_array($oldHide, ['HIDE-THANKS', 'HIDETHANKS', 'HIDE-REPLY-THANKS', 'HIDEREPLYTHANKS']))
                        {
                            return "[{$newHide}=1]{$matches[2]}[/{$newHide}]";
                        }
                        else
                        {
                            return str_replace($oldHide, $newHide, $fullBBCode);
                        }

                    }, $message);
                }

                $db->update('xf_post', ['message' => $message], 'post_id = ?', [$post['post_id']]);
            }

            $db->commitAll();
        }
    }

    // ################################ UPGRADE TO 2.0.1 ##################

    public function upgrade2000100Step1()
    {
        $this->installStep1();
    }

    // ################################ UPGRADE TO 2.0.3 ##################

    public function upgrade2000300Step1()
    {
        $this->getEditorRepo()->addButtonsHideDropdown(['xcHideTrophy', 'xcHideReactScore']);
    }

    // ################################ UPGRADE TO 2.0.3 Patch Level 2 ##################

    public function upgrade2000392Step1()
    {
        $this->upgrade2000300Step1();
    }

    // ################################ UPGRADE TO 2.0.6 ##################

    public function upgrade2000600Step1()
    {
        $this->uninstallStep1();
    }

    public function upgrade2000600Step2()
    {
        $this->installStep1();
    }

    public function upgrade2000600Step3()
    {
        $this->installStep2();
    }

    // ################################ UPGRADE TO 2.0.7 ##################

    public function upgrade2000700Step1()
    {
        $this->getEditorRepo()->addButtonsHideDropdown(['xcHideReplyOrReact']);
    }

    // ################################ UPGRADE TO 2.0.8 ##################

    public function upgrade2000800Step1()
    {
        $this->renameOption('xc_hide_bbcode_allow_hide_first_post', 'xc_hide_bbcode_allow_hide_buttons_create_thread');
        $this->renameOption('xc_hide_bbcode_allow_hide_reply', 'xc_hide_bbcode_allow_hide_buttons_reply_thread');
    }

    // ################################ UPGRADE TO 2.0.8 Beta 4 ##################

    public function upgrade2000834Step1()
    {
        $this->getEditorRepo()->addButtonsHideDropdown(['xcHideGuest', 'xcHideAge']);
    }

    // ################################ UPGRADE TO 2.0.8 Beta 5 ##################

    public function upgrade2000835Step1()
    {
        $groupId = 'forum';

		$renamePermissions = [
			'useHideShowtogroup' => 'useHideShowtogroups',
			'useHide' => 'useHideDefault',
			'bypassHideShowtogroup' => 'bypassHideShowtogroups',
			'bypassHide' => 'bypassHideDefault',
		];

        foreach ($renamePermissions as $old => $new) 
        {
            $this->renamePermission($groupId, $old, $groupId, $new);
        }
    }

    // ################################ UPGRADE TO 2.0.9  ##################

    public function upgrade2000970Step1()
    {
        $this->getEditorRepo()->addButtonsHideDropdown(['xcHideUserGroups']);
    }

    // ############################################ UNINSTALL #########################

    public function uninstallStep1()
    {
        $this->getEditorRepo()->deleteHideDropdown();
    }

    public function uninstallStep2()
    {
        $this->getEditorRepo()->addDeleteHideDropdownInToolbarConfig('delete');
    }

    // ############################################ FUNCTIONS #########################

    public function onActiveChange($newActive, array &$jobList)
    {
        $this->db()->update('xf_editor_dropdown', ['active' => (int)$newActive], "cmd = 'xcHide'");
    }

    protected function renameOption($old, $new, $takeOwnership = false)
    {
        /** @var \XF\Entity\Option $optionOld */
        $optionOld = \XF::finder('XF:Option')->whereId($old)->fetchOne();
        /** @var \XF\Entity\Option $optionNew */
        $optionNew = \XF::finder('XF:Option')->whereId($new)->fetchOne();
        if ($optionOld && !$optionNew)
        {
            $optionOld->option_id = $new;
            if ($takeOwnership)
            {
                $optionOld->addon_id = $this->addOn->getAddOnId();
            }
            $optionOld->saveIfChanged();
        }
        else if ($takeOwnership && $optionOld && $optionNew)
        {
            $optionNew->option_value = $optionOld->option_value;
            $optionNew->addon_id = $this->addOn->getAddOnId();
            $optionNew->save();
            $optionOld->delete();
        }
    }

    protected function renamePermission($oldGroupId, $oldPermissionId, $newGroupId, $newPermissionId)
    {
        $this->db()->query('
            UPDATE IGNORE xf_permission_entry
            SET permission_group_id = ?, permission_id = ?
            WHERE permission_group_id = ? AND permission_id = ?
        ', [$newGroupId, $newPermissionId, $oldGroupId, $oldPermissionId]);

        $this->db()->query('
            UPDATE IGNORE xf_permission_entry_content
            SET permission_group_id = ?, permission_id = ?
            WHERE permission_group_id = ? AND permission_id = ?
        ', [$newGroupId, $newPermissionId, $oldGroupId, $oldPermissionId]);

        $this->db()->query('
            DELETE FROM xf_permission_entry
            WHERE permission_group_id = ? AND permission_id = ?
        ', [$oldGroupId, $oldPermissionId]);

        $this->db()->query('
            DELETE FROM xf_permission_entry_content
            WHERE permission_group_id = ? AND permission_id = ?
        ', [$oldGroupId, $oldPermissionId]);
    }

    /**
     * @return \XenConcept\HideBBCode\Repository\Editor
     */
    protected function getEditorRepo()
    {
        return \XF::repository('XenConcept\HideBBCode:Editor');
    }

    /**
     * @return \XenConcept\HideBBCode\Repository\HideBbCode
     */
    protected function getHideBbCodeRepo()
    {
        return \XF::repository('XenConcept\HideBBCode:HideBbCode');
    }
}