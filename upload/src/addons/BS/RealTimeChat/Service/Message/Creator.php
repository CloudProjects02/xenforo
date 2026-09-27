<?php

namespace BS\RealTimeChat\Service\Message;

use BS\RealTimeChat\DB;
use BS\RealTimeChat\Entity\Room;
use BS\RealTimeChat\Entity\Message;
use XF\App;
use XF\Entity\User;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class Creator extends AbstractService
{
    use ValidateAndSavableTrait;

    protected Room $room;

    protected Message $message;

    protected ?User $user = null;

    protected bool $save = true;

    protected Manager $messageManager;

    protected ?CommandExecutor $commandExecutor;

    protected bool $autoSpamCheck = true;
    protected bool $performValidations = true;
    protected bool $executeCommands = true;

    public function __construct(
        App $app,
        Room $room,
        User $user = null
    ) {
        parent::__construct($app);

        $this->room = $room;

        $this->setupDefaults();

        if ($user) {
            $this->setUser($user);
        }
    }

    protected function setupDefaults()
    {
        $this->message = $this->room->getNewMessage();
        $this->messageManager = $this->service('BS\RealTimeChat:Message\Manager', $this->message);
        $this->commandExecutor = $this->service(
            'BS\RealTimeChat:Message\CommandExecutor',
            $this->message
        );
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setAutoSpamCheck($check)
    {
        $this->autoSpamCheck = (bool)$check;
    }

    public function setPerformValidations($perform)
    {
        $this->performValidations = (bool)$perform;
    }

    public function setExecuteCommands(bool $value)
    {
        $this->executeCommands = $value;
    }

    public function getPerformValidations()
    {
        return $this->performValidations;
    }

    public function setIsAutomated()
    {
        $this->setAutoSpamCheck(false);
        $this->setPerformValidations(false);
        $this->setExecuteCommands(false);
    }

    public function setMessageContent($message, $format = true)
    {
        return $this->messageManager->setMessage($message, $format, $this->performValidations);
    }

    public function setAttachmentHash($hash)
    {
        $this->messageManager->setAttachmentHash($hash);
        return $this;
    }

    public function setType(string $type): self
    {
        $this->message->type = $type;
        return $this;
    }

    /**
     * @param  \XF\Entity\User  $user
     * @return Creator
     */
    public function setUser(User $user): Creator
    {
        $this->user = $user;
        $this->message->user_id = $user->user_id;
        $this->message->hydrateRelation('User', $user);
        return $this;
    }

    public function setPmToUser(User $user)
    {
        $this->message->pm_user_id = $user->user_id;
        return $this;
    }

    public function checkForSpam()
    {
        if ($this->user && $this->user->isSpamCheckRequired()) {
            $this->messageManager->checkForSpam();
        }
    }

    protected function finalSetup()
    {
        $this->message->message_date = round(microtime(true) * 1000);

        if ($this->autoSpamCheck) {
            $this->checkForSpam();
        }

        if ($this->executeCommands) {
            $this->commandExecutor->parseAndExecuteCommand($this);
        }
    }

    protected function _validate()
    {
        $this->finalSetup();

        $this->message->preSave();
        return $this->message->getErrors();
    }

    protected function _save()
    {
        if ($this->executeCommands
            && ! $this->commandExecutor->shouldSaveMessage()
        ) {
            return null;
        }

        $message = $this->message;

        DB::repeatOnDeadlock(static function () use ($message) {
            $message->save(true, false);
        });

        $this->messageManager->afterInsert();

        return $message;
    }

    public function sendNotifications()
    {
        if (! $this->message->exists()) {
            return;
        }

        // run notifications send in job, so we don't have to wait for them to be sent
        // and user receive response much faster
        $key = substr(
            md5('chat-msg-notifications:' . $this->message->message_id),
            0,
            25
        );

        $this->app->jobManager()->enqueueUnique(
            $key,
            'BS\RealTimeChat:Message\SendNotifications',
            [
                'message_id'         => $this->message->message_id,
                'mentioned_user_ids' => $this->messageManager->getMentionedUserIds(),
                'quoted_user_ids'    => $this->messageManager->getQuotedUserIds(),
            ]
        );
    }

    /**
     * @return \BS\RealTimeChat\Repository\Room
     */
    protected function getRoomRepo()
    {
        return $this->repository('BS\RealTimeChat:Room');
    }
}
