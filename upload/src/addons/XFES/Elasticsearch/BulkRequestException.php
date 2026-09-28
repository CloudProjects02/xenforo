<?php

namespace XFES\Elasticsearch;

use function count, is_array, is_string;

class BulkRequestException extends RequestException
{
	protected $bulkErrors = [];

	public function __construct($errors, $code = 0, ?\Exception $previous = null)
	{
		if (is_string($errors))
		{
			$errors = [$errors];
		}
		$this->bulkErrors = $errors;

		$firstError = 'Unknown error';
		if (is_array($errors) && count($errors) > 0)
		{
			$key = key($errors);
			$firstError = "[$key] " . reset($errors);
		}

		parent::__construct("Elasticsearch bulk action error (first error: $firstError)", $code, $previous);
	}

	public function getBulkErrors()
	{
		return $this->bulkErrors;
	}
}
