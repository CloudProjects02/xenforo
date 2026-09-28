<?php

namespace AddonsLab\ContentHandler;

/**
 * Class AbstractFactory
 * @package AddonsLab\ContentHandler
 */
abstract class AbstractFactory
{
    /**
     * @var AbstractFactory
     */
    protected static $instances = array();

    /**
     * @return mixed
     * @throws \Exception
     */
    public static function getInstance()
    {
        $class = get_called_class();

        if (!isset(static::$instances[$class])) {
            $className = \XF::extendClass($class);
            static::$instances[$class] = new $className();
        }

        return static::$instances[$class];
    }

    /**
     * @param $handlerId
     * @return mixed
     * @throws \Exception
     */
    public static function getContentTypeHandler($handlerId)
    {
        $handlerList = static::getInstance()->getHandlers();

        if (isset($handlerList[$handlerId])) {
            return $handlerList[$handlerId];
        }

        throw new \RuntimeException("Unknown handler $handlerId");
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getHandlers()
    {
        $handlerClasses = $this->_getHandlerClasses();

        $handlerObjects = array();

        foreach ($handlerClasses AS $handlerClass) {
            /** @var AbstractContentHandler $handler */
            $handlerClassExtended = \XF::extendClass($handlerClass);
            $handler = new $handlerClassExtended;

            if ($handler instanceof AbstractContentHandler === false) {
                throw new \RuntimeException("$handlerClass should extend \\AddonsLab\\ContentHandler\\AbstractContentHandler");
            }

            $handlerObjects[$handler->getContentType()] = $handler;
        }

        return $handlerObjects;
    }

    protected abstract function _getHandlerClasses();
}