<?php

namespace SV\SignupAbuseBlocking\Finder;

use SV\SignupAbuseBlocking\Entity\ReportDataUser as ReportDataUserEntity;
use SV\StandardLib\Finder\SqlJoinTrait;
use SV\StandardLib\Helper;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ReportDataUserEntity>|ReportDataUserEntity[] fetch(?int $limit = null, ?int $offset = null)
 * @method ReportDataUserEntity|null fetchOne(?int $offset = null)
 * @implements \IteratorAggregate<string|int,ReportDataUserEntity>
 * @extends Finder<ReportDataUserEntity>
 */
class ReportDataUser extends Finder
{
    use SqlJoinTrait;

    public static function finder(): self
    {
        return Helper::finder(self::class);
    }

    protected static $multiAccountMatchCount = 0;

    /** @noinspection SqlResolve */
    public function addLinkedUsers(int $userId, ?int $maxDepth = null): self
    {
        if ($maxDepth === null)
        {
            $maxDepth = \XF::options()->svMaxMultiAccountGraphExpansions ?? 20;
        }

        $table = 'xf_sv_multiple_account_match_' . self::$multiAccountMatchCount;
        $table2 = $table . '_selection';
        self::$multiAccountMatchCount++;

        // xf_sv_multiple_account_report_data_user can be considered a graph, flatten on view
        // common table expressions would make this much easier, but use temp tables for pre-mysql 8.0/mariadb 10.2.0 support
        $db = \XF::db();
        $db->query("
            CREATE TEMPORARY TABLE $table (
                `report_data_id` int(10) UNSIGNED NOT NULL,
            PRIMARY KEY (`report_data_id`)
        )");

        $db->query("
            CREATE TEMPORARY TABLE $table2 (
                `report_data_id` int(10) UNSIGNED NOT NULL,
            PRIMARY KEY (`report_data_id`)
        )");

        // initial seeding
        $db->query("
            INSERT IGNORE INTO $table (`report_data_id`)
            SELECT reportDataUser.report_data_id
            FROM xf_sv_multiple_account_report_data_user AS `reportDataUser`
            JOIN `xf_sv_multiple_account_report_data` AS `reportData` ON (`reportDataUser`.`report_data_id` = `reportData`.`report_data_id`)
            WHERE reportDataUser.user_id = ? AND `reportData`.active = 1
        ", $userId)->rowsAffected();

        for ($i = 0; $i < $maxDepth; $i++)
        {
            // MySQL doesn't allow the temporary table to be referenced multiple times in the same query
            // As such we need to stage to another temporary table and then copy the results into the final temporary table
            // Otherwise the following error is thrown:
            // MySQL statement prepare error [1137]: Can't reopen table: 'xf_sv_multiple_account_match_???'

            $rows = $db->query("
                INSERT INTO $table2 (`report_data_id`)
                SELECT DISTINCT reportDataUser2.report_data_id
                FROM $table as userMatch
                JOIN xf_sv_multiple_account_report_data_user as reportDataUser1 on reportDataUser1.report_data_id = userMatch.report_data_id
                JOIN xf_sv_multiple_account_report_data_user as reportDataUser2 on reportDataUser1.user_id = reportDataUser2.user_id
                JOIN `xf_sv_multiple_account_report_data` AS `reportData` ON (`reportDataUser2`.`report_data_id` = `reportData`.`report_data_id`)
                where reportData.active = 1 and reportDataUser2.user_id <> ? 
            ", $userId)->rowsAffected();
            if ($rows === 0)
            {
                break;
            }

            $rows = $db->query("
                INSERT IGNORE INTO $table (`report_data_id`)
                SELECT report_data_id
                FROM $table2
            ")->rowsAffected();
            $db->emptyTable($table2);
            if ($rows === 0)
            {
                break;
            }
        }

        $this->sqlJoin($table, 'multiAccountMatch', ['report_data_id'], true);
        $this->sqlJoinConditions('multiAccountMatch', [
            ['report_data_id', '=', '$report_data_id']
        ]);
        $this->where('user_id', '!=', $userId);

        return $this;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
