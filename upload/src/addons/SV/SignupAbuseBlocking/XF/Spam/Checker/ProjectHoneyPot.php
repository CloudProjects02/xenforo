<?php

namespace SV\SignupAbuseBlocking\XF\Spam\Checker;



use SV\SignupAbuseBlocking\Spam\IScoringChecker;
use XF\App;
use XF\Entity\User as UserEntity;
use XF\Spam\AbstractChecker;

/**
 * @Extends \XF\Spam\Checker\ProjectHoneyPot
 */
class ProjectHoneyPot extends XFCP_ProjectHoneyPot
{
    /** @var array */
    protected $svProjectHoneyPotExtendedConfig;

    public function __construct(AbstractChecker $checker, App $app)
    {
        parent::__construct($checker, $app);

        $this->svProjectHoneyPotExtendedConfig = $this->app()->options()->svProjectHoneyPotExtendedConfig ?? [];
        $this->dateCutOff = $this->svProjectHoneyPotExtendedConfig['cutOff'] ?? 0;
    }

    public function check(UserEntity $user, array $extraParams = [])
    {
        if ($this->checker instanceof IScoringChecker)
        {
            $options = $this->app()->options();
            $key = $options->registrationCheckDnsBl['projectHoneyPotKey'] ?? '';
            if (\strlen($key) === 0)
            {
                return;
            }

            $block = $this->checkIp($key . '.%s.dnsbl.httpbl.org', false);
            if ($block)
            {
                // decode the response, see; https://www.projecthoneypot.org/httpbl_api.php
                $lastSeen = \intval($block[1]);
                $threadLevel = \intval($block[2]);
                $spamType = \intval($block[3]); // not used

                $score = null;
                if ($block[0] == '127'
                    && $lastSeen <= $this->dateCutOff
                    && $spamType)
                {
                    $score = 0;
                    if ($threadLevel >= $this->minThreatLevel)
                    {
                        if ($threadLevel >= 80)
                        {
                            $score = $this->svProjectHoneyPotExtendedConfig['score80to100'] ?? 0;
                        }
                        else if ($threadLevel >= 10)
                        {
                            $score = $this->svProjectHoneyPotExtendedConfig['score10to20'] ?? 0;
                        }
                    }
                }

                if ($score)
                {
                    $block = true;
                    $this->checker->logScore('sv_reg_log.honeypot_fail', $score, ['lastseen' => $lastSeen, 'threatlevel' => $threadLevel]);
                }
                else if ($score === 0)
                {
                    $block = false;
                    $this->checker->logScoreAccept('sv_reg_log.honeypot_pass', ['lastseen' => $lastSeen, 'threatlevel' => $threadLevel]);
                }
                else
                {
                    $block = false;
                    $this->checker->logScoreAccept('sv_reg_log.honeypot_ok');
                }
            }
            $oldVal = $this->checker->patchLogDecision(true);
            try
            {
                $this->processDecision($block, true);
            }
            finally
            {
                $this->checker->patchLogDecision($oldVal);
            }

            return;
        }

        parent::check($user, $extraParams);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
