<?php

namespace Jace\TokenDownloads\Purchasable;

use XF\Purchasable\AbstractPurchasable;
use XF\Purchasable\Purchase;
use XF\Payment\CallbackState;

class TokenPackage extends AbstractPurchasable
{
    public function getTitle()
    {
        return \XF::phrase('jace_token_downloads_token_packages');
    }

    public function getPurchaseFromRequest(\XF\Http\Request $request, \XF\Entity\User $purchaser, &$error = null)
    {
        if (!$purchaser->user_id)
        {
            $error = \XF::phrase('login_required');
            return false;
        }

        $profileId = $request->filter('payment_profile_id', 'uint');
        $paymentProfile = \XF::em()->find('XF:PaymentProfile', $profileId);
        if (!$paymentProfile || !$paymentProfile->active)
        {
            $error = \XF::phrase('please_choose_valid_payment_profile_to_continue_with_your_purchase');
            return false;
        }

        $packageId = $request->filter('package_id', 'uint');
        /** @var \Jace\TokenDownloads\Entity\Package $package */
        $package = \XF::em()->find('Jace\TokenDownloads:Package', $packageId);
        if (!$package || !$package->canPurchase())
        {
            $error = \XF::phrase('this_item_cannot_be_purchased_at_moment');
            return false;
        }

        if (!in_array($profileId, $package->payment_profile_ids))
        {
            $error = \XF::phrase('selected_payment_profile_is_not_valid_for_this_purchase');
            return false;
        }

        return $this->getPurchaseObject($paymentProfile, $package, $purchaser);
    }

    public function validatePurchasable(CallbackState $state, &$error = null): bool
    {
        $purchaseRequest = $state->getPurchaseRequest();
        $extraData = $purchaseRequest->extra_data;

        /** @var \Jace\TokenDownloads\Entity\Package $package */
        $package = \XF::em()->find('Jace\TokenDownloads:Package', $extraData['package_id']);
        if (!$package)
        {
            $error = "Unable to find token package '$extraData[package_id]'";
            return false;
        }

        return true;
    }

    public function getPurchasableFromExtraData(array $extraData)
    {
        $output = [
            'link' => '',
            'title' => '',
            'purchasable' => null
        ];
        
        /** @var \Jace\TokenDownloads\Entity\Package $package */
        $package = \XF::em()->find('Jace\TokenDownloads:Package', $extraData['package_id']);
        if ($package)
        {
            $output['link'] = \XF::app()->router('admin')->buildLink('token-downloads/packages/edit', $package);
            $output['title'] = $package->title;
            $output['purchasable'] = $package;
        }
        
        return $output;
    }

    public function getPurchaseFromExtraData(array $extraData, \XF\Entity\PaymentProfile $paymentProfile, \XF\Entity\User $purchaser, &$error = null)
    {
        $package = $this->getPurchasableFromExtraData($extraData);
        if (!$package['purchasable'] || !$package['purchasable']->canPurchase())
        {
            $error = \XF::phrase('this_item_cannot_be_purchased_at_moment');
            return false;
        }

        if (!in_array($paymentProfile->payment_profile_id, $package['purchasable']->payment_profile_ids))
        {
            $error = \XF::phrase('selected_payment_profile_is_not_valid_for_this_purchase');
            return false;
        }

        return $this->getPurchaseObject($paymentProfile, $package['purchasable'], $purchaser);
    }

    /**
     * @param \XF\Entity\PaymentProfile $paymentProfile
     * @param \Jace\TokenDownloads\Entity\Package $purchasable
     * @param \XF\Entity\User $purchaser
     *
     * @return Purchase
     */
    public function getPurchaseObject(\XF\Entity\PaymentProfile $paymentProfile, $purchasable, \XF\Entity\User $purchaser)
    {
        $purchase = new Purchase();

        $purchase->title = \XF::phrase('jace_token_downloads_token_package') . ': ' . $purchasable->title . ' (' . $purchaser->username . ')';
        $purchase->description = $purchasable->description;
        $purchase->cost = $purchasable->cost_amount;
        $purchase->currency = $purchasable->cost_currency;
        $purchase->recurring = false; // Token packages are not recurring
        $purchase->lengthAmount = 0;
        $purchase->lengthUnit = '';
        $purchase->purchaser = $purchaser;
        $purchase->paymentProfile = $paymentProfile;
        $purchase->purchasableTypeId = $this->purchasableTypeId;
        $purchase->purchasableId = $purchasable->package_id;
        $purchase->purchasableTitle = $purchasable->title;
        $purchase->extraData = [
            'package_id' => $purchasable->package_id
        ];

        $router = \XF::app()->router('public');

        $purchase->returnUrl = $router->buildLink('canonical:account/token-downloads');
        $purchase->cancelUrl = $router->buildLink('canonical:account/token-downloads');

        return $purchase;
    }

