<?php
/** 
* @package [AL] Filter Framework
* @author AddonsLab
* @license https://addonslab.com/
* @link https://addonslab.com/
* @version 1.4.3
This software is furnished under a license and may be used and copied
only  in  accordance  with  the  terms  of such  license and with the
inclusion of the above copyright notice.  This software  or any other
copies thereof may not be provided or otherwise made available to any
other person.  No title to and  ownership of the  software is  hereby
transferred.                                                         
                                                                     
You may not reverse  engineer, decompile, defeat  license  encryption
mechanisms, or  disassemble this software product or software product
license.  AddonsLab may terminate this license if you don't comply with
any of these terms and conditions.  In such event,  licensee  agrees 
to return licensor  or destroy  all copies of software  upon termination 
of the license.
*/


namespace AL\FilterFramework\Repository;

use AddonsLab\Core\XF2;
use AL\FilterFramework\ContentTypeProviderInterface;
use XF\Mvc\Entity\Repository;

class Logger extends Repository
{
    /**
     * @var ContentTypeProviderInterface
     */
    protected $contentTypeProvider;

    protected static $messages = [];

    protected $force_log_to_file = false;
    protected $force_log_to_errors = false;

    public function setContentTypeProvider(ContentTypeProviderInterface $contentTypeProvider)
    {
        $this->contentTypeProvider = $contentTypeProvider;
    }

    public function info(
        $message,
        $context = []
    ) {
        $this->logMessage(\Monolog\Logger::INFO, $message, $context);
    }

    public function warning(
        $message,
        $context = []
    ) {
        $this->logMessage(\Monolog\Logger::WARNING, $message, $context);
    }

    public function error(
        $message,
        $context = []
    ) {
        $this->logMessage(\Monolog\Logger::ERROR, $message, $context);
    }

    public function debug(
        $message,
        $context = []
    ) {
        $this->logMessage(\Monolog\Logger::DEBUG, $message, $context);
    }

    public function logMessage($level, $message, array $context = [])
    {
        $logToFile = isset($_GET['filterLogToFile']) || defined('TEST_ENV') || $this->force_log_to_file;
        $logToErrorLog = isset($_GET['filterLogToErrorLog']) || defined('TEST_ENV') || $this->force_log_to_errors;

        if ($logToFile)
        {
            $logger = XF2::getLogger($this->contentTypeProvider->getContentType() ?? 'unknown-filter');
            $logger->addRecord($level, $message, $context);
        }

        if ($logToErrorLog)
        {
            $content_type = $this->contentTypeProvider->getContentType();

            self::$messages[$content_type][] = [
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ];

            \XF::runOnce('filter_logger_' . $content_type, function () use ($content_type) {
                \XF::logError($content_type . ' filter log: ' . json_encode(self::$messages[$content_type]));
            });
        }
    }

    public function setForceLogToErrors(bool $force_log_to_errors): void
    {
        $this->force_log_to_errors = $force_log_to_errors;
    }

    public function setForceLogToFile(bool $force_log_to_file): void
    {
        $this->force_log_to_file = $force_log_to_file;
    }
}
