<?php

namespace BS\RealTimeChat\Entity;

use BS\RealTimeChat\Broadcasting\Broadcast;
use BS\RealTimeChat\Contracts\BroadcastibleMessage;
use BS\RealTimeChat\MessageType\IMessageType;
use BS\RealTimeChat\MessageType\MessageTypesBus;
use BS\RealTimeChat\Utils\Date;
use XF\Entity\LinkableInterface;
use XF\Entity\QuotableInterface;
use XF\Entity\ReactionTrait;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $message_id
 * @property int $user_id
 * @property string $message
 * @property int $message_date_
 * @property int $last_edit_date
 * @property int $to_user_id
 * @property int $pm_user_id
 * @property int|null $room_id
 * @property string $room_tag
 * @property string $type
 * @property int $attach_count
 * @property bool $has_been_read
 * @property array $extra_data
 * @property int $reaction_score
 * @property array $reactions_
 * @property array $reaction_users_
 *
 * GETTERS
 * @property mixed $translation
 * @property mixed $translation_message
 * @property mixed $message_date
 * @property mixed $day
 * @property \BS\RealTimeChat\MessageType\IMessageType $TypeHandler
 * @property mixed $reactions
 * @property mixed $reaction_users
 *
 * RELATIONS
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\Attachment[] $Attachments
 * @property \BS\RealTimeChat\Entity\Room $Room
 * @property \XF\Entity\User $User
 * @property \XF\Entity\User $ToUser
 * @property \XF\Entity\User $PmUser
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\ReactionContent[] $Reactions
 */
class Message extends Entity implements QuotableInterface, BroadcastibleMessage, LinkableInterface
{
    use ReactionTrait;

    public function toBroadcast(): array
    {
        return [
            'id'        => $this->message_id,
            'date'      => $this->message_date_,
            'user_id'   => $this->user_id,
            'room_tag'  => $this->room_tag,
            'type'      => $this->type,
        ];
    }

    public function getMessageDate()
    {
        return round($this->message_date_ / 1000);
    }

    public function getContentPublicRoute()
    {
        return 'chat/messages/to';
    }

    public function getContentTitle(string $context = '')
    {
        return \XF::phrase('chat_message');
    }

