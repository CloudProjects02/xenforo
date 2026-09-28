<?php

namespace AddonsLab\Core\Service;

class Logger extends \Monolog\Logger
{
    /**
     * @param int $level
     * @param string $message
     * @param array $context
     * @return bool
     * @throws \Exception
     */
    /*
     * public function addRecord($level, $message, array $context = array())
    {
        return parent::addRecord($level, $message, $context);
        try {

        } catch (\Exception $exception) {
            // backup logging
            if (is_writable(__DIR__ . '/tmp')) {
                $filename = __DIR__ . '/tmp/logger.log';
                if (!file_exists($filename)) {
                    @file_put_contents($filename, '');
                    if(file_exists($filename)) {
                        @chmod($filename, 0777);
                    }
                }

                if(file_exists($filename)) {
                    @file_put_contents($filename, $message.PHP_EOL.$exception->getMessage()."\n", FILE_APPEND);
                }
            }
        }
    }
    */
}