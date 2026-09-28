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

class Editor extends Repository
{
    public function getHideDropdown()
    {
        return $this->db()->fetchRow("SELECT * FROM xf_editor_dropdown WHERE cmd = ?",['xcHide']);
    }

    public function addHideDropdown()
    {
        $this->db()->insert('xf_editor_dropdown', [
            'cmd'  => 'xcHide',
            'icon' => 'fa-eye',
            'buttons' => json_encode($this->getHideButtons()),
            'display_order' => 10,
            'active' => 1
        ]);

        // Rebuild Cache
        \XF::repository('XF:Editor')->rebuildEditorDropdownCache();
    }

    public function addButtonsHideDropdown($newButtons = [])
    {
        $hideDropdown = $this->getHideDropdown();

        $buttons = array_merge(json_decode($hideDropdown['buttons']), $newButtons);

        $this->db()->update('xf_editor_dropdown', [
            'buttons' => json_encode(array_unique($buttons))
        ], 'cmd = ?',  ['xcHide']);

        // Rebuild Cache
        \XF::repository('XF:Editor')->rebuildEditorDropdownCache();
    }

    public function deleteHideDropdown()
    {
        $this->db()->delete('xf_editor_dropdown', 'cmd = ?', ['xcHide']);
        // Rebuild Cache
        \XF::repository('XF:Editor')->rebuildEditorDropdownCache();

    }

    // -------------------------------------------------------

    public function addDeleteHideDropdownInToolbarConfig($action)
    {
        $toolbars = \XF::options()->editorToolbarConfig;

        foreach ($toolbars as $toolbarName => &$toolbar)
        {
            foreach ($toolbar as $k => &$toolbarGroup)
            {
                if ($action == 'add' && $k == 'moreRich')
                {
                    $toolbarGroup['buttons'][] = 'xcHide';
                    $toolbarGroup['buttons'] = array_unique($toolbarGroup['buttons']);
                }
                elseif ($action == 'delete')
                {
                    if ($xcHide = array_search('xcHide', $toolbarGroup['buttons']))
                    {
                        unset($toolbarGroup['buttons'][$xcHide]);
                    }
                }
            }
        }

        \XF::repository('XF:Option')->updateOption('editorToolbarConfig', $toolbars);
        \XF::repository('XF:Editor')->rebuildEditorDropdownCache();
    }

    // -------------------------------------------------------

    protected function getHideButtons()
    {
        return array_keys($this->getHideBbCodeRepo()->getHideButtons());
    }

    /**
     * @return \XenConcept\HideBBCode\Repository\HideBbCode
     */
    protected function getHideBbCodeRepo()
    {
        return $this->repository('XenConcept\HideBBCode:HideBbCode');
    }

}