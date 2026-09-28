<?php

namespace AddonsLab\Core\vB3;

use AddonsLab\Core\DbProviderInterface;

class DbProvider implements DbProviderInterface
{
    /**
     * @var \vB_Database
     */
    protected $_db;

    protected $transaction_started = false;

    /**
     * DbProvider constructor.
     */
    public function __construct()
    {
        global $vbulletin;

        $this->_db = $vbulletin->db;
    }

    public function beginTransaction()
    {
        if ($this->transaction_started) {
            return;
        }

        $this->_db->query_write("SET AUTOCOMMIT = 0");
        $this->_db->query_write("START TRANSACTION");

        $this->transaction_started = true;
    }

    public function commit()
    {
        if ($this->transaction_started == false) {
            return;
        }
        $this->_db->query_write("COMMIT");
        $this->_db->query_write("SET AUTOCOMMIT = 1");

        $this->transaction_started = false;
    }

    public function rollback()
    {
        if ($this->transaction_started == false) {
            return;
        }
        $this->_db->query_write("ROLLBACK");
        $this->_db->query_write("SET AUTOCOMMIT = 1");

        $this->transaction_started = false;
    }

    public function rollbackAll()
    {
        $this->rollback();
    }

    public function query($query, $bind = array())
    {
        return $this->_db->query_write($this->_bindParams($query, $bind));
    }

    public function fetchOne($sql, $bind = array())
    {
        $result = $this->query($sql, $bind);

        if ($result === false || ($row = $this->_db->fetch_array($result)) === null) {
            return null;
        }

        return reset($row);
    }

    public function fetchRow($sql, $bind = array(), $fetchMode = null)
    {
        $result = $this->query($sql, $bind);

        if ($result === false || ($row = $this->_db->fetch_array($result)) === null) {
            return null;
        }

        return $row;
    }


    public function fetchAllKeyed($sql, $key, $bind = array(), $fetchMode = null)
    {
        $results = $this->fetchAll($sql, $bind, $fetchMode);

        $keyed = array();

        foreach ($results AS $result) {
            $keyed[$result[$key]] = $result;
        }

        return $keyed;
    }


    public function fetchAll($sql, $bind = array(), $fetchMode = null)
    {
        $result = $this->query($sql, $bind);

        if ($result === false) {
            return array();
        }

        $resultArray = array();

        while ($row = $this->_db->fetch_array($result)) {
            $resultArray[] = $row;
        }

        return $resultArray;
    }

    public function quote($value, $type = null)
    {
        return $this->_db->sql_prepare($value);
    }

    protected function _bindParams($sql, $bind = array())
    {
        return $sql;
    }
}