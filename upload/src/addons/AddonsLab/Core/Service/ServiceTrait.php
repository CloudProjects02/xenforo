<?php
namespace AddonsLab\Core\Service;

use AddonsLab\Core\App;

trait ServiceTrait
{
    /**
     * @var App
     */
    protected $app;

    /**
     * @return App
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * @param App $app
     */
    public function setApp($app)
    {
        $this->app = $app;
    }
}