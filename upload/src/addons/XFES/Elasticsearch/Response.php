<?php

namespace XFES\Elasticsearch;

class Response
{
	protected $code;
	protected $body;

	public function __construct($code, ?array $body = null)
	{
		$this->code = $code;
		$this->body = $body;
	}

	public function getCode()
	{
		return $this->code;
	}

	public function getBody()
	{
		return $this->body;
	}
}
