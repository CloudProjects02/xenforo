<?php

/*************************************************************************
 * Profile Views - XenConcept (c) 2020
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\ProfileViews\Cron;

class ProfileViews
{

    public static function run()
    {
        $app = \XF::app();

        /** @var \XenConcept\ProfileViews\Repository\ProfileViews $profileViewsRepo */
        $profileViewsRepo = $app->repository('XenConcept\ProfileViews:ProfileViews');
        $profileViewsRepo->pruneProfileViews();
    }

}