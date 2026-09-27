<?php

namespace BS\MultiAccountDetector\XF\Repository;

class BanningRepository extends XFCP_BanningRepository
{
    public function banUser(\XF\Entity\User $user, $endDate, $reason, &$error = null, \XF\Entity\User $banBy = null)
    {
        $isBanned = parent::banUser($user, $endDate, $reason, $error, $banBy);

        if ($user->MultiAccount && !$endDate && $isBanned && \XF::options()->madCloseIfBan) {
            /** @var \BS\MultiAccountDetector\Entity\MultiAccount $mult */
            $mult = $user->MultiAccount;
            $mult->is_closed = true;
            $mult->save();
        }

        return $isBanned;
    }
}
