<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection
 */

namespace SV\SignupAbuseBlocking\Report;

use SV\SignupAbuseBlocking\Entity\LogEvent;
use SV\SignupAbuseBlocking\XF\Entity\Report as ExtendedReportEntity;
use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;
use XF\Entity\Report as ReportEntity;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\Entity;
use XF\Phrase;
use XF\Report\AbstractHandler;
use function max;

/**
 * @extends AbstractHandler<UserEntity>
 */
class MultipleAccount extends AbstractHandler
{
    /**
     * @param ReportEntity|ExtendedReportEntity $report
     * @return bool
     */
    protected function canViewContent(ReportEntity $report): bool
    {
        /** @var ExtendedUserEntity $visitor */
        $visitor = \XF::visitor();

        return $visitor->canViewMultiAccountReport();
    }

    /**
     * @param ReportEntity|ExtendedReportEntity $report
     * @param Entity                            $content
     */
    public function setupReportEntityContent(ReportEntity $report, Entity $content)
    {
        /** @var UserEntity $content */
        $reportData  = $report->ReportData;
        if (!$reportData)
        {
            $logEvent = $content->getOption('svMultiAccountLogEvent') ?: null;
            if ($logEvent instanceof LogEvent)
            {
                $reportData = $logEvent->ReportData;
            }
        }

        $report->content_user_id = $content->user_id;
        $report->content_info = [
            'user_id'        => $content->user_id,
            'username'       => $content->username,
            'report_data_id' => $reportData ? $reportData->report_data_id : null
        ];
    }

    public function getContent($id)
    {
        return \XF::app()->findByContentType('user', $id, $this->getEntityWith());
    }

    public function getEntityWith(): array
    {
        return ['Profile'];
    }

    /**
     * @param ReportEntity|ExtendedReportEntity $report
     * @return Phrase
     */
    public function getContentTitle(ReportEntity $report): Phrase
    {
        $reportData = $report->ReportData;
        $count = $reportData ? max(1, $reportData->countUniqueAccounts() - 1) : 1;

        $content = $report->content_info;

        if ($report->content_user_id && $report->User)
        {
            $name = $report->User->username;
        }
        else
        {
            $name = $content['username'] ?? 'Guest';
        }

        return \XF::phraseDeferred('sv_multiple_accounts.report_subject', [
            'username' => $name,
            'count'    => $count,
        ]);
    }

    /**
     * @param ReportEntity|ExtendedReportEntity $report
     * @return string|Phrase
     * @noinspection PhpReturnDocTypeMismatchInspection
     */
    public function getContentMessage(ReportEntity $report)
    {
        return '';
    }

    /**
     * @param ReportEntity $report
     * @return string
     */
    public function getContentLink(ReportEntity $report): string
    {
        return \XF::app()->router('public')->buildLink('canonical:members', $report->User);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
