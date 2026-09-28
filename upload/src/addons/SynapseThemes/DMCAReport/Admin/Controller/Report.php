<?php

namespace SynapseThemes\DMCAReport\Admin\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\FormAction;

class Report extends \XF\Admin\Controller\AbstractController
{
    public function actionIndex()
    {
        $page = $this->filterPage();
        $perPage = 20;

        $reportFinder = $this->finder('SynapseThemes\DMCAReport:Report')
            ->setDefaultOrder('submit_date', 'DESC');

        $total = $reportFinder->total();
        $reports = $reportFinder->limitByPage($page, $perPage)->fetch();

        $viewParams = [
            'reports' => $reports,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage
        ];

        return $this->view('SynapseThemes\DMCAReport:Report\Listing', 'st_dmca_report_list', $viewParams);
    }

    public function actionView(ParameterBag $params)
    {
        if (!$params->report_id)
        {
            return $this->redirect($this->buildLink('dmca-reports'));
        }

        $report = $this->assertReportExists($params->report_id);

        if ($this->isPost())
        {
            $form = $this->formAction();

            $input = $this->filter([
                'status' => 'str',
                'admin_notes' => 'str'
            ]);

            $oldStatus = $report->status;
            $report->status = $input['status'];
            $report->admin_notes = $input['admin_notes'];
            $report->review_date = \XF::$time;
            $report->reviewer_id = \XF::visitor()->user_id;

            if ($report->save())
            {
                try
                {
                    $this->sendDMCAApprovalEmail($report);
                }
                catch (\Exception $e)
                {
                    // Email sending failed but report was saved
                }

                return $this->redirect($this->buildLink('dmca-reports'));
            }

            $form->logErrors($report->getErrors());
            return $this->error($report->getErrors());
        }

        $viewParams = [
            'report' => $report
        ];

        return $this->view('SynapseThemes\DMCAReport:Report\View', 'st_dmca_report_view', $viewParams);
    }

    protected function sendDMCAApprovalEmail(\SynapseThemes\DMCAReport\Entity\Report $report)
    {
        $options = \XF::options();
        
        if (!$report->email && (!$report->User || !$report->User->email))
        {
            return false;
        }

        $mail = \XF::mailer()->newMail();
        $mail->setTemplate('st_dmca_report_approval');
        
        // Use the proper XenForo method to set from address with name
        $mail->setFrom($options->contactEmailAddress, $options->boardTitle);

        if ($report->User && $report->User->email)
        {
            $mail->setTo($report->User->email);
        }
        else if ($report->email)
        {
            $mail->setTo($report->email);
        }

        $mail->setTemplateData([
            'report' => $report,
            'options' => $options
        ]);

        return $mail->send();
    }

    public function actionDelete(ParameterBag $params)
    {
        $reportId = $params->report_id;
        if (!$reportId)
        {
            return $this->notFound(\XF::phrase('requested_page_not_found'));
        }

        $report = $this->assertReportExists($reportId);

        if ($this->isPost())
        {
            $report->delete();
            return $this->redirect($this->buildLink('dmca-reports'));
        }

        $viewParams = [
            'report' => $report
        ];

        return $this->view('SynapseThemes\DMCAReport:Report\Delete', 'st_dmca_report_delete', $viewParams);
    }

    /**
     * @param int $id
     * @param array|string|null $with
     * @param null|string $phraseKey
     *
     * @return \SynapseThemes\DMCAReport\Entity\Report
     */
    protected function assertReportExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('SynapseThemes\DMCAReport:Report', $id, $with, $phraseKey);
    }

    public static function getActivityDetails(array $activities)
    {
        return \XF::phrase('managing_dmca_reports');
    }
} 