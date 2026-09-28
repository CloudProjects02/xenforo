<?php
namespace AddonsLab\Core\Xf1;

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
        $container->set('route.prefix.fetcher', function ($c) {
            return new RoutePrefixFetcher();
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
        $container->set('ThreadCopyProvider', function () {
            return new ThreadCopyProvider();
        });
        /*$container->set('logger.dir', function ($c)
        {
            $logDirectory =  . '/internal_data/logs';
            if (!is_dir($logDirectory))
            {
                File::createDirectory($logDirectory);
            }
        });*/
    }
}