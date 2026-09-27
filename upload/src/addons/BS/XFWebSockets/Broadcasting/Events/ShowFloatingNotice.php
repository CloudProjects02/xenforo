<?php

namespace BS\XFWebSockets\Broadcasting\Events;

use BS\XFWebSockets\Broadcasting\Event;
use BS\XFWebSockets\Broadcasting\UserChannel;
use XF\Entity\User;

class ShowFloatingNotice implements Event
{
    protected User $toUser;
    protected string $html;
    protected int $duration;
    protected string $containerClass;

    public function __construct(
        User $toUser,
        string $html,
        int $duration = 12000,
        string $containerClass = 'notice--primary'
    ) {
        $this->toUser = $toUser;
        $this->html = $html;
        $this->duration = $duration;
        $this->containerClass = $containerClass;
    }

    public function toChannels(): array
    {
        return [
            new UserChannel($this->toUser->user_id)
        ];
    }

    public function payload(): array
    {
        return [
            'html' => $this->html,
            'duration' => $this->duration,
            'containerClass' => $this->containerClass
        ];
    }

    public function broadcastAs(): string
    {
        return 'FloatingNotification';
    }
}