<?php

namespace Andrew\ModeratorPanel\Widget;

class RecentWarnings extends \XF\Widget\AbstractWidget
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

        $finder = \XF::finder('XF:Warning');
        $warnings = $finder
            ->with('User')
            ->order('warning_date','DESC')
            ->limit($limit)
            ->fetch();

        $viewParams = [
            'warnings' => $warnings
        ];
        return $this->renderer('widget_andrew_moderatorpanel_recent_warnings', $viewParams);
    }

    public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
    {
        $options = $request->filter([
            'limit' => 'uint'
        ]);

        if ($options['limit'] < 1)
        {
            $options['limit'] = 1;
        }

        return true;
    }
}