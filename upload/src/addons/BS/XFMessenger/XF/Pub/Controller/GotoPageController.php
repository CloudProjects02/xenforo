<?php

namespace BS\XFMessenger\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class GotoPageController extends XFCP_GotoPageController
{
    public function actionConvMessage(ParameterBag $params)
    {
        $messageId = $this->filter('id', 'uint');
        /** @var \XF\Entity\ConversationMessage $convMessage */
        $convMessage = $this->em()->find('XF:ConversationMessage', $messageId, ['Conversation']);

        if (! $convMessage || ! $convMessage->canView()) {
            return $this->notFound();
        }

        return $this->redirect(
            $this->buildLink(
                'conversations/messenger',
                ['tag' => $convMessage->conversation_id],
                [
                    'filter' => [
                        'around_message_id' => $messageId
                    ]
                ]
            )
        );
    }
}
