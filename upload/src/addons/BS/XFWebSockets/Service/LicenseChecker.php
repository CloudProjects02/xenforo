<?php

namespace BS\XFWebSockets\Service;

use GuzzleHttp\Client;
use XF\Service\AbstractService;

class LicenseChecker extends AbstractService
{
    private const API_URL = 'https://devsell.io/api/resource-licenses/check-permission';

    protected Client $client;

    public function __construct(\XF\App $app)
    {
        parent::__construct($app);
        $this->client = $app->http()->client();
    }

    public function isAllowedAction(string $resourceId, string $action, array $data = []): bool
    {
        $response = $this->client->request(
            'GET',
            self::API_URL,
            [
                'query' => [
                    'domain' => $this->getDomainFromBoardUrl(),
                    'action' => $action,
                    'action_data' => $data,
                    'resource_id' => $resourceId,
                ],
                'headers' => [
                    'XF-Api-Key' => 'VXzE9cVHn8KAucP9-w5Nhqxc3UirefVS',
                ],
            ]
        );

        $response = $response->getBody()->getContents();
        $response = @json_decode($response, true);

        return (bool)($response['allowed'] ?? false);
    }

    protected function getDomainFromBoardUrl(): string
    {
        $boardUrl = $this->app->options()->boardUrl;
        $boardUrl = parse_url($boardUrl);
        return $boardUrl['host'];
    }
}