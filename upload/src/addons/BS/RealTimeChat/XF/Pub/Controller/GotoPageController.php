<?php

namespace BS\RealTimeChat\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class GotoPageController extends XFCP_GotoPageController
{
    public function actionRtcMessage()
    {
        $messageId = $this->filter('id', 'uint');
        /** @var \BS\RealTimeChat\Entity\Message $message */
        $message = $this->em()->find('BS\RealTimeChat:Message', $messageId, ['Room']);
        if (! $message || ! $message->canView()) {
            return $this->notFound();
        }

        return $this->redirect(
            $this->buildLink(
                'chat',
                ['tag' => $message->room_tag],
                [
                    'filter' => [
                        'around_message_id' => $messageId
                    ]
                ]
            )
        );
    }
}
