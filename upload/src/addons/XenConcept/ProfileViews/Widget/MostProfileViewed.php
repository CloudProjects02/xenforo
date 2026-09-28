<?php

/*************************************************************************
 * Profile Views - XenConcept (c) 2017
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\ProfileViews\Widget;

use XF\Widget\AbstractWidget;

class MostProfileViewed extends AbstractWidget
{
    protected $defaultOptions = [
        'limit' => 10,
        'display_avatar' => true,
    ];


    public function render()
    {
        $options = \XF::options();

        if (!$options->xc_profile_views_enable_most_profile_viewed)
        {
            return '';
        }

        /** @var \XenConcept\ProfileViews\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->canViewMostProfilesViewed($error))
        {
            return '';
        }

        $limit = $this->options['limit'];

        $profileViewsRepo = $this->getProfileViewsRepo();
        $mostProfileViewedFinder = $profileViewsRepo->findMostProfileViewed();

        $mostProfilesViewed = $mostProfileViewedFinder->fetch($limit);

        $viewParams = [

            'mostProfilesViewed' => $mostProfilesViewed,
            'displayAvatar' => $this->options['display_avatar']
        ];

        return $this->renderer('xc_profile_views_most_profiles_viewed_widget', $viewParams);
    }

    public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
    {
        $options = $request->filter([
            'limit' => 'uint',
            'display_avatar' => 'bool',
        ]);

        return true;
    }

    /**
     * @return string|null
     */
    public function getOptionsTemplate()
    {
        return 'admin:xc_profile_views_widget_def_options_most_profile_viewed';
    }

    /**
     * @return \XenConcept\ProfileViews\Repository\ProfileViews
     */
    protected function getProfileViewsRepo()
    {
        return $this->repository('XenConcept\ProfileViews:ProfileViews');
    }
}