    public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null)
    {
        return \XF::app()->router('public')->buildLink(
            ($canonical ? 'canonical:' : '') . 'chat/messages/to',
            $this,
            $extraParams
        );
    }

    public function isTo()
    {
        return $this->to_user_id
            && $this->to_user_id === \XF::visitor()->user_id;
    }

    public function isPm()
    {
        $visitor = \XF::visitor();

        $isPmForVisitor = $this->pm_user_id === $visitor->user_id
            || $this->user_id === $visitor->user_id;

        return $this->pm_user_id && $isPmForVisitor;
    }

    public function isSearchable(): bool
    {
        return $this->TypeHandler->isSearchable($this);
    }

    public function isUnread(): bool
    {
        $visitor = \XF::visitor();
        if ($visitor->user_id === $this->user_id) {
            return false;
        }

        return $this->Room->getMember(\XF::visitor())->last_view_date < $this->message_date_
            || !$this->has_been_read;
    }

    public function hasVisitorReaction($reactionId = null): bool
    {
        if (! $reactionId) {
            return $this->getVisitorReactionId() !== null;
        }

        return $this->getVisitorReactionId() === $reactionId;
    }

    public function canView()
    {
        if (! $this->TypeHandler->canView($this)) {
            return false;
        }

        if ($this->pm_user_id) {
            $visitor = \XF::visitor();

            if ($this->pm_user_id !== $visitor->user_id
                && $this->user_id !== $visitor->user_id
            ) {
                return false;
            }
        }

        if ($this->room_id && $this->Room) {
            return $this->Room->canView();
        }

        return true;
    }

    public function canCopy()
    {
        $visitor = \XF::visitor();

        if (! $this->TypeHandler->canCopy($this)) {
            return false;
        }

        return $this->canView();
    }

    public function canEdit()
    {
        $visitor = \XF::visitor();

        if (! $this->TypeHandler->canEdit($this)) {
            return false;
        }

        if ($visitor->hasChatPermission('canEdit')
            && $this->user_id === $visitor->user_id
        ) {
            return true;
        }

        return $visitor->hasChatPermission('canEditAny');
    }

    public function canDelete()
    {
        $visitor = \XF::visitor();

        if (! $this->TypeHandler->canDelete($this)) {
            return false;
        }

        if ($visitor->hasChatPermission('canDelete')
            && $this->user_id == $visitor->user_id
        ) {
            return true;
        }

        return $visitor->hasChatPermission('canDeleteAny');
    }

    public function canReport(&$error = '', User $asUser = null)
    {
        if (! $this->TypeHandler->canReport($this)) {
            return false;
        }

        $asUser = $asUser ?: \XF::visitor();
        return $asUser->canReport($error);
    }

    public function canReact(&$error = null)
    {
        $visitor = \XF::visitor();

        if (! $this->TypeHandler->canReact($this)) {
            return false;
        }

        if ($this->Room) {
            return $this->Room->canReactMessage($this, $visitor, $error);
        }

        return $visitor->hasChatPermission('canReact');
    }

    public function canTranslate()
    {
        $visitor = \XF::visitor();

        if ($this->user_id === $visitor->user_id) {
            return false;
        }

        if (! $this->TypeHandler->isTranslatable($this)) {
            return false;
        }

        return $visitor->hasChatPermission('translateMessages');
    }

    protected function _preSave()
    {
        if ($this->isChanged('message')) {
            // clear translations cache

            $extraData = $this->extra_data ?? [];

            unset($extraData['translations'], $extraData['translation_users']);

            $this->clearCache('translation');

            $this->extra_data = $extraData;
        }
    }

    protected function _postSave()
    {
        if ($this->isInsert()) {
            $this->Room->messageAdded($this);
        }

        $this->runAiBotsJob();

        if ($this->isUpdate() && $this->hasChanges()) {
            $this->broadcastOnUpdate();

            if ($this->getOption('log_moderator')) {
                $this->app()->logger()->logModeratorChanges('chat_message', $this);
            }
        }
    }

    protected function broadcastOnUpdate()
    {
        Broadcast::messageContentUpdated($this);

        if ($this->Room->last_message_id === $this->message_id) {
            Broadcast::roomUpdated($this->Room);
        }
    }

    protected function _postDelete()
    {
        /** @var \XF\Repository\Attachment $attachRepo */
        $attachRepo = $this->repository('XF:Attachment');
        $attachRepo->fastDeleteContentAttachments('chat_message', $this->message_id);

        $this->Room->messageDeleted($this);

        Broadcast::messagesDeleted($this->room_tag, [
            'messageIds' => [$this->message_id]
        ]);

        if ($this->getOption('log_moderator')) {
            $this->app()->logger()->logModeratorAction('chat_message', $this, 'delete');
        }
    }

    protected function runAiBotsJob()
    {
        // Handle only if message is new
        if (! $this->isInsert()) {
            return;
        }

        if (! class_exists('BS\AIBots\Service\Bot\ContextHandleJobRunner')) {
            return;
        }

        if (! $this->User) {
            return;
        }

        if (! $this->User->hasPermission('bsChat', 'useAiBots')) {
            return;
        }

        /** @var \BS\AIBots\Service\Bot\ContextHandleJobRunner $jobRunner */
        $jobRunner = $this->app()->service('BS\AIBots:Bot\ContextHandleJobRunner');
        $jobRunner->runJob($this, $this->message, 'User', ['User']);
    }

    public function render(array $filter = [])
    {
        return $this->TypeHandler->render($this, $filter);
    }

    public function getTypeHandler(): IMessageType
    {
        $handler = MessageTypesBus::resolveType($this->type);
        if (! $handler) {
            $handler = MessageTypesBus::resolveType(MessageTypesBus::DEFAULT_MSG_TYPE);
        }

        return $handler;
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

        $languageCode = $this->extra_data['translation_users'][$userId];

        if (! isset($this->extra_data['translations'][$languageCode])) {
            $this->translate($languageCode);
        }

        return $this->extra_data['translations'][$languageCode]
            ?? $translation;
    }

    public function hasTranslationForUser(int $userId)
    {
        $extraData = $this->extra_data ?? [];
        $translationUsers = $extraData['translation_users'] ?? [];

        return isset($translationUsers[$userId]);
    }

    public function updateExtraData($key, $value = null)
    {
        $extraData = $this->extra_data ?? [];

        if (is_array($key)) {
            $extraData = array_merge($extraData, $key);
        } else {
            $extraData[$key] = $value;
        }

        $this->extra_data = $extraData;
    }

    public function isAttachmentEmbedded($attachmentId)
    {
        return false;
    }

    public function getQuoteWrapper($inner)
    {
        $username = $this->User->username ?? 'Somebody';
        return "[QUOTE=\"$username, rtcMessage: $this->message_id, member: $this->user_id\"]\n$inner\n[/QUOTE]\n";
    }

    public function translate(string $languageCode)
    {
        $this->TypeHandler->translate($this, $languageCode);
        $this->updateExtraData([
            'translation_users' => [
                \XF::visitor()->user_id => $languageCode
            ]
        ]);
        $this->save();
    }

    public function removeTranslation(int $userId)
    {
        $extraData = $this->extra_data ?? [];

        unset($extraData['translation_users'][$userId]);

        $this->extra_data = $extraData;
        $this->save();
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bs_chat_message';
        $structure->shortName = 'BS\RealTimeChat:Message';
        $structure->primaryKey = 'message_id';
        $structure->contentType = 'chat_message';
        $structure->columns = [
            'message_id'     => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id'        => ['type' => self::UINT, 'required' => true, 'default' => 0],
            'message'        => ['type' => self::STR, 'required' => true],
            'message_date'   => [
                'type'    => self::UINT,
                'default' => Date::getMicroTimestamp(),
                'max'     => pow(2, 63) - 2
            ],
            'last_edit_date' => ['type' => self::UINT, 'default' => 0],
            'to_user_id'     => ['type' => self::UINT, 'default' => 0],
            'pm_user_id'     => ['type' => self::UINT, 'default' => 0],
            'room_id'        => ['type' => self::UINT, 'nullable' => true, 'default' => null],
            'room_tag'       => ['type' => self::STR, 'maxLength' => 50],
            'type'           => [
                'type'      => self::STR,
                'maxLength' => 25,
                'default'   => MessageTypesBus::DEFAULT_MSG_TYPE
            ],
            'attach_count'   => ['type' => self::UINT, 'max' => 65535, 'forced' => true, 'default' => 0],
            'has_been_read'  => ['type' => self::BOOL, 'default' => false],
            'extra_data'     => ['type' => self::JSON_ARRAY, 'default' => []],
        ];
        $structure->getters = [
            'translation'         => true,
            'translation_message' => true,
            'message_date'        => true,
            'TypeHandler'         => true,
        ];
        $structure->behaviors = [
            'XF:Indexable' => [
                'checkForUpdates' => ['message', 'user_id']
            ]
        ];
        $structure->relations = [
            'Attachments' => [
                'entity' => 'XF:Attachment',
                'type' => self::TO_MANY,
                'conditions' => [
                    ['content_type', '=', 'chat_message'],
                    ['content_id', '=', '$message_id']
                ],
                'with' => 'Data',
                'order' => 'attach_date'
            ],
            'Room'   => [
                'entity'     => 'BS\RealTimeChat:Room',
                'type'       => self::TO_ONE,
                'conditions' => 'room_id',
                'primary'    => true
            ],
            'User'   => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => 'user_id',
                'primary'    => true
            ],
            'ToUser' => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => [['user_id', '=', '$to_user_id']],
                'primary'    => true
            ],
            'PmUser' => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => [['user_id', '=', '$pm_user_id']],
                'primary'    => true
            ]
        ];
        $structure->options = [
            'log_moderator' => true
        ];

        self::addReactableStructureElements($structure);

        return $structure;
    }
}
