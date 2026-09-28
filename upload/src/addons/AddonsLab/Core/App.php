<?php

namespace AddonsLab\Core;

use AddonsLab\Core\Service\AccountDeleterInterface;
use AddonsLab\Core\Service\ArrayHelper;
use AddonsLab\Core\Service\DirectorySynchronizer;
use AddonsLab\Core\Service\FlashMessageProvider;
use AddonsLab\Core\Service\Logger;
use AddonsLab\Core\Service\OptionBuilder;
use AddonsLab\Core\Service\PhraseMapper;
use AddonsLab\Core\Service\ThreadCopyProviderInterface;
use AddonsLab\Core\Service\UrlBuilder;
use AddonsLab\Core\Service\Utf8Converter;
use AddonsLab\Licensing\LicenseValidationService;
use Monolog\Handler\RotatingFileHandler;

/**
 * Class LinkChecker
 * @package AddonsLab\Core
 * Add-ons should extend this app and provide the services in the same manner as XF2.x app
 */
abstract class App
{
    /**
     * @var Container
     */
    protected $container;

    protected static $instances;

    protected $registered_mocks;

    /**
     * @return App
     */
    public static function instance()
    {
        $className = get_called_class();
        if (!isset(static::$instances[$className]))
        {
            static::$instances[$className] = new static();
        }

        return static::$instances[$className];
    }

    /**
     * @return Container
     */
    public static function container()
    {
        return static::instance()->getContainer();
    }

    /**
     * Add-on apps should extend this and setup the container to provide the services needed
     */
    public function setupContainer()
    {
        $this->container['phrase.provider'] = function ($c)
        {
            throw new \Exception("Phrase provider should be overridden by extending applications.");
        };
        $this->container['cookie.provider'] = function ($c)
        {
            throw new \Exception("Cookie provider should be overridden by extending applications.");
        };
        $this->container['session.provider'] = function ($c)
        {
            throw new \Exception("Session provider should be overridden by extending applications.");
        };
        $this->container['option.provider'] = function ($c)
        {
            throw new \Exception("Option provider should be overridden by extending applications.");
        };
        $this->container['route.prefix.fetcher'] = function ($c)
        {
            throw new \Exception("Route prefix fetcher should be overridden by extending applications.");
        };
        $this->container['db.provider'] = function ($c)
        {
            throw new \Exception("Database provider should be overridden by extending applications.");
        };
        $this->container->set('RegistryProvider', function ()
        {
            throw new \Exception('Registry provider should be overridden by extending applications.');
        });
        $this->container->set('AccountDeleter', function ()
        {
            throw new \Exception('Account deleter should be overridden by extending applications.');
        });
        $this->container['utf8.converter'] = function ($c)
        {
            return new Utf8Converter();
        };
        $this->container['directory.synchronizer'] = function ($c)
        {
            return new DirectorySynchronizer();
        };

        $this->container['option.builder'] = function ($c)
        {
            return new OptionBuilder();
        };
        $this->container->set('UrlBuilder', function ()
        {
            return new UrlBuilder();
        });
        $this->container['array.helper'] = function ($c)
        {
            return new ArrayHelper();
        };
        $this->container['LicenseValidationService'] = function ($c)
        {
            return function ($validator_class)
            {
                return new LicenseValidationService($validator_class);
            };
        };
        $this->container['FlashMessageProvider'] = function ()
        {
            return new FlashMessageProvider();
        };
        $this->container['DbMiddleware'] = function ()
        {
            return function ($db)
            {
                return new DbMiddleware($db);
            };
        };
        $this->container->set('PhraseMapper', function ()
        {
            return function (PhraseProviderInterface $phraseProvider)
            {
                return new PhraseMapper($phraseProvider);
            };
        });
        $this->container->set('ZendDbProvider', function ()
        {
            return function (\Zend_Db_Adapter_Abstract $db)
            {
                return new ZendDbProvider($db);
            };
        });

        $this->container->set('logger.dir', function(){
            throw new \Exception("Logger directory should be specified by each software.");
        });

        $this->container->set('logger', function ($c)
        {
            return function ($channel) use($c)
            {
                static $loggerCache = [];
                if (!$channel)
                {
                    $channel = 'general';
                }

                $directory = $c['logger.dir'];

                if (!isset($loggerCache[$channel]))
                {
                    $logger = new Logger($channel);
                    $logger->pushHandler(new RotatingFileHandler($directory . '/000-debug.log', 10, Logger::DEBUG, false, 0777));
                    $logger->pushHandler(new RotatingFileHandler($directory . '/001-info.log', 10, Logger::INFO, false, 0777));
                    $logger->pushHandler(new RotatingFileHandler($directory . '/002-notice.log', 10, Logger::NOTICE, false, 0777));
                    $logger->pushHandler(new RotatingFileHandler($directory . '/003-warning.log', 10, Logger::WARNING, false, 0777));
                    $logger->pushHandler(new RotatingFileHandler($directory . '/004-error.log', 10, Logger::ERROR, false, 0777));
                    $logger->pushHandler(new RotatingFileHandler($directory . '/005-critical.log', 10, Logger::CRITICAL, false, 0777));
                    $logger->pushHandler(new RotatingFileHandler($directory . '/006-alert.log', 10, Logger::ALERT, false, 0777));
                    $logger->pushHandler(new RotatingFileHandler($directory . '/007-emergency.log', 10, Logger::EMERGENCY, false, 0777));
                    $loggerCache[$channel] = $logger;
                }

                return $loggerCache[$channel];
            };
        });

        $this->_setupContainer($this->container);
    }

