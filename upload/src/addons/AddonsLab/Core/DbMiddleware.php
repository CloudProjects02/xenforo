<?php

namespace AddonsLab\Core;

/**
 * Class DbMiddleware
 * @package AddonsLab\Core
 * A middleware for the DB for custom handling of lock wait timeout errors
 */
class DbMiddleware
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }
    
    public function getDb()
    {
        return $this->db;
    }

    public function __call($name, $arguments)
    {
        $tryCount = 0;
        while (true) {
            $tryCount++;
            try {
                return call_user_func_array(array($this->db, $name), $arguments);
            } catch (\Zend_Db_Statement_Mysqli_Exception $exception) {
                if ($tryCount > 2) { // tried 2 times already
                    throw $exception;
                }

                if (strpos($exception->getMessage(), 'Lock wait timeout exceeded') !== false) {
                    // transaction error, trying again after some time
                    usleep(50000);
                    continue;
                }
                
                throw $exception;
            }
        }
    }
}