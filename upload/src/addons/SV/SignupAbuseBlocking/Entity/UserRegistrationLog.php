<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace SV\SignupAbuseBlocking\Entity;

use SV\SignupAbuseBlocking\Repository\UserRegistrationLog as UserRegistrationLogRepo;
use SV\StandardLib\Helper;
use XF\Admin\App as AdminApp;
use XF\Entity\LinkableInterface;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\PreEscaped;
use function is_array;
use function is_numeric;
use function is_string;
use function reset;

/**
 * COLUMNS
 *
 * @property int|null                    $user_registration_log_id
 * @property int                         $log_date
 * @property int                         $user_id
 * @property string                      $username
 * @property string                      $ip_address
 * @property string                      $result
 * @property array                       $details_
 * @property array                       $request_state
 * @property int|null                    $asn
 * @property string|null                 $country
 * @property array<float, string[]>|null $browser_language
 * @property string|null                 $timezone
 * @property int|null                    $user_agent_id
 * GETTERS
 * @property-read Phrase                 $status
 * @property string[]                    $details
 * @property-read ?string                $user_agent
 * RELATIONS
 * @property-read ?UserEntity            $User
 * @property-read ?UserEntity            $ApplyingUser
 * @property-read ?UserAgent             $UserAgent
 */
class UserRegistrationLog extends Entity
{
    public static function create(): self
    {
        return Helper::createEntity(self::class);
    }

    public function wasModerated(): bool
    {
        return ($this->result ?: 'allowed') !== 'allowed';
    }

    public function getStatus(): Phrase
    {
        return \XF::phrase('sv_reg_status.' . ($this->result ?: 'allowed'));
    }

    public function getDetails(): array
    {
        $output = [];

        $app = \XF::app();
        $language = \XF::app()->language();
        $isAdmin = $app instanceof AdminApp;
        $publicRouter = \XF::app()->router('public');
        $router = $isAdmin ? \XF::app()->router('admin') : $publicRouter;
        $linkUsername = $this->getOption('linkUsernameInDetails');

        foreach ($this->details_ AS $detail)
        {
            if (!isset($detail['phrase']))
            {
                continue;
            }
            $data = $detail['data'] ?? [];

            // ensure score is consistently formatted.
            $score = $data['score'] ?? '';
            if ($score !== '')
            {
                if ($score === '0' || $score === '+0')
                {
                    $score = 'accept';
                }
                if (is_numeric($score))
                {
                    $score = $language->numberFormat((int)$score, 0);
                    $data['score'] = ($score > 0 ? '+' : '') . $score;
                }
                else
                {
                    $phraseKey = 'svSignupBlocking_score.' . $score;
                    $phraseText = $language->getPhraseText($phraseKey);
                    if (is_string($phraseText))
                    {
                        $data['score'] = \XF::phrase($phraseKey);
                    }
                }
            }

            // flatten, workaround bug in earlier alpha
            foreach($data as &$value)
            {
                if (is_array($value))
                {
                    $value = reset($value);
                }
            }

            if (isset($data['ip']))
            {
                // convert IP into a link!
                $link = $publicRouter->buildLink('canonical:misc/ip-info', null, ['ip' => $data['ip']]);
                $data['ip'] = new PreEscaped('<a href="' . $link . '">' . $data['ip'] . '</a>');
            }

            switch ($detail['phrase'])
            {
                case 'sv_reg_log.as_ok':
                case 'sv_reg_log.as_fail':
                    if (isset($data['number']))
                    {
                        $link = $publicRouter->buildLink('canonical:misc/asn-info', null, ['asn' => $data['number']]);
                        $data['number'] = new PreEscaped('<a href="' . $link . '">' . $data['number'] . '</a>');
                    }
                    break;
                case 'sv_reg_log.email_domain_whitelist_fail':
                case 'sv_reg_log.email_domain_whitelist_ok':
                case 'sv_reg_log.email_domain_whitelist_accept':
                    if (isset($data['domain']))
                    {
                        $url = \XF::options()->svSignupAbuseSearchUrl ?? '';
                        if (\strlen($url) === 0 || \strpos($url, '{url}') === false)
                        {
                            $url = 'https://duckduckgo.com/?q={url}';
                        }

                        $data['search'] = str_replace('{url}', urlencode($data['domain']), $url);
                    }
                    break;
                case 'sv_reg_log.multi_account_accept':
                case 'sv_reg_log.multi_account_accept_score':
                case 'sv_reg_log.multi_account_moderate':
                case 'sv_reg_log.multi_account_reject':
                    if ($linkUsername && isset($data['username']) && isset($data['user_id']))
                    {
                        $data['username'] = new PreEscaped('<a href="'. $router->buildLink($isAdmin ? 'canonical:users/edit' : 'canonical:members', $data) . '">' . \XF::escapeString($data['username']) . '</a>');
                    }
                    break;
                case 'sv_reg_log.multi_account_perm':
                    $data['perm'] = \XF::phrase('permission.' . $data['perm']);
                    break;
                case 'sv_reg_log.multi_account_perm_content':
                    $data['perm'] = \XF::phrase('permission.' . $data['perm']);
                    $title = $data['title'];
                    try
                    {
                        $entity = \XF::app()->findByContentType($data['contentType'], $data['contentId']);
                    }
                    catch (\Throwable $e)
                    {
                        // some 3rd party addons do not implement content types/entities sanely
                        $entity = null;
                    }
                    if ($entity instanceof LinkableInterface)
                    {
                        $detail['phrase'] = 'sv_reg_log.multi_account_perm_content_url';
                        $data['url'] = $entity->getContentUrl(true);
                        $title = $entity->getContentTitle();
                    }
                    else if ($entity instanceof Entity && $entity->isValidKey('title'))
                    {
                        $title = $entity->get('title');
                    }
                    $data['title'] = $title ?: $data['title'];
                    break;
            }

            $output[] = \XF::phrase($detail['phrase'], $data);
        }

        return $output;
    }

