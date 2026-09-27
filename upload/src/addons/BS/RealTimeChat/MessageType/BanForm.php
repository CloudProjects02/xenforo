<?php

namespace BS\RealTimeChat\MessageType;

use BS\RealTimeChat\Entity\Message;

class BanForm extends AbstractMessageType
{
    public const BANS_PER_PAGE = 10;

    public function render(Message $message, array $filter): string
    {
        if (isset($message->extra_data['list'])) {
            return $this->renderBanList($message, $filter);
        }

        return $this->templater->renderMacro(
            'public:rtc_message_type_ban_macros',
            'ban_form',
            compact('message', 'filter')
        );
    }

    public function renderBanList(Message $message, array $filter): string
    {
        $page = $message->extra_data['page'] ?? 1;
        $perPage = self::BANS_PER_PAGE;

        $bansFinder = \XF::finder('BS\RealTimeChat:Ban')
            ->where('room_id', $message->room_id)
            ->limitByPage($page, $perPage);

        $bans = $bansFinder->fetch();
        $total = $bansFinder->total();

        return $this->templater->renderMacro(
            'public:rtc_message_type_ban_macros',
            'ban_list',
            compact('bans', 'message', 'filter', 'page', 'perPage', 'total')
        );
    }
}
