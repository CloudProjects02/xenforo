<?php

namespace BS\RealTimeChat\Pub\View\Room;

use XF\Mvc\View;

class CommandsList extends View
{
    public function renderJson()
    {
        $results = [];
        foreach ($this->params['commands'] as $command) {
            $results[] = [
                'id'       => $command->getName(),
                'text'     => $command->getDescription(),
                'q'        => $this->params['q']
            ];
        }

        return [
            'results' => $results,
            'q'       => $this->params['q']
        ];
    }
}