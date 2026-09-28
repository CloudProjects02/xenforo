<?php

namespace XFES\Elasticsearch;

use GuzzleHttp\Psr7\Request;

class RequestException extends Exception
{
	/** @var Request */
	protected $request;

	/** @var Response */
	protected $response;

	public function setRequest(Request $request)
	{
		$this->request = $request;
	}

	public function getRequest()
	{
		return $this->request;
	}

	public function setResponse(Response $response)
	{
		$this->response = $response;
	}

	public function getResponse()
	{
		return $this->response;
	}
}
