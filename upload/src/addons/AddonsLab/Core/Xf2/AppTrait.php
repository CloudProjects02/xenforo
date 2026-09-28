<?php
namespace AddonsLab\Core\Xf2;

use AddonsLab\Core\Container;
use AddonsLab\Core\Xf2\Service\ContentCreator;
use AddonsLab\Core\Xf2\Service\CrudEntity;
use AddonsLab\Core\Xf2\Service\OptionBuilder;
use AddonsLab\Core\Xf2\Service\TableMigrator;
use AddonsLab\Core\Xf2\Service\UserFinder;
use XF\Util\File;

trait AppTrait
{
    /**
     * @param Container $container
     */
    protected function _setupContainer($container)
    {
        $container->set('option.provider', function ($c) {
            return new Xf2OptionProvider();
        });
        $container->set('route.prefix.fetcher', function ($c) {
            return new RoutePrefixFetcher();
        });
        $container->set('phrase.provider', function ($c) {
            return new Xf2PhraseProvider();
        });
        $container->set('cookie.provider', function ($c) {
            return new Xf2CookieProvider();
        });
        $container->set('logger.dir', function ($c) {
            $logDirectory = \XF::getRootDirectory().'/internal_data/logs';
            if(!is_dir($logDirectory)) {
                File::createDirectory($logDirectory);
            }
            return $logDirectory;
        });

        $container->set('session.provider', function ($c) {
            return new Xf2SessionProvider();
        });
        $container->set('db.provider', function ($c) {
            return new DbProvider();
        });
        $container->set('RegistryProvider', function () {
            return new Xf2RegistryProvider();
        });
        $container->set('AccountDeleter', function () {
            return new AccountDeleter();
        });
        $container['option.builder'] = function ($c) {
            return new OptionBuilder();
        };
        $container->set('CrudEntity', function () {
            return new CrudEntity();
        });
        $container->set('ContentCreator', function ()
        {
            return new ContentCreator();
        });
        $container->set('UserFinder', function ()
        {
            return new UserFinder();
        });
        $container->set('TableMigrator', function ()
        {
            return new TableMigrator();
        });
    }

    /**
     * @return UserFinder
     * @testable
     */
    public static function getUserFinder()
    {
        return static::get('UserFinder');
    }

    /**
     * @return TableMigrator
     * @testable
     */
    public static function getTableMigrator()
    {
        return static::get('TableMigrator');
    }



    /**
     * @return ContentCreator
     * @testable
     */
    public static function getContentCreator()
    {
        return static::get('ContentCreator');
    }

    /**
     * @return CrudEntity
     * @testable
     */
    public static function getCrudEntityService()
    {
        return static::get('CrudEntity');
    }
}
