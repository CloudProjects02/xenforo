<?php

namespace Andrew\ModeratorPanel\Widget;

class RecentBans extends \XF\Widget\AbstractWidget
{
    protected $defaultOptions = [
        'limit' => 5,
        'hideSpamUsers' => 1
    ];

    protected function getDefaultTemplateParams($context)
    {
        $params = parent::getDefaultTemplateParams($context);

        return $params;
    }

    public function render()
    {
        $options = $this->options;
        $limit = $options['limit'];
        $finder = \XF::finder('XF:User');

        $hideSpamUsers = $options['hideSpamUsers'];
        $spamCleanerPhrase = \XF::phrase('spam_cleaner_ban_reason');
        $spamPhrase = \XF::phrase('spam');

        If($hideSpamUsers == 1)
        {
            $users = $finder
                ->with('Ban')
                ->with('SpamCleanerLog',false)
                ->where('is_banned',1)
                ->where('SpamCleanerLog.user_id', null)
                ->where('Ban.user_reason','!=',$spamCleanerPhrase)
                ->where('Ban.user_reason','!=',$spamPhrase)
                ->order('Ban.ban_date','DESC')
                ->limit($limit)
                ->fetch();
        }
        else
        {
            $users = $finder
                ->with('Ban')
                ->where('is_banned',1)
                ->order('Ban.ban_date','DESC')
                ->limit($limit)
                ->fetch();
        }

        $viewParams = [
            'users' => $users
        ];
        return $this->renderer('widget_andrew_moderatorpanel_recent_bans', $viewParams);
    }

    public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
    {
        $options = $request->filter([
            'limit' => 'uint',
            'hideSpamUsers' => 'uint'
        ]);

        if ($options['limit'] < 1)
        {
            $options['limit'] = 1;
        }

        return true;
    }
}