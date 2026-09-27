<?php

namespace BS\MultiAccountDetector\Service\Thread;

use XF\Service\AbstractService;

class ReportDuplicate extends AbstractService
{
    /**
     * @var \BS\MultiAccountDetector\Entity\MultiAccount
     */
    protected $report;

    /**
     * @var \XF\Entity\Forum
     */
    protected $forum;

    /**
     * @param  \XF\App  $app
     * @param  \BS\MultiAccountDetector\Entity\MultiAccount  $report
     * @param  \XF\Entity\Forum $forum
     */
    public function __construct(\XF\App $app, $report, $forum)
    {
        parent::__construct($app);

        $this->setReport($report);
        $this->setForum($forum);
    }

    public function create()
    {
        \XF::asVisitor($this->botUser(), function() {
            $report = $this->report;

            $none = \XF::phrase('none');
            $formattedSameFingerprintUsers = $this->pluckUsersAndWrapToBbCodes(
                $report->FingerprintJSUsers
            );
            $formattedSameFingerprintProUsers = $this->pluckUsersAndWrapToBbCodes(
                $report->FingerprintJSProUsers
            );
            $formattedSameEvercookieUsers = $this->pluckUsersAndWrapToBbCodes(
                $report->EvercookieUsers
            );

            /** @var \XF\Service\Thread\Creator $creator */
            $creator = $this->service('XF:Thread\Creator', $this->forum);
            $creator->setContent(
                \XF::phrase('mad_new_multi_account_of_user_x_detected', [
                    'name' => $report->User->username
                ]),
                \XF::phrase('mad_multi_account_detected_post_content', [
                    'user_id' => $report->User->user_id,
                    'username' => $report->User->username,
                    'fingerprint_users' => $formattedSameFingerprintUsers ?: $none,
                    'fingerprint_pro_users' => $formattedSameFingerprintProUsers ?: $none,
                    'evercookie_users' => $formattedSameEvercookieUsers ?: $none
                ]),
            );
            $creator->setIsAutomated();
            $creator->setDiscussionOpen(false);
            $creator->setDiscussionState('visible');

            $thread = $creator->getThread();
            $thread->discussion_type = 'article';

            $creator->save();
        });
    }

    /**
     * @return \XF\Entity\User
     */
    protected function botUser()
    {
        $user = null;

        $creatorUsername = \XF::options()->madThreadReportCreator;
        if ($creatorUsername) {
            /** @var \XF\Entity\User $user */
            $user = $this->em()->findOne('XF:User', ['username' => $creatorUsername]);
        }

        if (! $user) {
            $user = $this->em()->create('XF:User');
            $user->setTrusted('user_id', 0);
            $user->set('username', 'Multi-account detector');
        }

        return $user;
    }

    protected function pluckUsersAndWrapToBbCodes($records)
    {
        return $this->wrapUsersToBbCodes(
            $this->pluckUsersFromRecords($records)
        );
    }

    protected function pluckUsersFromRecords($records)
    {
        $users = [];

        foreach ($records as $record) {
            $users[] = $record->User;
        }

        return $users;
    }

    /**
     * @param  \XF\Entity\User[]  $users
     * @return string
     */
    protected function wrapUsersToBbCodes(array $users)
    {
        $output = [];

        foreach ($users as $user) {
            $output[] = '[USER='. $user->user_id .']'. $user->username .'[/USER]';
        }

        return implode(', ', $output);
    }

    public function setReport($report)
    {
        $this->report = $report;
    }

    public function setForum($forum): void
    {
        $this->forum = $forum;
    }
}