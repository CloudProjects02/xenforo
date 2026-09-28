<?php

namespace SV\SignupAbuseBlocking\XF\Finder;

use SV\SignupAbuseBlocking\Globals;
use SV\StandardLib\Helper;
use XF\Entity\ChangeLog as ChangeLogEntity;
use XF\Mvc\Entity\ArrayCollection;
use function in_array;
use function str_pad;

/**
 * @Extends \XF\Finder\ChangeLog
 */
class ChangeLog extends XFCP_ChangeLog
{
    protected $svDateOfBirthFields =  ['dob_year','dob_month','dob_day'];
    protected $svDateOfBirthFieldsPadding =  ['dob_month' => 2,'dob_day' => 2];

    public function fetch($limit = null, $offset = null)
    {
        $originalLimit = $limit;
        $doFiltering = $limit !== null && $offset === null && (Globals::$filterChangeLogItems ?? false);
        if ($doFiltering)
        {
            $skipDateOfBirthInApprovalQueue = !(\XF::options()->svShowDateOfBirthInApprovalQueue ?? true);
            if ($skipDateOfBirthInApprovalQueue)
            {
                $this->where('field', '<>', array_values($this->svDateOfBirthFields));
                $doFiltering = false;
            }
            else
            {
                $limit += 3;
            }
        }

        $results = parent::fetch($limit, $offset);

        if ($doFiltering)
        {
            // collapse date of birth fields into a single field
            $arr = [];
            $firstDobFieldIndex = null;
            $dob = [];
            $userId = null;
            /**
             * @var int             $key
             * @var ChangeLogEntity $entity
             */
            foreach ($results as $key => $entity)
            {
                $field = $entity->field;
                if (in_array($field, $this->svDateOfBirthFields, true))
                {
                    if ($firstDobFieldIndex === null)
                    {
                        $firstDobFieldIndex = $key;
                        $userId = $entity->content_id;
                        $arr[$key] = null;
                    }
                    $padding = (int)($this->svDateOfBirthFieldsPadding[$field] ?? 0);
                    $dob[$field] = ($padding > 0)
                        ? str_pad($entity->new_value, $padding, '0', \STR_PAD_LEFT)
                        : $entity->new_value;
                    continue;
                }

                $arr[$key] = $entity;
            }
            if ($firstDobFieldIndex !== null && count($dob) === 3)
            {
                $dobChangeLog = Helper::createEntity(ChangeLogEntity::class);
                $dobChangeLog->field = 'date_of_birth';
                $dobChangeLog->content_type = 'user';
                $dobChangeLog->content_id = $userId;

                $dob = (new ArrayCollection($dob))
                    ->sortByList($this->svDateOfBirthFields)
                    ->toArray();
                $dobChangeLog->new_value = implode('-', $dob);

                $dobChangeLog->setReadOnly(true);
                $arr[$firstDobFieldIndex] = $dobChangeLog;
            }

            $results = new ArrayCollection($arr);
            $results = $results->slice(0, $originalLimit);
        }

        return $results;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
