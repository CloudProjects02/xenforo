<?php

namespace BS\RealTimeChat\Setup;

trait SetupWidgets
{
    public function installStep3()
    {
        foreach ($this->getWidgets() as $widgetKey => $widget) {
            $this->createWidget($widgetKey, $widget['definition_id'], $widget['config'], $widget['title']);
        }
    }

    protected function getWidgets()
    {
        $widgets = [];

        $widgets['real_time_chat'] = [
            'definition_id' => 'real_time_chat',
            'config'        => [
                'positions' => []
            ],
            'title'         => ''
        ];

        return $widgets;
    }
}
