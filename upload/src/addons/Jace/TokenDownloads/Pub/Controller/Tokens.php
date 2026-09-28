<?php

namespace Jace\TokenDownloads\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Tokens extends AbstractController
{
    public function actionIndex()
    {
        $this->assertRegistrationRequired();
        
        $packageRepo = $this->repository('Jace\TokenDownloads:Package');
        $packages = $packageRepo->findActivePackagesForList()->fetch();
        
        $purchaseRepo = $this->repository('Jace\TokenDownloads:Purchase');
        $purchases = $purchaseRepo->findPurchasesForUser(\XF::visitor()->user_id)->fetch();
        
        $activePurchases = $purchaseRepo->findActivePurchasesForUser(\XF::visitor()->user_id)->fetch();
        
        $totalRemaining = 0;
        foreach ($activePurchases as $purchase)
        {
            $totalRemaining += $purchase->tokens_remaining;
        }
        
        $viewParams = [
            'packages' => $packages,
            'purchases' => $purchases,
            'totalRemaining' => $totalRemaining
        ];
        return $this->view('Jace\TokenDownloads:Tokens\Listing', 'jace_token_downloads_tokens', $viewParams);
    }
    
    public function actionPurchase(ParameterBag $params)
    {
        $this->assertRegistrationRequired();
        
        $package = $this->assertPackageExists($params->package_id);
        if (!$package->canPurchase())
        {
            return $this->error(\XF::phrase('jace_token_downloads_package_not_available_for_purchase'));
        }
        
        $paymentRepo = $this->repository('XF:Payment');
        $paymentProfiles = $paymentRepo->findPaymentProfilesForList()
            ->where('payment_profile_id', $package->payment_profile_ids)
            ->fetch();
        
        $viewParams = [
            'package' => $package,
            'paymentProfiles' => $paymentProfiles
        ];
        return $this->view('Jace\TokenDownloads:Tokens\Purchase', 'jace_token_downloads_purchase', $viewParams);
    }
    
    public function actionHistory()
    {
        $this->assertRegistrationRequired();
        
        $purchaseRepo = $this->repository('Jace\TokenDownloads:Purchase');
        $purchases = $purchaseRepo->findPurchasesForUser(\XF::visitor()->user_id)->fetch();
        
        // Find all token log entries
        $logs = $this->finder('Jace\TokenDownloads:Log')
            ->where('user_id', \XF::visitor()->user_id)
            ->with(['Resource', 'ResourceVersion'])
            ->setDefaultOrder('log_date', 'DESC')
            ->fetch();
        
        $viewParams = [
            'purchases' => $purchases,
            'logs' => $logs
        ];
        return $this->view('Jace\TokenDownloads:Tokens\History', 'jace_token_downloads_history', $viewParams);
    }
    
    /**
     * @param int $id
     * @param array $with
     *
     * @return \Jace\TokenDownloads\Entity\Package
     */
    protected function assertPackageExists($id, array $with = [])
    {
        $package = $this->em()->find('Jace\TokenDownloads:Package', $id, $with);
        if (!$package)
        {
            throw $this->exception($this->notFound(\XF::phrase('jace_token_downloads_requested_package_not_found')));
        }

        return $package;
    }
} 