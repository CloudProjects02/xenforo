<?php
namespace AddonsLab\Core\Xf1;

use AddonsLab\Core\DbProviderInterface;

class DbProvider implements DbProviderInterface
{
    public function beginTransaction()
    {
        \XenForo_Db::beginTransaction();
    }

    public function commit()
    {
        \XenForo_Db::commit();
    }

    public function rollback()
    {
        \XenForo_Db::rollback();
    }

    public function rollbackAll()
    {
        \XenForo_Db::rollbackAll();
    }

    public function query($query, $bind = array())
    {
        return \XenForo_Application::getDb()->query($query, $bind);
    }

    public function fetchOne($sql, $bind = array())
    {
        return \XenForo_Application::getDb()->fetchOne($sql, $bind);
    }

    public function fetchAll($sql, $bind = array(), $fetchMode = null)
    {
        return \XenForo_Application::getDb()->fetchAll($sql, $bind, $fetchMode);
    }

    public function fetchRow($sql, $bind = array(), $fetchMode = null)
    {
        return \XenForo_Application::getDb()->fetchRow($sql, $bind, $fetchMode = null);
    }

    public function quote($value, $type = null)
    {
        return \XenForo_Application::getDb()->quote($value, $type);
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
        return \XenForo_Application::getDb()->insert($table, $bind);
    }

    public function delete($table, $where = '')
    {
        return \XenForo_Application::getDb()->delete($table, $where);
    }


}
