<?php

namespace CloudCheats\ProfileLayout;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1(): void
    {
        $this->createOption();
    }

    protected function createOption(): void
    {
        $db = \XF::db();

        // Create option group if it doesn't exist
        $groupExists = $db->fetchOne(
            "SELECT option_group_id FROM xf_option_group WHERE option_group_id = 'cloudCheatsProfileLayout'"
        );
        if (!$groupExists)
        {
            $db->insert('xf_option_group', [
                'option_group_id'    => 'cloudCheatsProfileLayout',
                'display_order'      => 1000,
                'addon_id'           => 'CloudCheats/ProfileLayout',
                'admin_navigation_id' => 'home',
            ]);
            $db->insert('xf_option_group_description', [
                'option_group_id' => 'cloudCheatsProfileLayout',
                'language_id'     => 0,
                'title'           => 'CloudCheats: Profile Layout',
                'description'     => 'Settings for the CloudCheats profile layout and scammer system.',
            ]);
        }

        // Create scammer group option if it doesn't exist
        $optExists = $db->fetchOne(
            "SELECT option_id FROM xf_option WHERE option_id = 'ccScammerGroupId'"
        );
        if (!$optExists)
        {
            $db->insert('xf_option', [
                'option_id'        => 'ccScammerGroupId',
                'option_value'     => '0',
                'default_value'    => '0',
                'edit_format'      => 'number',
                'edit_format_params' => '',
                'data_type'        => 'posint',
                'sub_options'      => '',
                'validation_class' => '',
                'validation_method' => '',
                'advanced'         => 0,
                'addon_id'         => 'CloudCheats/ProfileLayout',
            ]);
            $db->insert('xf_option_description', [
                'option_id'   => 'ccScammerGroupId',
                'language_id' => 0,
                'title'       => 'Scammer Usergroup ID',
                'explain'     => 'Enter the ID of the usergroup to assign when a user is marked as scammer. Find the group ID in Admin CP > Users > User Groups.',
            ]);
            $db->insert('xf_option_group_relation', [
                'option_id'       => 'ccScammerGroupId',
                'option_group_id' => 'cloudCheatsProfileLayout',
                'display_order'   => 10,
            ]);
        }

        \XF::app()->container('optionCache')->clearAll();
    }
}
