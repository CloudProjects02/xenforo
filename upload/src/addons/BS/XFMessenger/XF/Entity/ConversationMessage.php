<?php

namespace BS\XFMessenger\XF\Entity;

use BS\RealTimeChat\Contracts\BroadcastibleMessage;
use BS\XFMessenger\Broadcasting\Broadcast;
use XF\Mvc\Entity\Structure;

/**
 * @property array|null xfm_extra_data
 * @property bool xfm_has_been_read
 * @property int xfm_last_edit_date
 * @property-read array $translation
 */
class ConversationMessage extends XFCP_ConversationMessage implements BroadcastibleMessage
{
    public function toBroadcast(): array
    {
        return [
            'id'       => $this->message_id,
            'date'     => $this->message_date,
            'user_id'  => $this->user_id,
            'room_tag' => (string)$this->conversation_id,
            'type'     => 'bubble'
        ];
    }

    public function canDelete()
    {
        $visitor = \XF::visitor();

        // Don't allow deleting the first message in a conversation
        if ($this->Conversation->first_message_id === $this->message_id) {
            return false;
        }

        if ($visitor->user_id == $this->user_id
            && $visitor->hasPermission('conversation', 'deleteOwnMessages')
        ) {
            return true;
        }

        return $visitor->hasPermission('conversation', 'deleteAnyMessages');
    }

    public function canTranslate()
    {
        $visitor = \XF::visitor();

        if ($this->user_id === $visitor->user_id) {
            return false;
        }

        $container = \XF::app()->container();

        if (! $container->offsetExists('chatGPT')) {
            return false;
        }

        /** @var \Orhanerday\OpenAi\OpenAi $api */
        $api = $container->offsetGet('chatGPT');
        if (!$api) {
            return false;
        }

        if (mb_strlen($this->message) > 4096) {
            return false;
        }

        return $visitor->hasPermission('conversation', 'translateMessages');
    }

    public function isUnread($lastRead = null)
    {
        $visitor = $lastRead instanceof \XF\Entity\User
            ? $lastRead
            : \XF::visitor();
        if ($visitor->user_id === $this->user_id) {
            return false;
        }

        return parent::isUnread($lastRead) || !$this->xfm_has_been_read;
    }

    protected function _preSave()
    {
        parent::_preSave();

        if ($this->isChanged('message')) {
            // clear translations cache

            $extraData = $this->xfm_extra_data ?? [];

            unset($extraData['translations'], $extraData['translation_users']);

            $this->clearCache('translation');

            $this->xfm_extra_data = $extraData;
        }
    }

    protected function _postSave()
    {
        parent::_postSave();

        if ($this->isUpdate() && $this->hasChanges()) {
            $this->broadcastUpdate();
        }
    }

    protected function broadcastUpdate()
    {
        $broadcastContentUpdated = false;

        if ($this->isChanged('reactions')) {
            $broadcastContentUpdated = true;
        }

        if ($this->isChanged('message') || $this->isChanged('xfm_extra_data')) {
            $broadcastContentUpdated = true;

            if ($this->Conversation->last_message_id === $this->message_id) {
                $this->Conversation->broadcastUpdate();
            }
        }

        if ($broadcastContentUpdated) {
            Broadcast::messageContentUpdated($this);
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        $this->deleteMessageFromConversation();

        Broadcast::messagesDeleted($this->Conversation, [
            'messageIds' => [$this->message_id]
        ]);
    }

    protected function deleteMessageFromConversation()
    {
        $conversation = $this->Conversation;
        $conversation->reply_count--;

        if ($conversation->last_message_id === $this->message_id) {
            /** @var \BS\XFMessenger\XF\Entity\ConversationMessage $previousMessage */
            $previousMessage = $this->finder('XF:ConversationMessage')
                ->where('conversation_id', $conversation->conversation_id)
                ->where('message_id', '<', $this->message_id)
                ->order('message_id', 'DESC')
                ->fetchOne();

            $conversation->last_message_date = $previousMessage->message_date;
            $conversation->last_message_id = $previousMessage->message_id;
            $conversation->last_message_user_id = $previousMessage->user_id;
            $conversation->last_message_username = $previousMessage->username;

            foreach ($conversation->Recipients as $recipient) {
                if ($recipient->recipient_state === 'deleted_ignored') {
                    continue;
                }

                /** @var \XF\Entity\ConversationUser $conversationUser */
                $conversationUser = $recipient->getRelationOrDefault('ConversationUser');

                $conversationUser->reply_count = $conversation->reply_count;
                $conversationUser->last_message_date = $previousMessage->message_date;
                $conversationUser->last_message_id = $previousMessage->message_id;
                $conversationUser->last_message_user_id = $previousMessage->user_id;
                $conversationUser->last_message_username = $previousMessage->username;

                $conversationUser->saveIfChanged();
            }
        }

        $conversation->clearCache('message_ids');
        $conversation->saveIfChanged();
    }

    public function getDay()
    {
        $lang = \XF::app()->userLanguage(\XF::visitor());

        $postYear = $lang->date($this->message_date, 'year');
        $currentYear = $lang->date(\XF::$time, 'year');
        if ($postYear === $currentYear) {
            return $lang->date($this->message_date, 'F d');
        }

        return $lang->date($this->message_date, 'F d, Y');
    }

    public function getTranslationMessage()
    {
        $translation = $this->translation;
        if (! empty($translation['error'])) {
            return $this->message;
        }

        return $translation['message'] ?? $this->message;
    }

    public function getTranslation()
    {
        $visitor = \XF::visitor();
        return $this->getTranslationForUser($visitor->user_id);
    }

    public function getTranslationForUser(int $userId)
    {
        $translation = [
            'message' => $this->message,
        ];

        if (! $this->hasTranslationForUser($userId)) {
            return $translation;
        }

        $languageCode = $this->xfm_extra_data['translation_users'][$userId];

        if (! isset($translation[$languageCode])) {
            $this->translate($languageCode);
        }

        return $this->xfm_extra_data['translations'][$languageCode]
            ?? $translation;
    }

    public function hasTranslationForUser(int $userId)
    {
        $extraData = $this->xfm_extra_data ?? [];
        $translationUsers = $extraData['translation_users'] ?? [];

        return isset($translationUsers[$userId]);
    }

    public function translate(string $languageCode)
    {
        $this->app()->service('BS\XFMessenger:ConversationMessage\Translate', $this)
            ->translate($languageCode);

        $this->updateXfmExtraData([
            'translation_users' => [
                \XF::visitor()->user_id => $languageCode
            ]
        ]);
        $this->saveIfChanged();
    }

    public function removeTranslation(int $userId)
    {
        $extraData = $this->xfm_extra_data ?? [];

        unset($extraData['translation_users'][$userId]);

        $this->xfm_extra_data = $extraData;
        $this->saveIfChanged();
    }

    public function updateXfmExtraData($key, $value = null)
    {
        $extraData = $this->xfm_extra_data ?? [];

        if (is_array($key)) {
            $extraData = array_merge($extraData, $key);
        } else {
            $extraData[$key] = $value;
        }

        $this->xfm_extra_data = $extraData;
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['xfm_extra_data'] = ['type' => self::JSON_ARRAY, 'default' => []];
        $structure->columns['xfm_has_been_read'] = ['type' => self::BOOL, 'default' => false];
        $structure->columns['xfm_last_edit_date'] = ['type' => self::UINT, 'default' => 0];
        $structure->getters['translation'] = true;
        $structure->getters['translation_message'] = true;
        $structure->getters['day'] = true;

        return $structure;
    }
}
