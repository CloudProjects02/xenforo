<?php

namespace AddonsLab\Core\Xf2\Service;
class TableMigrator
{
    public function migrate($fromTable, $toTable, $orderClause = '')
    {
        $schemaManager = \XF::db()->getSchemaManager();

        $sourceColumns = array_keys($schemaManager->getTableColumnDefinitions($fromTable));
        $targetColumns = array_keys($schemaManager->getTableColumnDefinitions($toTable));

        $commonColumns = array_intersect($sourceColumns, $targetColumns);

        $columnNames = array_map(function ($name)
        {
            return "`$name`";
        }, $commonColumns);

        $columnNames = implode(', ', $columnNames);

        \XF::db()->query("
            INSERT IGNORE INTO $toTable ($columnNames)
            SELECT $columnNames FROM $fromTable
            $orderClause
        ");
    }
}
