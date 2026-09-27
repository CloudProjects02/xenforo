<?php

namespace BS\RealTimeChat\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class RoomLink extends AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        if ($this->options()->rtcDisable) {
            throw $this->exception($this->notFound());
        }

        $this->assertRegistrationRequired();
    }

    public function actionIndex(ParameterBag $params)
    {
        $link = $this->assertValidLink($params->link_id, ['Room']);

        $member = $link->Room->getMember(\XF::visitor());
        if ($link->Room->isPublic() || $member->exists()) {
            return $this->redirect($this->buildLink('chat', $link->Room));
        }

        if ($this->isPost()) {
            $link->join(\XF::visitor());
            return $this->redirect($this->buildLink('chat', $link->Room));
        }

        return $this->view(
            'BS\RealTimeChat:RoomLink\Join',
            'real_team_chat_room_link_join',
            compact('link')
        );
    }

    /**
     * @param $id
     * @return \BS\RealTimeChat\Entity\RoomLink
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertValidLink($id, array $with = [])
    {
        $link = $this->finder('BS\RealTimeChat:RoomLink')
            ->where('link_id', $id)
            ->whereOr(['expire_date', '>', \XF::$time], ['expire_date', '=', null])
            ->with($with)
            ->fetchOne();
        if (!$link) {
            throw $this->exception($this->notFound());
        }
        return $link;
    }
}
