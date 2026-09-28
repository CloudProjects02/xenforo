<?php

namespace SV\SignupAbuseBlocking\XF\ApprovalQueue;

use SV\SignupAbuseBlocking\Globals;
use SV\SignupAbuseBlocking\Repository\AllowEmailDomain as AllowEmailDomainRepo;
use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;
use SV\StandardLib\Helper;
use XF\Db\DuplicateKeyException;
use XF\Entity\ApprovalQueue as ApprovalQueueEntity;
use XF\Entity\BanEmail as BanEmailEntity;
use XF\Entity\User as UserEntity;
use XF\Phrase;
use XF\PrintableException;
use XF\Repository\Banning as BanningRepo;
use XF\Repository\Option as OptionRepo;
use XF\Service\User\EmailConfirmation as EmailConfirmationService;
use function explode;
use function trim;

/**
 * Class User
 *
 * @package SV\SignupAbuseBlocking\XF\ApprovalQueue
 */
class User extends XFCP_User
{
    /**
     * @param string                        $infix
     * @param UserEntity|ExtendedUserEntity $user
     * @return array
     */
    protected function getMoreActions(string $infix, UserEntity $user): array
    {
        return $this->getInput('more_' . $infix . '_actions', $user->user_id) ?: [];
    }

    /**
     * @param string                        $infix
     * @param UserEntity|ExtendedUserEntity $user
     * @param string                        $action
     * @return bool
     */
    protected function hasMoreAction(string $infix, UserEntity $user, string $action): bool
    {
        return \in_array($action, $this->getMoreActions($infix, $user), true);
    }

    /**
     * @param UserEntity|ExtendedUserEntity $user
     * @param string                        $action
     * @return bool
     */
    protected function hasMoreApproveAction(UserEntity $user, string $action): bool
    {
        return $this->hasMoreAction('approve', $user, $action);
    }

    /**
     * @param UserEntity|ExtendedUserEntity $user
     * @param string                        $action
     * @return bool
     */
    protected function hasMoreRejectAction(UserEntity $user, string $action): bool
    {
        return $this->hasMoreAction('reject', $user, $action);
    }

    public function doAllowEmailDomain(UserEntity $user)
    {
        /** @var ExtendedUserEntity $visitor */
        $visitor = \XF::visitor();
        if (!$visitor->canApproveEmailDomain())
        {
            return;
        }

        $email = $user->email;
        if (!$email)
        {
            return;
        }

        $emailParts = explode('@', $email, 2);
        if (empty($emailParts[1]))
        {
            return;
        }
        $emailDomain = trim($emailParts[1]);
        if (!$emailDomain)
        {
            return;
        }

        $allowEmailDomainRepo = AllowEmailDomainRepo::get();
        try
        {
            $allowEmailDomainRepo->addItem($emailDomain);
            \XF::app()->logger()->logModeratorAction('user', $user, 'sv_allowed_email_domain', [
                'email' => $email,
                'domain' => $emailDomain
            ]);
        }
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (DuplicateKeyException $exception)
        {
        }

        $allowedEmailDomain = $allowEmailDomainRepo->findItem($emailDomain);
        if ($allowedEmailDomain)
        {
            $allowEmailDomainRepo->triggerItemUsage($emailDomain);
        }
    }

    public function doRequireEmailConfirmation(UserEntity $user)
    {
        $user->user_state = 'email_confirm';
        $user->save();

        \XF::app()->logger()->logModeratorAction('user', $user, 'sv_email_confirm');

        $emailConfirmation = Helper::service(EmailConfirmationService::class, $user);
        $emailConfirmation->triggerConfirmation();
    }

    public function actionApprove(UserEntity $user)
    {
        if ($this->hasMoreApproveAction($user, 'sv_allow_email_domain'))
        {
            $this->doAllowEmailDomain($user);
        }

        if ($user->email && $this->hasMoreApproveAction($user, 'sv_require_email_confirm'))
        {
            $this->doRequireEmailConfirmation($user);

            return;
        }

        parent::actionApprove($user);
    }

