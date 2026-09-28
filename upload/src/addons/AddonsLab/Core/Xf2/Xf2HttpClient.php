<?php

namespace AddonsLab\Core\Xf2;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class Xf2HttpClient
{
    public function __construct()
    {

    }

    protected $data;

    protected $remoteUrl;

    protected $timeout = 5;

    protected $client;

    protected $adapter_options;

    protected $no_body = true;

    protected $method = 'GET';

    protected $effective_url;

    public function process()
    {
        $client = $this->getClient();

        return $this->sendRequest($client);
    }

    /**
     * @param $client Client
     * @return ResponseInterface
     */
    public function sendRequest($client)
    {
        $response = $client->request($this->method, $this->remoteUrl, [
            'form_params'=>$this->data
        ]);

        $this->effective_url = $response->getHeader(\GuzzleHttp\RedirectMiddleware::HISTORY_HEADER);

        return $response;
    }

    public function setAdapterOption($optionName, $optionValue, $isCurlClientOption = false)
    {
        $adapterOptions = $this->getAdapterOptions();

        if ($isCurlClientOption)
        {
            $adapterOptions['curloptions'][$optionName] = $optionValue;
        }
        else
        {
            $adapterOptions[$optionName] = $optionValue;
        }

        $this->setAdapterOptions($adapterOptions);
    }

    public function removeAdapterOption($optionName, $isCurlClientOption = false)
    {
        $adapterOptions = $this->getAdapterOptions();

        if ($isCurlClientOption)
        {
            unset($adapterOptions['curloptions'][$optionName]);
        }
        else
        {
            unset($adapterOptions[$optionName]);
        }
        $this->setAdapterOptions($adapterOptions);
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        if ($this->client === null)
        {
            $this->client = \XF::app()->http()->createClient(['allow_redirects' => ['track_redirects' => true]]);
        }

        return $this->client;
    }


    public function getAdapterOptions()
    {
        if (is_null($this->adapter_options))
        {
            $this->adapter_options = array();
        }

        return $this->adapter_options;
    }

    public function getEffectiveUrl()
    {
        return $this->effective_url;
    }

    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    public function setNoBody($no_body)
    {
        $this->no_body = $no_body;

        $this->setAdapterOption(CURLOPT_NOBODY, $no_body, true);
    }

    public function getTimeout()
    {
        return $this->timeout;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function getRemoteUrl()
    {
        return $this->remoteUrl;
    }

    public function setRemoteUrl($remoteUrl)
    {
        // to reset the client to the correct URL
        $this->client = null;
        $this->remoteUrl = $remoteUrl;
    }

    public function setClient($client)
    {
        $this->client = $client;
    }

    public function setAdapterOptions($adapter_options)
    {
        $this->client = null;
        $this->adapter_options = $adapter_options;
    }
}