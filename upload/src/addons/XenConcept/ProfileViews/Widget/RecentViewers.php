<?php

namespace XenConcept\ProfileViews\Widget;

use XF\Widget\AbstractWidget;
use XF\Entity\User as XFUser;

class RecentViewers extends AbstractWidget
{
    protected $defaultOptions = [
        'limit' => 10,
        'display_number' => true,
        'display_user' => 'avataruser'
    ];

    public function render()
    {
        $options = $this->options;

        // Try to get the profile owner from context (member_view pages should set this)
        $user = $this->contextParams['user'] ?? null;

        // Ensure we have a real XF user entity; otherwise safely no-op
        if (!$user instanceof XFUser) {
            // Option A: bail out silently when no context user exists
            return '';
            // Option B (if you prefer): fall back to the current visitor
            // $user = \XF::visitor();
            // if (!$user || !$user->user_id) { return ''; }
        }

        // Now it is safe to call the repository method that requires a User entity
        $profileViewsFinder = $this->getProfileViewsRepo()->findLogProfileViews($user);
        $userViewersTotal = $profileViewsFinder->total();
        $userViewers = $profileViewsFinder->fetch($options['limit']);

        $viewParams = [
            'user' => $user,
            'userViewers' => $userViewers,
            'showAll' => false, // or: ($userViewersTotal - count($userViewers)) > 0
            'displayUser' => $options['display_user'],
            'displayNumber' => $options['display_number']
        ];

        return $this->renderer('xc_profile_views_recent_viewers_widget', $viewParams);
    }

    public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
    {
        $options = $request->filter([
            'limit' => 'uint',
            'display_number' => 'bool',
            'display_user' => 'str'
        ]);
        return true;
    }

    public function getOptionsTemplate()
    {
        return 'admin:xc_profile_views_widget_def_options_recent_viewers';
    }

    protected function getProfileViewsRepo()
    {
        return $this->repository('XenConcept\ProfileViews:ProfileViews');
    }
}
