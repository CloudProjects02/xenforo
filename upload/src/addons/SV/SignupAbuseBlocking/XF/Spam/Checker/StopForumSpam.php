<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SignupAbuseBlocking\XF\Spam\Checker;

use SV\SignupAbuseBlocking\Spam\IScoringChecker;
use XF\Entity\User as UserEntity;

/**
 * @Extends \XF\Spam\Checker\StopForumSpam
 */
class StopForumSpam extends XFCP_StopForumSpam
{
    /**
     * @param UserEntity $user
     * @param array      $extraParams
     */
    public function check(UserEntity $user, array $extraParams = [])
    {
        if ($this->checker instanceof IScoringChecker)
        {
            $oldVal = $this->checker->patchLogDecision(true);
            try
            {
                parent::check($user, $extraParams);
            }
            finally
            {
                $this->checker->patchLogDecision($oldVal);
            }
            // bail
            return;
        }

        parent::check($user, $extraParams);
    }

    /**
     * @param array $data
     * @param array $counts
     * @return int
     */
    protected function getSfsSpamFlagCount(array $data, &$counts = [])
    {
        $flagCount = parent::getSfsSpamFlagCount($data, $counts);

        if ($this->checker instanceof IScoringChecker && $flagCount > 0)
        {
            $option = $this->app()->options()->stopForumSpam ?? [];
            $extendedConfig = \XF::options()->svSFSExtendedConfig ?? [];

            foreach (['username', 'email', 'emailhash', 'ip'] AS $flagName)
            {
                if (!empty($data[$flagName]))
                {
                    $flag = $data[$flagName];

                    if (!empty($flag['appears']))
                    {
                        if ($flag['frequency'] >= ($option['frequencyCutOff'] ?? 0))
                        {
                            if (empty($option['lastSeenCutOff']) || $flag['lastseen'] >= time() - $option['lastSeenCutOff'] * 86400)
                            {
                                if ($flagName == 'emailhash')
                                {
                                    // consider emailhash flag to be same as email
                                    $flagName = 'email';
                                }
                                if (isset($extendedConfig[$flagName]))
                                {
                                    $score = $extendedConfig[$flagName];
                                    if ($score > 0)
                                    {
                                        $this->checker->logScore('sv_reg_log.sfs_fail', $score, ['flag' => $flagName]);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return 0;
        }

        return $flagCount;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