    /** @noinspection PhpRedundantCatchClauseInspection */
    protected function doBanEmailDomain(UserEntity $user)
    {
        /** @var ExtendedUserEntity $visitor */
        $visitor = \XF::visitor();
        if (!$visitor->canBanEmailDomain())
        {
            return;
        }

        $email = $user->email;
        if (!$email)
        {
            return;
        }

        $emailParts = explode('@', $email, 2);
        $emailDomain = trim($emailParts[1] ?? '');
        if ($emailDomain === '')
        {
            return;
        }

        $banEmailDomain = '*@'.$emailDomain;

        $emailBan = Helper::findOne(BanEmailEntity::class, ['banned_email' => $banEmailDomain]);
        if ($emailBan !== null)
        {
            $emailBan->fastUpdate('last_triggered_date', \time());
            return;
        }

        try
        {
            Helper::repository(BanningRepo::class)->banEmail($banEmailDomain);
            \XF::app()->logger()->logModeratorAction('user', $user, 'sv_banned_email_domain', [
                'email' => $email,
                'domain' => $emailDomain
            ]);
        }
        catch(PrintableException $e)
        {
            $messages = $e->getMessages();
            foreach ($messages as $message)
            {
                if ($message instanceof Phrase && $message->getName() === 'banned_emails_must_be_unique')
                {
                    // ignore
                    return;
                }
            }

            throw $e;
        }
        catch (DuplicateKeyException $exception)
        {
            // ignore
        }

        $emailBan = Helper::findOne(BanEmailEntity::class, ['banned_email' => $banEmailDomain]);
        if ($emailBan !== null)
        {
            $emailBan->fastUpdate('last_triggered_date', \time());
        }
    }

    /**
     * @param UserEntity|ExtendedUserEntity $user
     */
    protected function doBanAsn(UserEntity $user)
    {
        /** @var ExtendedUserEntity $visitor */
        $visitor = \XF::visitor();
        if (!$visitor->canBanAsn())
        {
            return;
        }

        $asn = $user->hasBannableASN(false);
        $asNumber = $asn['number'] ?? null;
        if ($asNumber === null)
        {
            return;
        }

        $asnRejectLine = 'reject|' . $asNumber;
        $options = \XF::app()->options();
        $svSignupAsnBlockingRule = $options->svSignupAsnBlockingRule ?? '';

        if (\preg_match_all('/^[^|]*\|' . \preg_quote($asNumber) . '$/sim', $svSignupAsnBlockingRule, $matches))
        {
            if (isset($matches[0]))
            {
                $svSignupAsnBlockingRule = \str_replace($matches[0], '', $svSignupAsnBlockingRule);
            }
        }
        $svSignupAsnBlockingRule .= "\n{$asnRejectLine}";
        $svSignupAsnBlockingRule = \preg_replace("/\n\n+/", "\n", $svSignupAsnBlockingRule);

        Helper::repository(OptionRepo::class)->updateOption('svSignupAsnBlockingRule', $svSignupAsnBlockingRule);
        $options->svSignupAsnBlockingRule = $svSignupAsnBlockingRule;

        \XF::app()->logger()->logModeratorAction('user', $user, 'sv_banned_asn', [
            'asn' => $asNumber,
        ]);
    }

    public function actionReject(UserEntity $user)
    {
        if ($this->hasMoreRejectAction($user, 'sv_ban_email_domain'))
        {
            $this->doBanEmailDomain($user);
        }

        if ($this->hasMoreRejectAction($user, 'sv_ban_asn'))
        {
            $this->doBanAsn($user);
        }

        parent::actionReject($user);
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function getTemplateData(ApprovalQueueEntity $unapprovedItem)
    {
        Globals::$filterChangeLogItems = true;
        try
        {
            return parent::getTemplateData($unapprovedItem);
        }
        finally
        {
            Globals::$filterChangeLogItems = false;
        }
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
