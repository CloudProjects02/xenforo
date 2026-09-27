<?php

namespace BS\RealTimeChat\ChatCommand;

use XF\App;
use BS\RealTimeChat\Entity\Message;

abstract class AbstractCommand implements ICommand
{
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function getTitle(): string
    {
        return \XF::phrase($this->getTitlePhraseName());
    }

    protected function getTitlePhraseName()
    {
        return 'rtc_command.' . $this->getName();
    }

    public function getDescription(): string
    {
        return \XF::phrase($this->getDescriptionPhraseName());
    }

    protected function getDescriptionPhraseName()
    {
        return 'rtc_command_desc.' . $this->getName();
    }

    /**
     * @return \XF\Mvc\Entity\Repository|\BS\RealTimeChat\Repository\Message
     */
    protected function getMessageRepo()
    {
        return $this->repository('BS\RealTimeChat:Message');
    }

    /**
     * @return App
     */
    public function app()
    {
        return $this->app;
    }

    /**
     * @return \XF\Db\AbstractAdapter
     */
    public function db()
    {
        return $this->app->db();
    }

    /**
     * @return \XF\Mvc\Entity\Manager
     */
    public function em()
    {
        return $this->app->em();
    }

    /**
     * @param string $repository
     *
     * @return \XF\Mvc\Entity\Repository
     */
    public function repository($repository)
    {
        return $this->app->repository($repository);
    }

    /**
     * @param $finder
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function finder($finder)
    {
        return $this->app->finder($finder);
    }

    /**
     * @return \XF\DataRegistry
     */
    public function registry()
    {
        return $this->app->registry();
    }

    /**
     * @return \ArrayObject
     */
    public function options()
    {
        return $this->app->options();
    }
}