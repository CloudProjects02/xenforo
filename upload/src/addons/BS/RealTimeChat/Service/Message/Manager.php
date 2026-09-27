<?php

namespace BS\RealTimeChat\Service\Message;

use BS\RealTimeChat\Broadcasting\Broadcast;
use BS\RealTimeChat\Entity\Message;
use BS\XFWebSockets\Request;
use XF;
use XF\App;
use XF\Service\AbstractService;
use XF\Service\Message\Preparer;

class Manager extends AbstractService
{
    /** @var Message */
    protected Message $message;

    /** @var string|null */
    protected ?string $attachmentHash = null;

    /** @var \XF\Service\Message\Preparer */
    protected Preparer $preparer;

    protected array $quotedMessages = [];

    public function __construct(App $app, Message $message)
    {
        parent::__construct($app);
        $this->message = $message;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setMessage($message, $format = true, $checkValidity = true)
    {
        $this->preparer = $this->getMessagePreparer($format);
        $this->message->message = $this->preparer->prepare(
            $this->zalgoFix($message),
            $checkValidity
        );
        $this->quotedMessages = $this->preparer->getQuotesKeyed('rtcMessage');

        return $this->preparer->pushEntityErrorIfInvalid($this->message);
    }

    protected function getMessagePreparer($format = true)
    {
        /** @var Preparer $preparer */
        $preparer = $this->service('XF:Message\Preparer', 'chat_message', $this->message);
        if (! $format) {
            $preparer->disableAllFilters();
        }

        $preparer->setConstraint('maxLength', $this->app->options()->realTimeChatMessageMaxLength);

        return $preparer;
    }

    public function checkForSpam()
    {
        $message = $this->message;

        $user = $message->User;

        $checker = $this->app->spam()->contentChecker();
        $checker->check($user, $message->message, [
            'content_type' => 'chat_message'
        ]);

        $decision = $checker->getFinalDecision();
        switch ($decision) {
            case 'moderated':
            case 'denied':
                $checker->logSpamTrigger('chat', null);
                $message->error(XF::phrase('your_content_cannot_be_submitted_try_later'));
                break;
        }
    }

    public function getQuotedUserIds()
    {
        if (! $this->quotedMessages) {
            return [];
        }

        $messageIds = array_map(static fn($v) => (int)$v, array_keys($this->quotedMessages));
        $quotedUserIds = [];

        $db = $this->db();
        $messageUserMap = $db->fetchPairs("
			SELECT message_id, user_id
			FROM xf_bs_chat_message
			WHERE message_id IN (".$db->quote($messageIds).")
		");
        foreach ($messageUserMap as $messageId => $userId) {
            if (! isset($this->quotedMessages[$messageId]) || ! $userId) {
                continue;
            }

            $quote = $this->quotedMessages[$messageId];
            if (! isset($quote['member']) || (int)$quote['member'] === $userId) {
                $quotedUserIds[] = $userId;
            }
        }

        return $quotedUserIds;
    }

    public function getMentionedUsers($limitPermissions = true)
    {
        if ($limitPermissions) {
            return $this->message->User->getAllowedUserMentions($this->preparer->getMentionedUsers());
        }

        return $this->preparer->getMentionedUsers();
    }

    public function getMentionedUserIds($limitPermissions = true)
    {
        return array_keys($this->getMentionedUsers($limitPermissions));
    }

    public function afterInsert()
    {
        if ($this->attachmentHash) {
            $this->associateAttachments($this->attachmentHash);
        }

        Broadcast::newMessage($this->message, Request::getPageUid());
    }

    public function afterUpdate()
    {
        if ($this->attachmentHash) {
            $this->associateAttachments($this->attachmentHash);
        }
    }

    protected function associateAttachments($hash)
    {
        $message = $this->message;

        /** @var \XF\Service\Attachment\Preparer $inserter */
        $inserter = $this->service('XF:Attachment\Preparer');
        $associated = $inserter->associateAttachmentsWithContent(
            $hash,
            'chat_message',
            $message->message_id
        );
        if (! $associated) {
            return;
        }

        $message->fastUpdate('attach_count', $message->attach_count + $associated);
    }

    protected function zalgoFix($message)
    {
        return preg_replace('~(?:[\p{M}]{1})([\p{M}])+?~uis', '', $message);
    }

    /**
     * @param  string|null  $attachmentHash
     * @return Manager
     */
    public function setAttachmentHash(?string $attachmentHash): self
    {
        $this->attachmentHash = $attachmentHash;
        return $this;
    }
}
