<?php

namespace SV\SignupAbuseBlocking\Job;

use SV\SignupAbuseBlocking\Entity\ReportData as ReportDataEntity;
use SV\SignupAbuseBlocking\Repository\MultipleAccount as MultipleAccountRepo;
use SV\SignupAbuseBlocking\XF\Entity\Report as ExtendedReportEntity;
use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;
use SV\StandardLib\Helper;
use XF\Entity\Report as ReportEntity;
use XF\Job\AbstractRebuildJob;
use XF\Phrase;
use XF\Repository\User as UserRepo;

class MigrateAlterEgoDetectorReports extends AbstractRebuildJob
{
    protected function getNextIds($start, $batch): array
    {
        $db = $this->app->db();

        return $db->fetchAllColumn($db->limit(
            '
				SELECT report_id
				FROM xf_report
				WHERE report_id > ? and (content_type = ? or content_type = ?)
				ORDER BY report_id
			', $batch
        ), [$start, 'alterego', 'multiple_account']);
    }

    public function decodeJsonOrSerialized($string)
    {
        // fastest possible check for serialized data
        if (!empty($string[1]) && $string[1] == ':' && preg_match('/^([abCdioOsS]:|N;$)/', $string))
        {
            return @\unserialize($string) ?: [];
        }
        else
        {
            return @\json_decode($string, true) ?: [];
        }
    }

    protected function rebuildById($id)
    {
        $db = $this->app->db();

        $db->beginTransaction();

        /** @var ExtendedReportEntity|null $report */
        $report = Helper::find(ReportEntity::class, $id, ['User']);
        if ($report === null)
        {
            $db->commit();

            return;
        }

        $contentInfoRaw = $report->getValueSourceEncoded('content_info');
        $contentInfoOld = $this->decodeJsonOrSerialized($contentInfoRaw);
        $contentUserId = $report->content_user_id;

        if (isset($contentInfoOld['user_id']))
        {
            $contentInfo = [
                'user_id' => $contentInfoOld['user_id'],
                'username' => $contentInfoOld['username']
            ];
            if (!$contentInfo['username'] && $report->User)
            {
                $contentInfo['username'] = $report->User->username;
            }
            if (isset($contentInfoOld['report_data_id']))
            {
                $contentInfo['report_data_id'] = $contentInfoOld['report_data_id'];
            }

            $report->content_info = $contentInfo;
            $report->save(true, false);

            $db->commit();

            return;
        }
        else if (!isset($contentInfoOld[0]))
        {
            $db->commit();

            return;
        }
        $username = null;
        $userIds = [];
        foreach ($contentInfoOld[0] as $user)
        {
            if (!isset($user['user_id']))
            {
                continue;
            }
            $userId = \intval($user['user_id']);
            if (!$userId)
            {
                continue;
            }

            $userIds[$userId] = $user['username'];
            if (!$contentUserId)
            {
                $contentUserId = $userId;
            }
            if (!$username && $userId === $contentUserId)
            {
                $username = $user['username'];
            }
        }
        $contentInfo = [
            'user_id' => $contentUserId,
            'username' => $username,
            'oldUsers' => $userIds,
        ];
        if (!$contentInfo['username'] && $report->User)
        {
            $contentInfo['username'] = $report->User->username;
        }

        $user = $report->User;
        if ($user === null)
        {
            /** @var ExtendedUserEntity $user */
            $user = Helper::repository(UserRepo::class)->getGuestUser($contentInfo['username']);
            $user->svInitGuestUser($contentInfo['user_id']);
        }

        $reportDataId = $db->fetchOne('select report_id from xf_sv_multiple_account_report_data where report_id = ? and active = 1', $id);
        $reportDataId = $reportDataId ?: null;
        $isLinked = (bool)$reportDataId;
        // rewrite time to kick everything into the past
        $oldTime = \XF::$time;
        \XF::$time = $report->first_report_date;
        try
        {
            MultipleAccountRepo::get()->handleLegacyCookieValue($user, $userIds, 'legacy', true, $reportDataId);
        }
        finally
        {
            \XF::$time = $oldTime;
        }

        if ($reportDataId)
        {
            if (!$isLinked && ($reportData = Helper::find(ReportDataEntity::class, $reportDataId)))
            {
                $reportData->report_id = $id;
                $reportData->save(true, false);
            }

            $contentInfo['report_data_id'] = $reportDataId;
        }

        $report->content_type = 'multiple_account';
        $report->content_info = $contentInfo;
        $report->save(true, false);

        $db->commit();
    }

    protected function getStatusType(): Phrase
    {
        return \XF::phrase('reports');
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
