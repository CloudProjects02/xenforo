<?php

namespace BS\MultiAccountDetector\Finder;

use XF\Mvc\Entity\Finder;

class MultiAccount extends Finder
{
    public function onUser(\XF\Entity\User $user)
    {
        $this->where(['user_id', '=', $user->user_id]);

        return $this;
    }

    public function inTimeFrame($timeFrame = null)
    {
        if ($timeFrame) {
            if (! is_array($timeFrame)) {
                $timeFrom = $timeFrame;
                $timeTo = time();
            } else {
                $timeFrom = $timeFrame[0];
                $timeTo = $timeFrame[1];
            }

            $this->where(['multi_account_date', '>=', $timeFrom]);
            $this->where(['multi_account_date', '<=', $timeTo]);
        }

        return $this;
    }

    public function onlyOpen($open = true)
    {
        $this->where([['is_closed', '=', ! $open]]);

        return $this;
    }
}