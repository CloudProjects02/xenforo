<?php
namespace AddonsLab\Core\Xf1;
use AddonsLab\Core\Service\AccountDeleterInterface;

class AccountDeleter implements AccountDeleterInterface
{
    public function deleteAccountById($accountId)
    {
        try {
            $dw = \XenForo_DataWriter::create('XenForo_DataWriter_User');
            $dw->setExistingData($accountId);
            $dw->delete();
            return true;
        } catch (\Exception $exception) {
            return false;
        }
        
    }

}