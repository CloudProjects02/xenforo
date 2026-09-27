<?php

namespace Andrew\ModeratorPanel\Stats;
use XF\Stats\AbstractHandler;

class ModeratorPanel extends AbstractHandler
{
    public function getStatsTypes()
    {
        return [
            'user_note' => \XF::phrase('andrew_moderatorpanel_user_notes'),
            'report' => \XF::phrase('reports'),
            'warning' => \XF::phrase('warnings'),
        ];
    }

    public function getData($start, $end)
    {
        $db = $this->db();

        $userNotes = $db->fetchPairs(
            $this->getBasicDataQuery('xf_andrew_mp_user_note', 'create_date'),
            [$start, $end]
        );

        $reports = $db->fetchPairs(
            $this->getBasicDataQuery('xf_report', 'first_report_date'),
            [$start, $end]
        );

        $warnings = $db->fetchPairs(
            $this->getBasicDataQuery('xf_warning', 'warning_date'),
            [$start, $end]
        );

        return [
            'user_note' => $userNotes,
            'report' => $reports,
            'warning' => $warnings
        ];
    }
}