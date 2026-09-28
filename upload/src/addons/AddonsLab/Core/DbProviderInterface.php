<?php

namespace AddonsLab\Core;

interface DbProviderInterface
{
    public function beginTransaction();

    public function commit();

    public function rollback();

    public function rollbackAll();

    public function query($query, $bind = array());

    public function fetchOne($sql, $bind = array());

    public function fetchRow($sql, $bind = array(), $fetchMode = null);
    public function fetchAll($sql, $bind = array(), $fetchMode = null);
    public function fetchAllKeyed($sql, $key, $bind = array(), $fetchMode = null);
    public function quote($value, $type = null);

    public function insert($table, array $bind);

    public function delete($table, $where = '');
}
