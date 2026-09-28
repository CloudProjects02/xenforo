<?php
namespace AddonsLab\Core\vB3;

use AddonsLab\Core\Container;

trait AppTrait
{
    /**
     * @param Container $container
     */
    protected function _setupContainer($container)
    {
        $container->set('option.provider', function ($c) {
            return new OptionProvider();
        });
        $container->set('phrase.provider', function ($c) {
            return new PhraseProvider();
        });
        $container->set('cookie.provider', function ($c) {
            return new CookieProvider();
        });
        $container->set('session.provider', function ($c) {
            return new SessionProvider();
        });
        $container->set('db.provider', function ($c) {
            return new DbProvider();
        });
        $container->set('RegistryProvider', function () {
            return new RegistryProvider();
        });
        $container->set('AccountDeleter', function () {
            return new AccountDeleter();
        });
    }
}