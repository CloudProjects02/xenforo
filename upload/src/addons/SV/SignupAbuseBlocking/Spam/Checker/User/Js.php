<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\User;

use SV\SignupAbuseBlocking\Spam\IScoringChecker;
use XF\Entity\User as UserEntity;
use XF\Spam\Checker\AbstractProvider;
use XF\Spam\Checker\UserCheckerInterface;

class Js extends AbstractProvider implements UserCheckerInterface
{
    protected function getType(): string
    {
        return 'SignupAbuseJs';
    }

    public function check(UserEntity $user, array $extraParams = []): void
    {
        $options = \XF::options();
        $fastFill = (int)($options->svSignupFastRegFill ?? 0);
        $fastFillScore = $options->svSignupFastRegFillScore ?? '';
        $noJsScore = $options->svSignupNoJsScore ?? '';
        if ((!$noJsScore && !$fastFillScore) || !($this->checker instanceof IScoringChecker))
        {
            return;
        }
        /** @var IScoringChecker $checker */
        $checker = $this->checker;

        $timeTaken = (int)$this->app->request()->get('timetaken', 0);

        if ($timeTaken && $fastFill)
        {
            if ($timeTaken < $fastFill)
            {
                switch ($fastFillScore)
                {
                    case 'reject':
                        $checker->logScoreReject('sv_reg_log.js_timer_fail', ['timetaken' => $timeTaken]);
                        break;
                    case 'moderate':
                        $checker->logScoreModerate('sv_reg_log.js_timer_fail', ['timetaken' => $timeTaken]);
                        break;
                    case '0':
                        $checker->logScoreAccept('sv_reg_log.js_timer_fail', ['timetaken' => $timeTaken]);
                        break;
                    default:
                        $checker->logScore('sv_reg_log.js_timer_fail', $fastFillScore, ['timetaken' => $timeTaken]);
                        break;
                }
            }
            else
            {
                $checker->logScoreAccept('sv_reg_log.js_timer_ok', ['timetaken' => $timeTaken]);
            }
        }
        else if (!$timeTaken)
        {
            switch ($noJsScore)
            {
                case 'reject':
                    $checker->logScoreReject('sv_reg_log.js_fail');
                    break;
                case 'moderate':
                    $checker->logScoreModerate('sv_reg_log.js_fail');
                    break;
                case '0':
                    $checker->logScoreAccept('sv_reg_log.js_fail');
                    break;
                default:
                    $checker->logScore('sv_reg_log.js_fail', $noJsScore);
                    break;
            }
        }
    }

    public function submit(UserEntity $user, array $extraParams = [])
    {
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
