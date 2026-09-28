<?php
/*************************************************************************
 * Profile Views - XenConcept (c) 2020
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\ProfileViews\Finder;

use XF\Mvc\Entity\Finder;

class ProfileViews extends Finder
{
    public function beforeViewId($viewId)
    {
        if ($viewId)
        {
            $this->where('view_id', '<', $viewId);
        }

        return $this;
    }

    public function applyMemberVisibilityRestriction()
    {
        $visitor = \XF::visitor();

        if (!$visitor->canBypassUserPrivacy())
        {
            $constraints = [
                ['visitor_user_id' => 0],
                ['Visitor.visible' => 1, 'Visitor.user_state' => 'valid']
            ];

            if ($visitor->user_id)
            {
                $constraints[] = ['visitor_user_id' => $visitor->user_id];
            }

            $this->whereOr($constraints);
        }

        return $this;
    }
}