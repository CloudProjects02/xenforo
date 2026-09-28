<?php
namespace AddonsLab\Core\Xf2;

use AddonsLab\Core\DbProviderInterface;

class DbProvider implements DbProviderInterface
{
    public function insert($table, array $bind)
    {
        return \XF::db()->insert($table, $bind);
    }

    public function delete($table, $where = '')
    {
        return \XF::db()->delete($table, $where);
    }


    public function beginTransaction()
    {
        \XF::db()->beginTransaction();
    }

    public function commit()
    {
        \XF::db()->commit();
    }

    public function rollback()
    {
        \XF::db()->rollback();
    }

    public function rollbackAll()
    {
        \XF::db()->rollbackAll();
    }

    public function query($query, $bind = array())
    {
        return \XF::db()->query($query, $bind);
    }

    public function fetchOne($sql, $bind = array())
    {
        return \XF::db()->fetchOne($sql, $bind);
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

    public function fetchRow($sql, $bind = array(), $fetchMode = null)
    {
        return \XF::db()->fetchRow($sql, $bind);
    }

    public function fetchAll($sql, $bind = array(), $fetchMode = null)
    {
        return \XF::db()->fetchAll($sql, $bind);
    }

    public function quote($value, $type = null)
    {
        return \XF::db()->quote($value, $type);
    }
}
