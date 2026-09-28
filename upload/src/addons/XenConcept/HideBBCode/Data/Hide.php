<?php
/**
 * Created by PhpStorm.
 * User: Remi
 * Date: 30/01/2021
 * Time: 19:34
 */

namespace XenConcept\HideBBCode\Data;

class Hide
{
    public function getHideBbCodes()
    {
        return [
            'hide' => [
                'name' => 'xcHideDefault',
                'hasOption' => '',
                'optionRegex' => '',
            ],
            'hidereply' => [
                'name' => 'xcHideReply',
                'hasOption' => 'no',
                'optionRegex' => ''
            ],
            'hideposts' => [
                'name' => 'xcHidePosts',
                'hasOption' => 'yes',
                'optionRegex' => '/^[0-9]+$/i'
            ],
            'hidethanks' => [
                'name' => 'xcHideThanks',
                'hasOption' => 'no',
                'optionRegex' => ''
            ],
            'hidereplythanks' => [
                'name' => 'xcHideReplyThanks',
                'hasOption' => 'no',
                'optionRegex' => ''
            ],
            'hidereact' => [
                'name' => 'xcHideReact',
                'hasOption' => 'yes',
                'optionRegex' => '/^[0-9,]+$/i'
            ],
            'hidereplyreact' => [
                'name' => 'xcHideReplyReact',
                'hasOption' => 'yes',
                'optionRegex' => '/^[0-9,]+$/i'
            ],
            'hidereplyorreact' => [
                'name' => 'xcHideReplyOrReact',
                'hasOption' => 'yes',
                'optionRegex' => '/^[0-9,]+$/i'
            ],
            'hideusergroups' => [
                'name' => 'xcHideUserGroups',
                'hasOption' => 'yes',
                'optionRegex' => '/^[0-9,]+$/i'
            ],
            'hideshowtogroups' => [
                'name' => 'xcHideShowtogroups',
                'hasOption' => 'yes',
                'optionRegex' => '/^[0-9,]+$/i'
            ],
            'hideuser' => [
                'name' => 'xcHideUser',
                'hasOption' => 'yes',
                'optionRegex' => '/^[0-9,]+$/i'
            ],
            'hideguest' => [
                'name' => 'xcHideGuest',
                'hasOption' => 'no',
                'optionRegex' => ''
            ],
            'hideage' => [
                'name' => 'xcHideAge',
                'hasOption' => 'yes',
                'optionRegex' => '/^[0-9]+$/i'
            ],
            'hidetrophy' => [
                'name' => 'xcHideTrophy',
                'hasOption' => 'yes',
                'optionRegex' => '/^[0-9]+$/i'
            ],
            'hidereactscore' => [
                'name' => 'xcHideReactScore',
                'hasOption' => 'yes',
                'optionRegex' => '/^[0-9]+$/i'
            ],
        ];
    }

    public function getHideButtons(): array
    {
        return [
            'xcHideDefault' => [
                'title' => \XF::phrase('xc_hide_bbcode_hide_bb_code_title.hide'),
                'fa' => 'fa-eye-slash'
            ],
            'xcHideReply' => [
                'title' => \XF::phrase('xc_hide_bbcode_hide_bb_code_title.hidereply'),
                'fa' => 'fa-reply'
            ],
            'xcHidePosts' => [
                'title' => \XF::phrase('xc_hide_bbcode_hide_bb_code_title.hideposts'),
                'fa' => 'fa-eye-slash'
            ],
            'xcHideThanks' => [
                'title' => \XF::phrase('xc_hide_bbcode_hide_bb_code_title.hidethanks'),
                'fa' => 'fa-eye-slash'
            ],
            'xcHideReplyThanks' => [
                'title' => \XF::phrase('xc_hide_bbcode_hide_bb_code_title.hidereplythanks'),
                'fa' => 'fa-eye-slash'
            ],
            'xcHideReact' => [
                'title' => \XF::phrase('xc_hide_bbcode_hide_bb_code_title.hidereact'),
                'fa' => 'fa-eye-slash'
            ],
            'xcHideReplyReact' => [
                'title' => \XF::phrase('xc_hide_bbcode_hide_bb_code_title.hidereplyreact'),
                'fa' => 'fa-eye-slash'
            ],
            'xcHideReplyOrReact' => [
                'title' => \XF::phrase('xc_hide_bbcode_hide_bb_code_title.hidereplyorreact'),
                'fa' => 'fa-eye-slash'
            ],
            'xcHideUserGroups' => [
                'title' => \XF::phrase('xc_hide_bbcode_hide_bb_code_title.hideusergroups'),
                'fa' => 'fa-eye-slash'
            ],
            'xcHideShowtogroups' => [
                'title' => \XF::phrase('xc_hide_bbcode_hide_bb_code_title.hideshowtogroups'),
                'fa' => 'fa-eye-slash'
            ],
            'xcHideUser' => [
                'title' => \XF::phrase('xc_hide_bbcode_hide_bb_code_title.hideuser'),
                'fa' => 'fa-user'
            ],
            'xcHideGuest' => [
                'title' => \XF::phrase('xc_hide_bbcode_hide_bb_code_title.hideguest'),
                'fa' => 'fa-user'
            ],
            'xcHideAge' => [
                'title' => \XF::phrase('xc_hide_bbcode_hide_bb_code_title.hideage'),
                'fa' => 'fa-user'
            ],
            'xcHideTrophy' => [
                'title' => \XF::phrase('xc_hide_bbcode_hide_bb_code_title.hidetrophy'),
                'fa' => 'fa-trophy'
            ],
            'xcHideReactScore' => [
                'title' => \XF::phrase('xc_hide_bbcode_hide_bb_code_title.hidereactscore'),
                'fa' => 'fa-eye-slash'
            ]
        ];
    }

    public function getLegacyHideBbCodes()
    {
        return [
            'HIDE-REPLY'        => 'HIDEREPLY',
            'HIDE-POSTS'        => 'HIDEPOSTS',
            'HIDE-THANKS'       => 'HIDETHANKS',
            'HIDE-REPLY-THANKS' => 'HIDETREPLYHANKS',
            'SHOWTOGROUPS'      => 'HIDESHOWTOGROUPS'
        ];
    }
}