<?php

namespace SynapseThemes\DMCAReport\XF\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;
use XF\Mvc\Reply\Redirect;

class Dmca extends \XF\Pub\Controller\AbstractController
{
    public function actionIndex()
    {
        if (!\XF::visitor()->hasPermission('st_dmcaReport', 'canViewDMCAReport'))
        {
            throw $this->exception($this->noPermission('You do not have permission to view DMCA reports.'));
        }
        return $this->view('SynapseThemes\DMCAReport:Dmca\Index', 'st_dmca_index');
    }

    public function actionReport()
    {
        if (!\XF::visitor()->hasPermission('st_dmcaReport', 'canViewDMCAReport'))
        {
            throw $this->exception($this->noPermission('You do not have permission to submit DMCA reports.'));
        }
        if ($this->isPost())
        {
            $input = $this->filter([
                'title' => 'str',
                'email' => 'str',
                'content_urls' => 'str',
                'original_content_urls' => 'str',
                'content_description' => 'str',
                'copyright_owner' => 'str',
                'dmca_type' => 'str'
            ]);

            // Convert newline-separated URLs to arrays
            $contentUrls = array_filter(explode("\n", $input['content_urls']));
            $originalUrls = array_filter(explode("\n", $input['original_content_urls']));

            /** @var \SynapseThemes\DMCAReport\Entity\Report $report */
            $report = $this->em()->create('SynapseThemes\DMCAReport:Report');
            
            $report->bulkSet([
                'title' => $input['title'],
                'user_id' => \XF::visitor()->user_id,
                'username' => \XF::visitor()->username ?: '',
                'email' => $input['email'],
                'content_urls' => $contentUrls,
                'original_content_urls' => $originalUrls,
                'content_description' => $input['content_description'],
                'copyright_owner' => $input['copyright_owner'],
                'dmca_type' => $input['dmca_type'],
                'ip_address' => $this->request->getIp()
            ]);

            if ($report->save())
            {
                // Send alerts to staff members
                $staffMembers = $this->finder('XF:User')
                    ->with('Option', true)
                    ->where('is_staff', 1)
                    ->fetch();

                foreach ($staffMembers as $staffMember)
                {
                    /** @var \XF\Repository\UserAlert $alertRepo */
                    $alertRepo = $this->repository('XF:UserAlert');
                    
                    try {
                        $alertRepo->alert(
                            $staffMember,
                            $report->user_id,
                            $report->username,
                            'st_dmca_report',
                            $report->report_id,
                            'new_report',
                            [
                                'title' => $report->title,
                                'dmca_type' => $report->dmca_type,
                                'link' => $this->app->router('admin')->buildLink('canonical:dmca-reports/', $report)
                            ]
                        );
                    } catch (\Exception $e) {
                        // Silent catch - alerts are non-critical
                    }
                }

                return $this->redirect($this->buildLink('dmca/success'));
            }

            return $this->error($report->getErrors());
        }

        return $this->view('SynapseThemes\DMCAReport:Dmca\Report', 'st_dmca_report_form');
    }

    public function actionSuccess()
    {
        return $this->view('SynapseThemes\DMCAReport:Dmca\Success', 'st_dmca_success');
    }
} 