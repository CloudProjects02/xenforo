<?php

namespace BS\RealTimeChat\Service\Message;

use BS\RealTimeChat\Entity\Message;
use XF\App;
use XF\Entity\User;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class Editor extends AbstractService
{
    use ValidateAndSavableTrait;

    protected Message $message;

    protected User $user;

    protected Manager $messageManager;

    protected bool $autoSpamCheck = true;
    protected bool $performValidations = true;

    protected bool $silent = false;

    public function __construct(App $app, Message $message, User $user)
    {
        parent::__construct($app);

        $this->message = $message;
        $this->user = $user;

        $this->setupDefaults();
    }

    public function setSilent($silent)
    {
        $this->silent = (bool)$silent;
    }

    protected function setupDefaults()
    {
        $this->messageManager = $this->service('BS\RealTimeChat:Message\Manager', $this->message);
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setAutoSpamCheck($check)
    {
        $this->autoSpamCheck = (bool)$check;
    }

    public function setPerformValidations($perform)
    {
        $this->performValidations = (bool)$perform;
    }

    public function getPerformValidations()
    {
        return $this->performValidations;
    }

    public function setIsAutomated()
    {
        $this->setAutoSpamCheck(false);
        $this->setPerformValidations(false);
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

    public function checkForSpam()
    {
        if ($this->user->isSpamCheckRequired()) {
            $this->messageManager->checkForSpam();
        }
    }

    protected function finalSetup()
    {
        if ($this->autoSpamCheck) {
            $this->checkForSpam();
        }

        if (! $this->silent) {
            $this->message->last_edit_date = \XF::$time;
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
        $message = $this->message;

        $db = $this->db();
        $db->beginTransaction();

        $message->save(true, false);

        $db->commit();

        $this->messageManager->afterUpdate();

        return $message;
    }
}
