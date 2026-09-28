<?php


namespace AddonsLab\Core\Xf2;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Installer
{
    public function deleteTables(array $tables)
    {
        $schemaManager = \XF::db()->getSchemaManager();

        foreach ($tables as $tableName => $tableConfig)
        {
            if (isset($tableConfig['uninstall']) && $schemaManager->tableExists($tableName))
            {
                call_user_func($tableConfig['uninstall'], $tableName);
            }
        }
    }

    public function deleteColumns(array $mapping)
    {
        $schemaManager = \XF::db()->getSchemaManager();

        foreach ($mapping as $tableName => $tableColumns)
        {
            $tableStatus = $schemaManager->getTableStatus($tableName);
            if (!$tableStatus)
            {
                // the table does not exist anymore
                continue;
            }
            $schemaManager->alterTable($tableName, function (Alter $table) use ($tableColumns)
            {
                foreach ($tableColumns as $columnDefinition)
                {
                    if (!empty($columnDefinition['uninstall']))
                    {
                        call_user_func($columnDefinition['uninstall'], $columnDefinition['column'], $table);
                    }
                }
            });
        }
    }

    public function assertCreateTable(array $mapping)
    {
        $schemaManager = \XF::db()->getSchemaManager();

        foreach ($mapping as $tableDefinition)
        {
            $tableName = $tableDefinition['table'];
            if ($schemaManager->tableExists($tableName) === false)
            {
                $schemaManager->createTable($tableName, function (Create $table) use ($tableDefinition)
                {
                    call_user_func($tableDefinition['install'], $table);
                });
            }
        }
    }

    public function assertAlterTable(array $mapping)
    {
        $schemaManager = \XF::db()->getSchemaManager();

        foreach ($mapping as $tableName => $tableColumns)
        {
            foreach ($tableColumns as $columnDefinition)
            {
                $schemaManager->alterTable($tableName, function (Alter $table) use ($schemaManager, $tableName, $columnDefinition)
                {
                    $columnName = $columnDefinition['column'];
                    if ($schemaManager->columnExists($tableName, $columnName))
                    {
                        $column = $table->changeColumn($columnName);
                    }
                    else
                    {
                        $column = $table->addColumn($columnName);
                    }
                    call_user_func($columnDefinition['install'], $column, $table);
                });
            }
        }
    }
}