    public function completePurchase(CallbackState $state)
    {
        $purchaseRequest = $state->getPurchaseRequest();
        $packageId = $purchaseRequest->extra_data['package_id'];
        $purchaseId = $purchaseRequest->extra_data['purchase_id'] ?? null;
        
        $paymentResult = $state->paymentResult;
        $purchaser = $state->getPurchaser();

        /** @var \Jace\TokenDownloads\Entity\Package $package */
        $package = \XF::em()->find('Jace\TokenDownloads:Package', $packageId);
        if (!$package)
        {
            $state->logType = 'error';
            $state->logMessage = 'Could not find the specified token package.';
            return;
        }

        switch ($paymentResult)
        {
            case CallbackState::PAYMENT_RECEIVED:
                /** @var \Jace\TokenDownloads\Entity\Purchase $purchase */
                $purchase = \XF::em()->create('Jace\TokenDownloads:Purchase');
                $purchase->user_id = $purchaser->user_id;
                $purchase->package_id = $package->package_id;
                $purchase->tokens_total = $package->download_limit;
                $purchase->tokens_remaining = $package->download_limit;
                $purchase->save();
                
                if ($purchaseRequest)
                {
                    $extraData = $purchaseRequest->extra_data;
                    $extraData['purchase_id'] = $purchase->purchase_id;
                    $purchaseRequest->extra_data = $extraData;
                    $purchaseRequest->save();
                }

                $state->logType = 'payment';
                $state->logMessage = 'Payment received, tokens added.';
                break;

            case CallbackState::PAYMENT_REINSTATED:
                if ($purchaseId)
                {
                    /** @var \Jace\TokenDownloads\Entity\Purchase $purchase */
                    $purchase = \XF::em()->find('Jace\TokenDownloads:Purchase', $purchaseId);
                    if ($purchase && $purchase->user_id == $purchaser->user_id)
                    {
                        // Restore tokens
                        $purchase->tokens_remaining = $purchase->tokens_total;
                        $purchase->save();
                        
                        $state->logType = 'payment';
                        $state->logMessage = 'Reversal cancelled, tokens restored.';
                    }
                    else
                    {
                        $state->logType = 'info';
                        $state->logMessage = 'OK, no action.';
                    }
                }
                else
                {
                    $state->logType = 'info';
                    $state->logMessage = 'OK, no action.';
                }
                break;
        }
    }

    public function reversePurchase(CallbackState $state)
    {
        $purchaseRequest = $state->getPurchaseRequest();
        $purchaseId = $purchaseRequest->extra_data['purchase_id'] ?? null;
        
        if (!$purchaseId)
        {
            $state->logType = 'info';
            $state->logMessage = 'No purchase to reverse.';
            return;
        }
        
        /** @var \Jace\TokenDownloads\Entity\Purchase $purchase */
        $purchase = \XF::em()->find('Jace\TokenDownloads:Purchase', $purchaseId);
        if (!$purchase)
        {
            $state->logType = 'info';
            $state->logMessage = 'Could not find purchase to reverse.';
            return;
        }
        
        // Set tokens to 0
        $purchase->tokens_remaining = 0;
        $purchase->save();

        $state->logType = 'cancel';
        $state->logMessage = 'Payment refunded/reversed, tokens removed.';
    }

    public function getPurchasablesByProfileId($profileId)
    {
        /** @var \XF\Mvc\Entity\Finder $finder */
        $finder = \XF::finder('Jace\TokenDownloads:Package');

        $profileId = (int)$profileId;
        $packages = $finder->whereSql('FIND_IN_SET(%s, payment_profile_ids)', $profileId)->fetch();
        $output = [];

        foreach ($packages AS $package)
        {
            $output[] = [
                'title' => $package->title,
                'link' => \XF::app()->router('admin')->buildLink('token-downloads/packages/edit', $package)
            ];
        }

        return $output;
    }
} 