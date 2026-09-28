<?php

namespace SV\SignupAbuseBlocking\Entity;

use SV\SignupAbuseBlocking\Finder\ReportDataUser as ReportDataUserFinder;
use SV\StandardLib\Helper;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use function max;
use function min;

/**
 * @property int                                                                                                          $user_id
 * @property int                                                                                                          $first_seen_date
 * @property int                                                                                                          $last_seen_date
 * @property int                                                                                                          $count
 * GETTERS
 * @property-read array<int,array{user_id: int, first_seen_date: int, last_seen_date: int, count: int, User: UserEntity}> $OtherUsers
 * RELATIONS
 * @property-read ?UserEntity                                                                                             $User
 * @property-read AbstractCollection<ReportDataUser>                                                                      $ReportDataUser
 */
class MultiAccountUser extends Entity
{
    public static function create(): self
    {
        return Helper::createEntity(self::class);
    }

    public function rebuild(): bool
    {
        $row = \XF::db()->fetchRow('
            SELECT MIN(reportDataUser.first_seen_date) as first_seen_date
                 , MAX(reportDataUser.last_seen_date) as last_seen_date
            FROM xf_sv_multiple_account_report_data_user as reportDataUser
            JOIN xf_sv_multiple_account_report_data as reportData on reportData.report_data_id = reportDataUser.report_data_id
            WHERE reportDataUser.user_id = ? AND reportData.active = 1
            GROUP BY reportDataUser.user_id
        ', $this->user_id);

        if (!is_array($row) || count($row) === 0)
        {
            return false;
        }

        $this->first_seen_date = (int)$row['first_seen_date'];
        $this->last_seen_date = (int)$row['last_seen_date'];
        $this->count = 0;

        return true;
    }

    protected function getOtherUsers(): array
    {
        $finder = ReportDataUserFinder::finder();

        $output = [];
        /** @var ReportDataUser[] $results */
        $results = $finder
            ->addLinkedUsers($this->user_id)
            ->with('User', true)
            ->order('last_seen_date', 'desc')
            ->order('first_seen_date', 'desc')
            ->fetch();
        foreach ($results as $reportDataUser)
        {
            $userId = $reportDataUser->user_id;
            $rec = $output[$userId] ?? null;

            if ($rec === null)
            {
                $rec = [
                    'user_id' => $userId,
                    'User' => $reportDataUser->User,
                    'first_seen_date' => $reportDataUser->first_seen_date,
                    'last_seen_date' => $reportDataUser->last_seen_date,
                    'count' => 0,
                ];
            }

            $rec['count'] += $reportDataUser->count;
            $rec['first_seen_date'] = min($rec['first_seen_date'], $reportDataUser->first_seen_date);
            $rec['last_seen_date'] = max($rec['last_seen_date'], $reportDataUser->last_seen_date);

            $output[$userId] = $rec;
        }

        return $output;
    }

    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_sv_multiple_account_user';
        $structure->shortName = 'SV\SignupAbuseBlocking:MultiAccountUser';
        $structure->primaryKey = 'user_id';
        $structure->columns = [
            'user_id'         => ['type' => self::UINT, 'required' => true],
            'first_seen_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'last_seen_date'  => ['type' => self::UINT, 'default' => \XF::$time],
            'count'           => ['type' => self::UINT, 'default' => 0],
        ];

        $structure->getters = [
            'OtherUsers' => ['getter' => 'getOtherUsers', 'cache' => true],
        ];

        $structure->relations = [
            'User'           => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => 'user_id',
                'primary'    => true
            ],
            'ReportDataUser' => [
                'entity'     => 'SV\SignupAbuseBlocking:ReportDataUser',
                'type'       => self::TO_MANY,
                'conditions' => 'user_id',
            ],
        ];

        $structure->defaultWith = [];

        return $structure;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
