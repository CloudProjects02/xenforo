<?php
/**
 * Created by PhpStorm.
 * User: Remi
 * Date: 04/02/2021
 * Time: 00:18
 */

namespace XenConcept\HideBBCode\Listener;

class Editor
{

    /**
     * @param array $data
     *   -> dialog
     *   -> view
     *   -> template
     *   -> params
     * @param \XF\Pub\Controller\AbstractController $controller
     */
    public static function dialog(array &$data, \XF\Pub\Controller\AbstractController $controller)
    {
        if (!($hideBbCode = self::getHideBbCodeRepo()->getHideBbCode($data['dialog'])))
        {
            return null;
        }

        $params = [
            'tag' => $data['dialog'],
            'title' => \XF::phrase('xc_hide_bbcode_hide_bb_code_title.' . $data['dialog']),

            'macroName' => $data['dialog'],

            'options' => [
                'hideMappedTo' => str_replace('xc', '', strtolower(\XF::options()->xc_hide_bbcode_hideMappedTo)),
                'usergroups' => self::getUserGroupTitlePairs(),
                'reactions' => self::getReactionTitlePairs(),
            ]
        ];

        if (in_array($data['dialog'], ['hidereact', 'hidereplyreact', 'hidereplyorreact']))
        {
            $params['macroName'] = 'react';
        }

        $data['template'] = 'xc_hide_bbcode_editor_dialog_hide';
        $data['params'] = $params;
    }

    protected static function getUserGroupTitlePairs()
    {
        return \XF::repository('XF:UserGroup')->getUserGroupTitlePairs();
    }

    protected static function getReactionTitlePairs()
    {
        return \XF::repository('XF:Reaction')
            ->findReactionsForList(true)
            ->where('reaction_id',  \XF::options()->xc_hide_bbcode_usableReactions)
            ->fetch()
            ->pluckNamed('title', 'reaction_id');
    }

    public static function buttonData(array &$buttons, \XF\Data\Editor $editorData)
    {
        $hideButtons = self::getHideBbCodeRepo()->getHideButtons();

        $buttons = array_merge($buttons, $hideButtons);
    }

    /**
     * @return \XenConcept\HideBBCode\Repository\HideBbCode
     */
    protected static function getHideBbCodeRepo()
    {
        /** @noInspection Php  */
        return \XF::repository('XenConcept\HideBBCode:HideBbCode');
    }
}