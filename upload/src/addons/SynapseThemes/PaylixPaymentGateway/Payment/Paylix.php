<?php

namespace SynapseThemes\PaylixPaymentGateway\Payment;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use XF\Entity\PaymentProfile;
use XF\Entity\PurchaseRequest;
use XF\Http\Request;
use XF\Mvc\Controller;
use XF\Mvc\Reply\AbstractReply;
use XF\Payment\AbstractProvider;
use XF\Payment\CallbackState;
use XF\Purchasable\Purchase;

class Paylix extends AbstractProvider
{
    protected $httpClient;

    public function __construct($providerId)
    {
        parent::__construct($providerId);
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'verify' => false, // Disable SSL verification for development
        ]);
    }

    public function getTitle(): string
    {
        return "Paylix";
    }

    public function getApiEndpoint(): string
    {
        return 'https://dev.paylix.gg/v1';
    }

    public function renderConfig(PaymentProfile $profile)
    {
        $data = [
            'profile' => $profile,
        ];
        return \XF::app()->templater()->renderTemplate('admin:payment_profile_synapse_paylix_gateway', $data);
    }

    public function verifyConfig(array &$options, &$errors = [])
    {
        if (empty($options['api_key']))
        {
            $errors[] = \XF::phrase('paylix_provide_api_key');
            return false;
        }

        if (empty($options['webhook_secret']))
        {
            $errors[] = \XF::phrase('paylix_provide_webhook_secret');
            return false;
        }

        // Test API connection
        try
        {
            $response = $this->makeApiRequest('GET', '/products', $options['api_key']);
            
            if (!$response || !isset($response['status']) || $response['status'] !== 200)
            {
                $errors[] = \XF::phrase('paylix_api_connection_error');
                return false;
            }
            
            return true;
        }
        catch (\Exception $e)
        {
            $errors[] = \XF::phrase('paylix_api_connection_text_failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function initiatePayment(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase)
    {
        // Skip the initiate template and go directly to Paylix checkout
        return $this->processPayment($controller, $purchaseRequest, $purchase->paymentProfile, $purchase);
    }

    public function processPayment(Controller $controller, PurchaseRequest $purchaseRequest, PaymentProfile $paymentProfile, Purchase $purchase)
    {
        $purchaser = $purchase->purchaser;
        $options = $paymentProfile->options;

        try
        {
            // Create payment using Paylix API
            $paymentData = [
                'title' => $purchase->title,
                'value' => $purchase->cost,
                'currency' => strtoupper($purchase->currency),
                'quantity' => 1,
                'email' => $purchaser->email,
                'white_label' => false,
                'return_url' => $purchase->returnUrl,
                'webhook' => $this->getCallbackUrl(),
                'gateway' => null,
                'gateways' => null,
                'auto_fulfill' => true,
                'custom_fields' => [
                    'request_key' => $purchaseRequest->request_key,
                    'user_id' => $purchaser->user_id,
                    'username' => $purchaser->username,
                    'purchasable_type_id' => $purchase->purchasableTypeId,
                    'purchasable_id' => $purchase->purchasableId,
                ],
            ];

            $response = $this->makeApiRequest('POST', '/payments', $options['api_key'], $paymentData);

            if (!$response || !isset($response['data']['uniqid'])) {
                throw new \Exception('Failed to create payment: ' . json_encode($response));
            }

            $paymentId = $response['data']['uniqid'];
            $paymentUrl = $response['data']['url_branded'] ?? $response['data']['url'] ?? null;

            if (!$paymentUrl) {
                throw new \Exception('No payment URL returned from Paylix: ' . json_encode($response['data']));
            }

            // Store payment ID in purchase request for callback
            $purchaseRequest->fastUpdate('provider_metadata', json_encode([
                'paylix_payment_id' => $paymentId,
            ]));

            // Redirect to Paylix payment page
            return $controller->redirect($paymentUrl);
        }
        catch (\Exception $e)
        {
            \XF::logException($e, false, 'Paylix payment processing error: ');
            throw $controller->exception($controller->error('Payment processing failed. Please try again.'));
        }
    }

    public function setupCallback(Request $request)
    {
        $state = new CallbackState();
        
        // Get raw payload for signature verification
        $state->rawPayload = $request->getInputRaw();
        
        // Get Paylix webhook headers
        $state->webhookSignature = $request->getServer('HTTP_X_PAYLIX_SIGNATURE');
        $state->webhookEvent = $request->getServer('HTTP_X_PAYLIX_EVENT');
        
        // Parse the JSON payload
        $payload = json_decode($state->rawPayload, true);
        
        if ($payload && isset($payload['data'])) {
            $data = $payload['data'];
            
            // Extract order information from custom_fields
            if (isset($data['custom_fields'])) {
                $state->requestKey = $data['custom_fields']['request_key'] ?? '';
                $state->subscriberId = $data['custom_fields']['user_id'] ?? '';
            }
            
            $state->transactionId = $data['uniqid'] ?? $data['id'] ?? '';
            $state->paymentCountry = $data['customer_country_code'] ?? '';
        }
        
        $state->ip = $request->getIp();
        
        return $state;
    }

    public function validateCallback(CallbackState $state)
    {
        if (!$state->webhookSignature || !$state->webhookEvent)
        {
            $state->logType = 'error';
            $state->logMessage = 'Missing webhook signature or event';
            return false;
        }

        // Verify webhook signature
        $purchaseRequest = $state->getPurchaseRequest();
        if (!$purchaseRequest)
        {
            $state->logType = 'error';
            $state->logMessage = 'Could not find purchase request';
            return false;
        }

        $paymentProfile = $state->getPaymentProfile();
        if (!$paymentProfile)
        {
            $state->logType = 'error';
            $state->logMessage = 'Could not find payment profile';
            return false;
        }

        $webhookSecret = $paymentProfile->options['webhook_secret'];
        $expectedSignature = hash_hmac('sha512', $state->rawPayload, $webhookSecret);

        if (!hash_equals($expectedSignature, $state->webhookSignature))
        {
            $state->logType = 'error';
            $state->logMessage = 'Invalid webhook signature';
            return false;
        }

        return true;
    }

    public function getPaymentResult(CallbackState $state)
    {
        $event = $state->webhookEvent;

        switch ($event)
        {
            case 'order:paid':
            case 'order:paid:product':
                $state->paymentResult = CallbackState::PAYMENT_RECEIVED;
                break;

            case 'order:cancelled':
            case 'order:cancelled:product':
                $state->paymentResult = CallbackState::PAYMENT_REVERSED;
                break;

            case 'subscription:created':
            case 'subscription:renewed':
                $state->paymentResult = CallbackState::PAYMENT_RECEIVED;
                break;

            case 'subscription:cancelled':
                $state->paymentResult = CallbackState::PAYMENT_REVERSED;
                break;

            default:
                $state->paymentResult = CallbackState::PAYMENT_RECEIVED;
                break;
        }
    }

    public function prepareLogData(CallbackState $state)
    {
        $state->logDetails = [
            'webhook_event' => $state->webhookEvent,
            'transaction_id' => $state->transactionId,
            'subscriber_id' => $state->subscriberId,
            'payment_country' => $state->paymentCountry,
            'ip' => $state->ip,
        ];
    }

    public function renderCancellationTemplate(PurchaseRequest $purchaseRequest)
    {
        return $this->renderCancellationDefault($purchaseRequest);
    }

    public function processCancellation(Controller $controller, PurchaseRequest $purchaseRequest, PaymentProfile $paymentProfile)
    {
        $metadata = json_decode($purchaseRequest->provider_metadata, true) ?: [];
        $subscriptionId = $metadata['paylix_subscription_id'] ?? null;

        if ($subscriptionId)
        {
            try
            {
                $this->makeApiRequest('DELETE', "/subscriptions/{$subscriptionId}", $paymentProfile->options['api_key']);
            }
            catch (\Exception $e)
            {
                \XF::logException($e, false, 'Paylix subscription cancellation error: ');
            }
        }

        return $controller->redirect($controller->buildLink('account/upgrades'));
    }

    public function renderChangePaymentTemplate(PurchaseRequest $purchaseRequest): string
    {
        return $this->renderChangePaymentDefault($purchaseRequest);
    }

    public function processChangePayment(Controller $controller, PurchaseRequest $purchaseRequest, PaymentProfile $paymentProfile): AbstractReply
    {
        // Paylix doesn't support changing payment method directly
        return $controller->redirect($controller->buildLink('account/upgrades'));
    }

    protected function makeApiRequest($method, $endpoint, $apiKey, $data = null)
    {
        $url = $this->getApiEndpoint() . $endpoint;
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $options = [
            'headers' => $headers,
        ];

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH']))
        {
            $options['json'] = $data;
        }

        try
        {
            $response = $this->httpClient->request($method, $url, $options);
            $body = $response->getBody()->getContents();
            return json_decode($body, true);
        }
        catch (TransferException $e)
        {
            \XF::logException($e, false, 'Paylix API request failed: ');
            throw new \Exception('API request failed: ' . $e->getMessage());
        }
    }

    public function verifyCurrency(PaymentProfile $paymentProfile, $currencyCode)
    {
        // Paylix supports most major currencies
        $supportedCurrencies = [
            'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF', 'SEK', 'NOK', 'DKK',
            'PLN', 'CZK', 'HUF', 'RON', 'BGN', 'HRK', 'RUB', 'TRY', 'BRL', 'MXN',
            'ARS', 'CLP', 'COP', 'PEN', 'UYU', 'VND', 'THB', 'MYR', 'SGD', 'IDR',
            'PHP', 'INR', 'PKR', 'BDT', 'LKR', 'NPR', 'MMK', 'KHR', 'LAK', 'MNT'
        ];

        return in_array(strtoupper($currencyCode), $supportedCurrencies);
    }

    public function getCookieThirdParties(): array
    {
        return ['paylix.gg'];
    }
} 