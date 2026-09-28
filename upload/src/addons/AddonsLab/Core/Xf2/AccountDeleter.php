<?php
namespace AddonsLab\Core\Xf2;
use AddonsLab\Core\Service\AccountDeleterInterface;

class AccountDeleter implements AccountDeleterInterface
{
    public function deleteAccountById($accountId)
    {
        $account = \XF::finder('XF:User')->whereId($accountId)->fetchOne();
        if($account) {
            $account->delete();
        }
    }
}