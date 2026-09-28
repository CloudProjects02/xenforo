<?php

namespace SynapseThemes\XFRMIndexSliders\Widget;

use XF\Widget\AbstractWidget;

class RecentResourcesSlider extends AbstractWidget
{
    protected $defaultOptions = [
        'limit' => 5
    ];

    public function getOptionsTemplate()
    {
        return 'admin:synapsethemes_xfrmindexsliders_widget_recent_resources_options';
    }

    public function render()
    {
        $options = $this->options;
        $limit = max(1, min($options['limit'], 20));

        /** @var \XFRM\Repository\ResourceItem $resourceRepo */
        $resourceRepo = $this->repository('XFRM:ResourceItem');
        
        $finder = $resourceRepo->findResourcesForOverviewList()
            ->order('resource_date', 'DESC')
            ->limit($limit);

        $resources = $finder->fetch();

        if (!$resources->count())
        {
            return '';
        }

        $viewParams = [
            'title' => \XF::phrase('synapsethemes_xfrmindexsliders_recent_resources'),
            'resources' => $resources,
            'options' => $options
        ];

        return $this->renderer('synapsethemes_xfrmindexsliders_widget_recent_resources', $viewParams);
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
        if ($options['limit'] > 20)
        {
            $options['limit'] = 20;
        }

        return true;
    }
} 