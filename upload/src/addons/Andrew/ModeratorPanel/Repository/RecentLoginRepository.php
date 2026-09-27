<?php
namespace Andrew\ModeratorPanel\Repository;

use XF\Entity\User;
use XF\Mvc\Entity\Repository;

class RecentLoginRepository extends Repository
{
    public function logToDatabase($user, $ipAddress, $country)
    {

        $db = \XF::db();
        $data = [
            'user_id' => $user->user_id,
            'ip' => $ipAddress,
            'country' => $country,
            'login_date' => \XF::$time,
        ];

        try {
            $db->insert('xf_andrew_mp_recent_login', $data);

        } catch (\Exception $e) {
            \XF::logError('Failed to log login: ' . $e->getMessage());
        }
    }

}