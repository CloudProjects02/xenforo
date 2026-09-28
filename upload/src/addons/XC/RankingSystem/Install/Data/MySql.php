<?php

namespace XC\RankingSystem\Install\Data;

use XF\Db\Schema\Create;

class MySql {

    public function getTables() {

        $tables = [];

        $tables['xc_experience_point'] = function (Create $table) {
            
           $table->addColumn('xp_id', 'int')->autoIncrement();
           $table->addColumn('title', 'varchar', 255);
           $table->addColumn('description', 'mediumtext');
           $table->addColumn('display_order', 'int')->setDefault(0);
           $table->addColumn('points', 'int')->setDefault(0);
           $table->addColumn('point_type', 'varchar',255);
           $table->addColumn('point_depend', 'int')->setDefault(0);
           $table->addPrimaryKey('xp_id');
       
        };
        
       
        $tables['xc_badges'] = function (Create $table) {
            $table->addColumn('badge_id', 'int')->autoIncrement();
            $table->addColumn('title', 'varchar', 255);
            $table->addColumn('description', 'mediumtext');
            $table->addColumn('display_order', 'int')->setDefault(0);
            $table->addColumn('user_criteria', 'mediumblob');
             $table->addColumn('file_ex', 'varchar', 100)->nullable()->setDefault(null);
            $table->addPrimaryKey('badge_id');
        };
         $tables['xc_ranking_levels'] = function (Create $table) {
             
            $table->addColumn('level_id', 'int')->autoIncrement();
            $table->addColumn('level', 'int')->setDefault(0);
            $table->addColumn('exp_points', 'int')->setDefault(0);
            $table->addPrimaryKey('level_id');
            
        };
        
        $tables['xc_level_badges'] = function (Create $table) {
            $table->addColumn('level_badge_id', 'int')->autoIncrement();
            $table->addColumn('level_id', 'int');
            $table->addColumn('level', 'int')->setDefault(0);
            $table->addColumn('title', 'varchar', 255);
            $table->addColumn('description', 'mediumtext');
            $table->addColumn('file_ex', 'varchar', 100)->setDefault('svg');
            $table->addColumn('is_active', 'tinyint')->setDefault(1);
            $table->addColumn('created_date', 'int')->setDefault(0);
            $table->addPrimaryKey('level_badge_id');
            $table->addKey(['level_id'], 'level_id');
            $table->addKey(['level'], 'level');
        };
         $tables['xc_level_document'] = function (Create $table) {
             
            $table->addColumn('doc_id', 'int')->autoIncrement();
            $table->addColumn('doc_title', 'varchar', 255);
            $table->addColumn('doc_ext', 'varchar', 255);
            $table->addPrimaryKey('doc_id');
        };
        
        $tables['xc_user_xp'] = function(Create $table)
		{
                        $table->addColumn('award_xp_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('xp_id', 'int');
			$table->addColumn('award_date', 'int');
                        $table->addColumn('manual', 'tinyint')->setDefault(0);
                        $table->addColumn('content_type', 'varchar', 255)->nullable()->setDefault(null);
                        $table->addColumn('content_id', 'int')->setDefault(0);
                        $table->addColumn('spot_ex_point', 'int')->setDefault(0);
                        $table->addPrimaryKey('award_xp_id');
			
		};
                
        $tables['xc_award_user_badge'] = function(Create $table)
		{
                        $table->addColumn('award_badge_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('badge_id', 'int');
			$table->addColumn('award_date', 'int');
                        $table->addColumn('manual', 'tinyint')->setDefault(0);
                        $table->addPrimaryKey('award_badge_id');
			
		};
        return $tables;
    }

}
