<?php

namespace XC\RankingSystem;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XC\RankingSystem\Install\Data\MySql;

class Setup extends AbstractSetup {

    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1() {


        $sm = $this->schemaManager();

        foreach ($this->getTables() AS $tableName => $closure) {
            $sm->createTable($tableName, $closure);
        }
        
         $sm->alterTable('xf_user', function (Alter $table) {
          
            $table->addColumn('total_points', 'int')->setDefault(0);
            $table->addColumn('level', 'int')->setDefault(0);
            $table->addColumn('first_visit', 'int')->setDefault(0);
            $table->addColumn('latest_visit', 'int')->setDefault(0);
        
        });
        
          $sm->alterTable('xf_thread', function (Alter $table) {
          
            $table->addColumn('rank_reply_count', 'int')->setDefault(0);
        
        });

        // Create level badges for existing levels
        $this->createLevelBadgesForExistingLevels();
      		
    }

    public function uninstallStep1() {
        $sm = $this->schemaManager();

        foreach (array_keys($this->getTables()) AS $tableName) {
            $sm->dropTable($tableName);
        }
        
         $sm->alterTable('xf_user', function (Alter $table) {
            
            $table->dropColumns(['total_points']);
            $table->dropColumns(['level']);
            $table->dropColumns(['first_visit']);
            $table->dropColumns(['latest_visit']);
         });
        
        $sm->alterTable('xf_thread', function (Alter $table) {
            
            $table->dropColumns(['rank_reply_count']);
        });
      
       
    }

    protected function getTables() {
        $data = new MySql();

        return $data->getTables();
    }

    protected function createLevelBadgesForExistingLevels()
    {
        $db = $this->db();
        
        // Get all existing levels
        $levels = $db->fetchAll("SELECT * FROM xc_ranking_levels ORDER BY level ASC");
        
        foreach ($levels as $level) {
            // Check if level badge already exists
            $existingBadge = $db->fetchOne("SELECT level_badge_id FROM xc_level_badges WHERE level_id = ?", $level['level_id']);
            
            if (!$existingBadge) {
                // Generate level title
                $levelTitle = $this->generateLevelTitle($level['level']);
                $levelDescription = "Achieved Level {$level['level']} with {$level['exp_points']} experience points";
                
                // Insert level badge
                $db->insert('xc_level_badges', [
                    'level_id' => $level['level_id'],
                    'level' => $level['level'],
                    'title' => $levelTitle,
                    'description' => $levelDescription,
                    'file_ex' => 'svg',
                    'is_active' => 1,
                    'created_date' => \XF::$time
                ]);
            }
        }
    }

    protected function generateLevelTitle($level)
    {
        $levelNames = [
            1 => 'Rookie',
            2 => 'Novice',
            3 => 'Apprentice',
            4 => 'Journeyman',
            5 => 'Expert',
            6 => 'Master',
            7 => 'Grandmaster',
            8 => 'Legend',
            9 => 'Mythic',
            10 => 'Divine'
        ];

        if (isset($levelNames[$level])) {
            return $levelNames[$level];
        }

        // For levels beyond 10, use a pattern
        if ($level <= 20) {
            return "Elite " . ($level - 10);
        } elseif ($level <= 50) {
            return "Champion " . ($level - 20);
        } elseif ($level <= 100) {
            return "Hero " . ($level - 50);
        } else {
            return "Level " . $level;
        }
    }

}
