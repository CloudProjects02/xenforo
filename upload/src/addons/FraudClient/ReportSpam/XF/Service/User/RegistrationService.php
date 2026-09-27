<?php

namespace FraudClient\ReportSpam\XF\Service\User;

class RegistrationService extends XFCP_RegistrationService
{
    /**
     * After a user is saved, check them against the FraudClient API.
     *
     * @return \XF\Entity\User
     */
    protected function _save()
    {
        $user = parent::_save();

        $options = \XF::options();
        if (
            $options->ms_fc_enable
            && $options->ms_fc_check_on_register
            && trim((string) $options->ms_fc_api_key) !== ''
        )
        {
            /** @var \FraudClient\ReportSpam\Repository\FraudClient $fraudRepo */
            // Get the repository to make the API call.
            $fraudRepo = $this->repository('FraudClient\ReportSpam:FraudClient');
            $results = $fraudRepo->checkUser($this->user);

            // Only moderate if the check was successful and reports exist.
            $reportCount = isset($results['totalReports'])
                ? (int) $results['totalReports']
                : count($results['reports'] ?? []);
            $totalPoints = (int) ($results['totalPoints'] ?? 0);
            $minimumReports = max(1, (int) $options->ms_fc_registration_min_reports);
            $minimumPoints = max(0, (int) $options->ms_fc_registration_min_points);

            if (
                empty($results['error'])
                && $reportCount >= $minimumReports
                && $totalPoints >= $minimumPoints
            )
            {
                $user->user_state = 'moderated';
                $user->save();
            }
        }

        return $user;
    }
}
