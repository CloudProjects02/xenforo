<?php

namespace AddonsLab\Core\vB3;

use AddonsLab\Core\Service\AccountDeleterInterface;

class AccountDeleter implements AccountDeleterInterface
{
    public function deleteAccountById($accountId)
    {
        throw new \RuntimeException('vBulletin 3 does not implement this method yet.');
    }

}