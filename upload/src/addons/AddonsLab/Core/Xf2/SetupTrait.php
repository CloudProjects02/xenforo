<?php

namespace AddonsLab\Core\Xf2;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Column;
use XF\Db\Schema\Create;

trait SetupTrait
{
    /**
     * @return array
     * @see _getSampleCreateMapping()
     */
    public abstract function getCreateMapping();

    /**
     * @return array
     * @see _getSampleAlterMapping
     */
    public abstract function getAlterMapping();

    public function assertCreateTable(array $mapping)
    {
        $installer = new Installer();

        $installer->assertCreateTable($mapping);
    }

    public function assertAlterTable(array $mapping)
    {
        $installer = new Installer();

        $installer->assertAlterTable($mapping);
    }

    public function deleteTables(array $mapping)
    {
        $installer = new Installer();
        $installer->deleteTables($mapping);
    }

    public function deleteColumns(array $mapping)
    {
        $installer = new Installer();
        $installer->deleteColumns($mapping);
    }

    /**
     * @return array[]
     */
    public function getTestCreateMapping()
    {
        $createMapping = $this->getCreateMapping();

        $newMapping = [];

        foreach ($createMapping as $tableName => $tableDefinition)
        {
            $testTableName = uniqid('test_', false) . '_' . $tableName;

            // Make sure the length of the table name is not more than 30 characters
            $testTableName = substr($testTableName, 0, 30);

            $tableDefinition['table'] = $testTableName;
            $newMapping[$testTableName] = $tableDefinition;
        }

        return $newMapping;
    }

    /**
     * @return array[]
     */
    public function getTestAlterMapping()
    {
        $alterMapping = $this->getAlterMapping();
        $newMapping = [];
        foreach ($alterMapping as $tableName => $tableColumns)
        {
            foreach ($tableColumns as $columnName => $columnDefinition)
            {
                $newColumnName = $columnName . '_' . uniqid('test_', false);
                $columnDefinition['column'] = $newColumnName;
                $newMapping[$tableName][$newColumnName] = $columnDefinition;
            }
        }
        return $newMapping;
    }

    /**
     * @return \array[][]
     * @see getAlterMapping
     */
    protected function _getSampleAlterMapping($tableName, $columnName)
    {
        return [
            $tableName => [
                [
                    'column' => $columnName,
                    'install' => function (Column $column)
                    {
                        $column->type('varbinary', 25)->setDefault('');
                    },
                    'uninstall' => function ($columnName, Alter $table)
                    {
                        $table->dropColumns([$columnName]);
                    }
                ]
            ]
        ];
    }

    /**
     * @param $tableName
     * @return array[]
     * @see getCreateMapping
     */
    protected function _getSampleCreateMapping($tableName)
    {
        return [
            $tableName => [
                'table' => $tableName,
                'install' => function (Create $table)
                {
                    $table->addColumn('primary_key', 'int')->autoIncrement();
                    $table->addColumn('field', 'type');
                    $table->addPrimaryKey('primary_key');
                    $table->addUniqueKey(['field'], 'field');
                    $table->addKey(['field', 'field']);

                    throw new \RuntimeException('Please use other installation code');
                },
                'uninstall' => function ($tableName)
                {
                    $this->schemaManager()->dropTable($tableName);
                }
            ]
        ];
    }
}
