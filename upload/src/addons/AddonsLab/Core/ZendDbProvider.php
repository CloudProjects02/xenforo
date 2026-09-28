<?php

namespace AddonsLab\Core;

class ZendDbProvider implements DbProviderInterface
{
    protected $db;

    public function __construct(\Zend_Db_Adapter_Abstract $db)
    {
        $this->db = $db;
    }

    public function beginTransaction()
    {
        $this->db->beginTransaction();
    }

    public function commit()
    {
        $this->db->commit();
    }

    public function rollback()
    {
        $this->db->rollback();
    }

    public function rollbackAll()
    {
        $this->db->rollBack();
    }

    public function query($query, $bind = array())
    {
        return $this->db->query($query, $bind);
    }

    public function fetchOne($sql, $bind = array())
    {
        return $this->db->fetchOne($sql, $bind);
    }

    public function fetchRow($sql, $bind = array(), $fetchMode = null)
    {
        return $this->db->fetchRow($sql, $bind, $fetchMode);
    }

    public function fetchAll($sql, $bind = array(), $fetchMode = null)
    {
        return $this->db->fetchAll($sql, $bind, $fetchMode);
    }

    public function quote($value, $type = null)
    {
        return $this->db->quote($value, $type);
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

    public function insert($table, array $bind)
    {
        return $this->db->insert($table, $bind);
    }

    public function delete($table, $where = '')
    {
        return $this->db->delete($table, $where);
    }


}