    protected static function _id($id)
    {
        return $id;
    }

    /**
     * @return ZendDbProvider
     * @testable
     */
    public static function getZendDbProvider(\Zend_Db_Adapter_Abstract $db)
    {
        return static::get('ZendDbProvider', array($db));
    }

    /**
     * @param $container Container
     * Specifically designed to be overridden by different engines
     */
    protected abstract function _setupContainer($container);

    public static function setMock($serviceName, $service, $counter = 0)
    {
        static::instance()->registered_mocks[$serviceName] = array(
            'callback' => $service,
            'counter' => $counter === 0 ? false : $counter
        );
    }

    public static function removeMock($serviceName)
    {
        unset(static::instance()->registered_mocks[$serviceName]);
    }

    public function getService($key, array $params = array())
    {
        return static::get($key, $params);
    }

    /**
     * @param $serviceName
     * @param array $params
     * @return mixed
     * @throws \Exception
     */
    public static function get($serviceName, $params = array())
    {
        $service = null;

        if (isset(static::instance()->registered_mocks[$serviceName]))
        {
            $mock =& static::instance()->registered_mocks[$serviceName];
            if ($mock['counter'] === false || $mock['counter'] > 0)
            {
                $service = $mock['callback'];
            }
            if ($mock['counter'] > 0)
            {
                $mock['counter']--;
            }
        }

        if ($service === null)
        {
            $container = static::instance()->getContainer();
            $service = $container->offsetGet($serviceName);
        }

        if (is_object($service) && method_exists($service, '__invoke'))
        {
            $service = call_user_func_array($service, $params);
        }
        else
        {
            if (!empty($params))
            {
                throw new \Exception("Parameters can be provided only for invokable services");
            }
        }

        if (method_exists($service, 'setApp'))
        {
            $service->setApp(static::instance());
        }

        return $service;
    }

    public function resetContainer()
    {
        $this->container = null;
        $this->registered_mocks = array();
    }

    /**
     * @return Container
     */
    public function getContainer()
    {
        if (null === $this->container)
        {
            $this->container = $this->createAppContainer();

            $this->setupContainer();
        }

        return $this->container;
    }

    /**
     * @return Container
     * Creates a container based on engine
     */
    public function createAppContainer()
    {
        if (class_exists('\XF\Container', false) && class_exists('\XF\App'))
        {
            // we are int XenForo 2.x, use the default container
            $className = '\XF\Container';
            return new $className();
        }

        // use AddonsLab version for Xf1.x apps
        return new Container();
    }

    /**
     * @return PhraseMapper
     * @testable
     */
    public static function getPhraseMapper()
    {
        return static::get('PhraseMapper', array(self::getPhraseProvider()));
    }

    /**
     * @return RegistryProviderInterface
     * @testable
     */
    public static function getRegistryProvider()
    {
        return static::get('RegistryProvider');
    }

    /**
     * @return AccountDeleterInterface
     * @testable
     */
    public static function getAccountDeleter()
    {
        return static::get('AccountDeleter');
    }

    /**
     * @return ThreadCopyProviderInterface
     * @testable
     */
    public static function getThreadCopyProvider()
    {
        return static::get('ThreadCopyProvider');
    }

    /**
     * @return UrlBuilder
     * @testable
     */
    public static function getUrlBuilder()
    {
        return static::get('UrlBuilder');
    }

    /**
     * @param $db
     * @return DbMiddleware
     */
    public static function getDbMiddleware($db)
    {
        return static::get('DbMiddleware', array($db));
    }

    /**
     * @return FlashMessageProvider
     * @testable
     */
    public static function getFlashMessageProvider()
    {
        return static::get('FlashMessageProvider');
    }

    /**
     * @return LicenseValidationService
     * @testable
     */
    public static function getLicenseValidationService($validator_class)
    {
        return static::get('LicenseValidationService', array($validator_class));
    }

    /**
     * @return ArrayHelper
     */
    public static function getArrayHelper()
    {
        return static::get('array.helper');
    }

    /**
     * @return OptionBuilder
     */
    public static function getOptionBuilder()
    {
        return static::get('option.builder');
    }

    /**
     * @return Utf8Converter
     */
    public static function getUtf8Converter()
    {
        return static::get('utf8.converter');
    }

    /**
     * @return DbProviderInterface
     */
    public static function getDbProvider()
    {
        return static::get('db.provider');
    }

    /**
     * @return OptionProviderInterface
     */
    public static function getOptionProvider()
    {
        return static::get('option.provider');
    }

    /**
     * @return RoutePrefixFetcherInterface
     */
    public static function getRoutePrefixFetcher()
    {
        return static::get('route.prefix.fetcher');
    }


    /**
     * @return PhraseProviderInterface
     */
    public static function getPhraseProvider()
    {
        return static::get('phrase.provider');
    }

    /**
     * @return CookieProviderInterface
     */
    public static function getCookieProvider()
    {
        return static::get('cookie.provider');
    }

    /**
     * @return SessionProviderInterface
     * @testable
     */
    public static function getSessionProvider()
    {
        return static::get('session.provider');
    }

    /**
     * @param string $channel
     * @return Logger
     * @throws \Exception
     */
    public static function getLogger($channel = '')
    {
        return static::get('logger', [$channel]);
    }

    /**
     * @return DirectorySynchronizer
     * @throws \Exception
     */
    public static function getDirectorySynchronizer()
    {
        return static::get('directory.synchronizer');
    }
}