    protected function getUserAgent():?string
    {
        return $this->UserAgent->user_agent ?? null;
    }

    protected function getAsnLink(): string
    {
        if (!$this->asn)
        {
            return '';
        }

        return UserRegistrationLogRepo::get()->getAsnLink($this->asn);
    }

    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_sv_user_registration_log';
        $structure->shortName = 'SV\SignupAbuseBlocking:UserRegistrationLog';
        $structure->primaryKey = 'user_registration_log_id';
        $structure->columns = [
            'user_registration_log_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'log_date'                 => ['type' => self::UINT, 'default' => \XF::$time],
            'user_id'                  => ['type' => self::UINT, 'required' => true],
            'username'                 => ['type' => self::STR, 'maxLength' => 100],
            'ip_address'               => ['type' => self::BINARY, 'maxLength' => 16],
            'result'                   => ['type' => self::STR, 'required' => true],
            'details'                  => ['type' => self::JSON_ARRAY, 'required' => true],
            'request_state'            => ['type' => self::JSON_ARRAY, 'required' => true],
            'asn'                      => ['type' => self::UINT, 'nullable' => true, 'default' => null],
            'country'                  => ['type' => self::STR, 'maxLength' => 3, 'nullable' => true, 'default' => null],
            'browser_language'         => ['type' => self::JSON_ARRAY, 'nullable' => true, 'default' => null],
            'timezone'                 => ['type' => self::STR, 'maxLength' => 50, 'nullable' => true, 'default' => null],
            'user_agent_id'            => ['type' => self::UINT, 'nullable' => true, 'default' => null],
        ];
        $structure->getters = [
            'status'  => ['getter' => 'getStatus', 'cache' => true],
            'details' => ['getter' => 'getDetails', 'cache' => true],
            'user_agent' => ['getter' => 'getUserAgent', 'cache' => true],
            'asn_link' => ['getter' => 'getAsnLink', 'cache' => true],
        ];
        $structure->relations = [
            'User' => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => 'user_id',
                'primary'    => true
            ],
            'UserAgent' => [
                'entity'     => 'SV\SignupAbuseBlocking:UserAgent',
                'type'       => self::TO_ONE,
                'conditions' => 'user_agent_id',
                'primary'    => true,
            ],
        ];
        $structure->options['linkUsernameInDetails'] = true;

        return $structure;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
