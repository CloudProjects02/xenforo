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


namespace AL\FilterFramework;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Column;
use XF\Db\Schema\Create;

trait FrameworkSetup
{
    protected function _getCreateMapping()
    {
        return [
            'xf_alff_field_index' => [
                'table' => 'xf_alff_field_index',
                'install' => function (Create $table) {
                    $table->addColumn('field_index_id', 'int')->autoIncrement();
                    $table->addColumn('content_id', 'int');
                    $table->addColumn('content_type', 'varbinary', 25)->comment('The type of main content');
                    $table->addColumn('field_id', 'varbinary', 25);
                    $table->addColumn('field_value', 'mediumtext');
                    $table->addColumn('field_int', 'int', 11)->setDefault(0)->unsigned(false);
                    $table->addColumn('field_float', 'double')->setDefault(0)->unsigned(false);
                    $table->addColumn('field_color_l', 'double')->setDefault(0)->unsigned(false);
                    $table->addColumn('field_color_a', 'double')->setDefault(0)->unsigned(false);
                    $table->addColumn('field_color_b', 'double')->setDefault(0)->unsigned(false);
                    $table->addColumn('modified_date', 'int')->setDefault(0);
                    $table->addPrimaryKey('field_index_id');
                    $table->addUniqueKey(['content_id', 'content_type', 'field_id'], 'content_field');
                    $table->addKey(['content_id', 'content_type']);
                },
                'uninstall' => function ($tableName) {
                    $this->schemaManager()->dropTable($tableName);
                },
            ],
            'xf_alff_search_cache' => [
                'table' => 'xf_alff_search_cache',
                'install' => function (Create $table) {
                    $table->addColumn('content_type', 'varbinary', 25);
                    $table->addColumn('search_hash', 'varbinary', 32);
                    $table->addColumn('result_list', 'mediumtext');
                    $table->addColumn('field_list', 'varbinary', 255);
                    $table->addColumn('creation_date', 'int')->setDefault(0);
                    $table->addPrimaryKey(['search_hash', 'content_type']);
                    $table->addKey(['content_type', 'field_list']);
                },
                'uninstall' => function ($tableName) {
                    $this->schemaManager()->dropTable($tableName);
                },
            ],
            'xf_alff_field_data' => [
                'table' => 'xf_alff_field_data',
                'install' => function (Create $table) {
                    $table->addColumn('content_type', 'varbinary', 25);
                    $table->addColumn('field_id', 'varbinary', 25);
                    $table->addColumn('filter_template', 'enum')->values(['select', 'radio', 'checkbox', 'multiselect'])->setDefault('checkbox');
                    $table->addColumn('default_match_type', 'enum')->values(['OR', 'AND'])->setDefault('OR');
                    $table->addColumn('allow_filter', 'tinyint', 1)->setDefault(0);
                    $table->addColumn('allow_search', 'tinyint', 1)->setDefault(0);
                    $table->addColumn('allow_sorting', 'tinyint', 1)->setDefault(0);
                    $table->addColumn('display_options', 'int', 11)->setDefault(0);
                    $table->addPrimaryKey(['content_type', 'field_id']);
                },
                'uninstall' => function ($tableName) {
                    $this->schemaManager()->dropTable($tableName);
                },
            ],
            'xf_alff_address_cache' => [
                'table' => 'xf_alff_address_cache',
                'install' => function (Create $table) {
                    $table->addColumn('address_hash', 'varbinary', 32);
                    $table->addColumn('lat', 'decimal', '8,6')->unsigned(false);
                    $table->addColumn('lng', 'decimal', '9,6')->unsigned(false);
                    $table->addColumn('date', 'int', 11)->unsigned()->setDefault(0);
                    $table->addPrimaryKey('address_hash');
                },
                'uninstall' => function ($tableName) {
                    $this->schemaManager()->dropTable($tableName);
                },
            ],
        ];
    }

    protected function _getAlterMapping()
    {
        $alter = [];

        $alter['xf_alff_field_data'][] = [
            'column' => 'allow_sorting',
            'install' => function (Column $column) {
                $column->type('tinyint', 1)->setDefault(0);
            },
            'uninstall' => function ($columnName, Alter $table) {
                $table->dropColumns([$columnName]);
            },
        ];

        $alter['xf_alff_field_data'][] = [
            'column' => 'default_match_type',
            'install' => function (Column $column) {
                $column->type('enum')->values(['OR', 'AND'])->setDefault('OR');
            },
            'uninstall' => function ($columnName, Alter $table) {
                $table->dropColumns([$columnName]);
            },
        ];

        // Location-related info
        $alter['xf_alff_field_index'][] = [
            'column' => 'field_location_country_code',
            'install' => function (Column $column) {
                $column->type('char', 2)->setDefault('');
            },
            'uninstall' => function ($columnName, Alter $table) {
                $table->dropColumns([$columnName]);
            },
        ];
        $alter['xf_alff_field_index'][] = [
            'column' => 'field_location_state_id',
            'install' => function (Column $column) {
                $column->type('int', 11)->setDefault(0);
            },
            'uninstall' => function ($columnName, Alter $table) {
                $table->dropColumns([$columnName]);
            },
        ];
        $alter['xf_alff_field_index'][] = [
            'column' => 'field_location_city_id',
            'install' => function (Column $column) {
                $column->type('char', 11)->setDefault(0);
            },
            'uninstall' => function ($columnName, Alter $table) {
                $table->dropColumns([$columnName]);
            },
        ];
        $alter['xf_alff_field_index'][] = [
            'column' => 'field_location_full_address',
            'install' => function (Column $column) {
                $column->type('varchar', 255)->setDefault('');
            },
            'uninstall' => function ($columnName, Alter $table) {
                $table->dropColumns([$columnName]);
            },
        ];
        $alter['xf_alff_field_index'][] = [
            'column' => 'field_location_zip_code',
            'install' => function (Column $column) {
                $column->type('varchar', 255)->setDefault('');
            },
            'uninstall' => function ($columnName, Alter $table) {
                $table->dropColumns([$columnName]);
            },
        ];
        $alter['xf_alff_field_index'][] = [
            'column' => 'field_location_street_address',
            'install' => function (Column $column) {
                $column->type('varchar', 255)->setDefault('');
            },
            'uninstall' => function ($columnName, Alter $table) {
                $table->dropColumns([$columnName]);
            },
        ];
        $alter['xf_alff_field_index'][] = [
            'column' => 'field_location_lat',
            'install' => function (Column $column) {
                $column->type('decimal', '8,6')->unsigned(false)->nullable()->setDefault(null);
            },
            'uninstall' => function ($columnName, Alter $table) {
                $table->dropColumns([$columnName]);
            },
        ];
        $alter['xf_alff_field_index'][] = [
            'column' => 'field_location_lng',
            'install' => function (Column $column) {
                $column->type('decimal', '9,6')->unsigned(false)->nullable()->setDefault(null);
            },
            'uninstall' => function ($columnName, Alter $table) {
                $table->dropColumns([$columnName]);
            },
        ];

        return $alter;
    }
}
