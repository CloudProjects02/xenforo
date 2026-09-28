<?php

namespace AddonsLab\Core\Service;

class Xf1HttpClient
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

	public function process()
	{
		$client = $this->getClient();

		$response = $this->sendRequest($client);

		return $response;
	}

	/**
	 * @param $client \Zend_Http_Client
	 * @return \Zend_Http_Response
	 */
	public function sendRequest($client)
	{
		return $client->request($this->method);
	}

	public function setAdapterOption($optionName, $optionValue, $isCurlClientOption = false)
	{
		$adapterOptions = $this->getAdapterOptions();

		if ($isCurlClientOption) {
			$adapterOptions['curloptions'][$optionName] = $optionValue;
		} else {
			$adapterOptions[$optionName] = $optionValue;
		}

		$this->setAdapterOptions($adapterOptions);
	}

	public function removeAdapterOption($optionName, $isCurlClientOption = false)
	{
		$adapterOptions = $this->getAdapterOptions();

		if ($isCurlClientOption) {
			unset($adapterOptions['curloptions'][$optionName]);
		} else {
			unset($adapterOptions[$optionName]);
		}
		$this->setAdapterOptions($adapterOptions);
	}

	/**
	 * @return \Zend_Http_Client
	 */
	public function getClient()
	{
		if ($this->client === null) {
			$this->client = \XenForo_Helper_Http::getClient(
				$this->getRemoteUrl(),
				$this->getAdapterOptions()
			);
			$this->client->setParameterPost($this->getData());
		}

		return $this->client;
	}


	public function getAdapterOptions()
	{
		if (is_null($this->adapter_options)) {
			$this->adapter_options = array(
				'httpversion' => \Zend_Http_Client::HTTP_0,
				'timeout' => $this->timeout, // will convert to CURLOPT_CONNECTTIMEOUT
				'adapter' => 'Zend_Http_Client_Adapter_Curl',
				'maxredirects' => 0,
				'curloptions' => array(
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_FOLLOWLOCATION => false,
					CURLOPT_TIMEOUT => $this->timeout + 2, // we basically allow only 2 additional seconds for getting the data itself
					CURLOPT_NOBODY => $this->no_body, // we do not need the content of pages 
				)
			);
		}

		return $this->adapter_options;
	}
	
	public function getEffectiveUrl()
    {
        $handle=$this->getClient()->getAdapter()->getHandle();
        if($handle) {
            return curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
        }
        
        return false;
    }

	public function setTimeout($timeout)
	{
		$this->timeout = $timeout;

		$this->setAdapterOption('timeout', $this->timeout);
		$this->setAdapterOption(CURLOPT_TIMEOUT, $this->timeout + 2);
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
	    $this->client=null;
		$this->adapter_options = $adapter_options;
	}
}