<?php

namespace Andrew\ModeratorPanel\XF\Entity;
use XF\Mvc\Entity\Structure;

class Warning extends XFCP_Warning
{
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['andrew_mp_thread_id'] = [
            'type' => self::UINT,
            'default' => 0,
            'changeLog' => false,
            'api' => true
        ];

        $structure->relations['Thread'] = [
            'entity' => 'XF:Thread',
            'type' => self::TO_ONE,
            'conditions' => [['thread_id', '=', '$andrew_mp_thread_id']],
            'primary' => true
        ];

        return $structure;
    }

    public function rebuildAndrewThreadWarning()
    {

        $addOns = \XF::app()->container('addon.cache');
        if (array_key_exists('SV/ReportImprovements', $addOns) && $addOns['SV/ReportImprovements'] >= 1010031)
        {
            $this->error(\XF::phrase('andrew_moderatorpanel_please_disable_SVReportImprovements_while_running_rebuild'));

        }

        $threads = $this->db()->fetchRow("select
                                                xf_post.thread_id
                                                from xf_warning
                                                inner join xf_post on xf_warning.content_id = xf_post.post_id 
                                                                          and xf_warning.content_type = 'post'
                                                where xf_warning.warning_id = ?", $this->warning_id);

        if($threads)
        {
            $this->andrew_mp_thread_id = $threads['thread_id'];
        }
        else
        {
            $this->andrew_mp_thread_id = 0;
        }
    }
    protected function _preSave()
    {
        if ($this->isInsert())
        {
            if ($this->content_type == 'post') {
                $threads = $this->db()->fetchRow("select
                                                xf_post.thread_id
                                                from xf_post
                                                where xf_post.post_id = ?", $this->content_id);

                $this->set('andrew_mp_thread_id', $threads['thread_id'], ['forceSet' => true]);
            } else {
                $this->set('andrew_mp_thread_id', 0, ['forceSet' => true]);
            }
        }
        return parent::_preSave();
    }

}