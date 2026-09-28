<?php

namespace XFES\Elasticsearch;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;

use function array_key_exists, count, intval, is_array, is_string, strval;

class Api implements \ArrayAccess
{
	/**
	 * @var string
	 */
	public const ELASTICSEARCH_MINIMUM_VERSION = '7.2.0';

	/**
	 * @var Client
	 */
	protected $client;

	protected $config = [
		'host' => 'localhost',
		'port' => 9200,
		'username' => '',
		'password' => '',
		'https' => false,
		'verify' => true,
		'index' => '',
		'singleType' => true,
	];

	protected $exists = null;

	/**
	 * @var string|null
	 */
	protected $distribution = null;

	/**
	 * @var string|null
	 */
	protected $distributionVersion = null;

	protected $version = null;

	/**
	 * @deprecated Elasticsearch 7+ is required
	 */
	protected $isSingleType = true;

	/**
	 * @deprecated Elasticsearch 7+ is required
	 */
	protected $singleTypeName = 'xf';

	public function __construct(array $config)
	{
		$this->config = array_replace($this->config, $config);

		if (!$this->config['index'])
		{
			$xfConfig = \XF::app()->config();
			if (isset($xfConfig['db']['dbname']))
			{
				$this->config['index'] = strtolower($xfConfig['db']['dbname']);
			}
		}

		$clientOptions = [
			'base_uri' => $this->getBaseUrl(),
			'connect_timeout' => 3,
			'timeout' => 20,
			'http_errors' => false,
		];

		if ($this->config['verify'] !== true)
		{
			$clientOptions['verify'] = $this->config['verify'];
		}

		$this->client = \XF::app()->http()->createClient($clientOptions);
	}

	public function test(&$error = null)
	{
		try
		{
			$result = $this->request('get', '/')->getBody();
		}
		catch (Exception $e)
		{
			$error = \XF::phrase('xfes_connection_could_not_be_made_to_elasticsearch_server');
			return false;
		}

		if (empty($result['version']['number']))
		{
			$error = \XF::phrase('xfes_does_not_appear_to_be_elasticsearch_server');
			return false;
		}

		$distribution = $result['version']['distribution'] ?? 'elasticsearch';
		$version = $distribution === 'opensearch'
			? '7.10.2'
			: $result['version']['number'];

		if (!version_compare($version, static::ELASTICSEARCH_MINIMUM_VERSION, '>='))
		{
			$error = \XF::phrase('xfes_elasticsearch_version_does_not_meet_requirements', [
				'required' => static::ELASTICSEARCH_MINIMUM_VERSION,
				'version' => $result['version']['number'],
			]);
			return false;
		}

		return true;
	}

	public function distribution(): string
	{
		if ($this->distribution === null)
		{
			$this->version();
		}

		return $this->distribution;
	}

	public function distributionVersion(): string
	{
		if ($this->distributionVersion === null)
		{
			$this->version();
		}

		return $this->distributionVersion;
	}

	public function version()
	{
		if ($this->version === null)
		{
			$result = $this->request('get', '/')->getBody();
			if (!$result || empty($result['version']['number']))
			{
				$this->distribution = 'Unknown';
				$this->distributionVersion = '0';
				$this->version = '0';
				throw new RequestException("Could not find version number in request");
			}

			$this->distribution = $result['version']['distribution']
				?? 'elasticsearch';
			$this->distributionVersion = $result['version']['number'];
			$this->version = $this->distribution === 'opensearch'
				? '7.10.2'
				: $result['version']['number'];
		}

		return $this->version;
	}

	public function majorVersion()
	{
		return intval($this->version());
	}

	/**
	 * Returns true if the index only supports a single type.
	 *
	 * @return bool
	 *
	 * @deprecated Elasticsearch 7+ is required
	 */
	public function isSingleTypeIndex()
	{
		return true;
	}

	/**
	 * @deprecated Elasticsearch 7+ is required
	 */
	public function isTypelessIndex()
	{
		return true;
	}

	/**
	 * @deprecated Elasticsearch 7+ is required
	 */
	public function getSingleTypeName()
	{
		return '_doc';
	}

	/**
	 * @deprecated Elasticsearch 7+ is required
	 */
	public function forceSingleType($force = true)
	{
	}

	public function index($type, $id, array $data)
	{
		$this->addTypeToDataForSingleTypeIndex($type, $data);

		return $this->requestById('put', $type, $id, $data)->getBody();
	}

	public function indexBulk(array $records)
	{
		if (!$records)
		{
			return [];
		}

		$items = [];

		foreach ($records AS $record)
		{
			$bulkResult = $this->getBulkActionEntry('index', $record['type'], $record['id'], $record['data']);
			if ($bulkResult)
			{
				$items[] = $bulkResult;
			}
		}

		if (!$items)
		{
			return [];
		}

		$query = implode("\n", $items) . "\n";

		$body = $this->bulkRequest($query)->getBody();

		return $body;
	}

	public function delete($type, $id)
	{
		try
		{
			return $this->requestById('delete', $type, $id)->getBody();
		}
		catch (RequestException $e)
		{
			$response = $e->getResponse();
			if ($response && $response->getCode() == 404)
			{
				// deleting something that has already been deleted shouldn't error
				return $response->getBody();
			}

			throw $e;
		}
	}

	public function deleteBulk($type, array $ids)
	{
		if (!$ids)
		{
			return [];
		}

		$deletes = [];

		foreach ($ids AS $id)
		{
			$bulkResult = $this->getBulkActionEntry('delete', $type, $id);
			if ($bulkResult)
			{
				$deletes[] = $bulkResult;
			}
		}

		if (!$deletes)
		{
			return [];
		}

		$query = implode("\n", $deletes) . "\n";

		return $this->bulkRequest($query)->getBody();
	}

	public function search(array $dsl)
	{
		return $this->requestFromIndex('get', '_search', $dsl)->getBody();
	}

	public function indexExists()
	{
		if ($this->exists !== null)
		{
			return $this->exists;
		}

		try
		{
			$response = $this->request('head', $this->config['index']);
			$this->exists = ($response->getCode() == 200);
		}
		catch (RequestException $e)
		{
			// probably a "no such index" error
			$this->exists = false;
		}

		return $this->exists;
	}

	public function getIndexInfo()
	{
		$name = $this->config['index'];

		$body = $this->request('get', $name)->getBody();
		return $body[$name] ?? [];
	}

	public function getIndexStats()
	{
		$name = $this->config['index'];

		$body = $this->requestFromIndex('get', "_stats")->getBody();
		return $body['indices'][$name]['primaries'] ?? [];
	}

	public function deleteIndex()
	{
		$this->exists = null;

		return $this->request('delete', $this->config['index'])->getBody();
	}

	public function createIndex(array $config = [])
	{
		unset(
			$config['settings']['index']['creation_date'],
			$config['settings']['index']['uuid'],
			$config['settings']['index']['version'],
			$config['settings']['index']['provided_name'],
			$config['settings']['index']['blocks']
		);

		if (empty($config['settings']['index']))
		{
			unset($config['settings']['index']);
		}

		foreach ($config AS $k => $value)
		{
			if (is_array($value) && !$value)
			{
				unset($config[$k]);
			}
		}

		$this->exists = null;

		$body = $this->request('put', $this->config['index'], $config)->getBody();

		return $body;
	}

	public function getClusterSettings(bool $includeDefaults = true)
	{
		return $this->requestFromRoot('get', '_cluster/settings' . ($includeDefaults ? '?include_defaults=true' : ''))->getBody();
	}

	public function updateSettings(array $settings)
	{
		return $this->requestFromIndex('put', '_settings', $settings)->getBody();
	}

	public function closeIndex()
	{
		return $this->requestFromIndex('post', "_close")->getBody();
	}

	public function openIndex()
	{
		return $this->requestFromIndex('post', "_open")->getBody();
	}

	public function requestFromIndex($method, $path, $data = null)
	{
		$path = ltrim($path, '/');
		$index = $this->config['index'];

		return $this->request($method, "{$index}/{$path}", $data);
	}

	public function requestFromRoot($method, $path, $data = null)
	{
		$path = ltrim($path, '/');

		return $this->request($method, $path, $data);
	}

	public function requestById($method, $type, $id, $data = null)
	{
		return $this->requestFromIndex($method, "_doc/{$type}-{$id}", $data);
	}

	public function request($method, $path, $data = null, array $options = [], &$request = null)
	{
		$headers = [];

		if ($data)
		{
			$headers['Content-Type'] = !empty($options['contentType']) ? $options['contentType'] : 'application/json';

			if (!is_string($data))
			{
				$data = json_encode($data, JSON_PRETTY_PRINT);
				if ($data === false)
				{
					throw new DataException("Failed to encode body for Elasticsearch request $method $path: " . json_last_error_msg());
				}
			}
		}

		$request = new Request($method, $path, $headers, $data);

		try
		{
			$response = $this->client->send($request);
			$body = $response->getBody();

			if ($body)
			{
				$contents = @json_decode($body->getContents(), true);
				$result = is_array($contents) ? $contents : [];
			}
			else
			{
				$result = null;
			}

			$esResponse = new Response($response->getStatusCode(), $result);
		}
		catch (\GuzzleHttp\Exception\ConnectException $e)
		{
			$message = $e->getMessage();

			$config = $this->config;
			$username = $config['username'] ?? null;
			$password = $config['password'] ?? null;

			// hide credentials in error message
			if ($username && $password
				&& strpos($message, 'http') !== false
				&& strpos($message, $username . ':' . $password . '@') !== false
			)
			{
				$message = str_replace($username . ':' . $password . '@', $username . ':*********@', $message);
			}

			throw new ConnectException($message, $e->getCode(), $e);
		}

		$responseCode = $esResponse->getCode();
		if ($responseCode >= 400 && $responseCode <= 599)
		{
			$body = $esResponse->getBody();
			$error = null;

			if ($body)
			{
				$error = $this->getErrorMessage($body);
			}

			if (!$error)
			{
				$error = "Unknown error (HTTP code: $responseCode)";
			}

			$e = new RequestException($error, $responseCode);
			$e->setRequest($request);
			$e->setResponse($esResponse);

			throw $e;
		}

		return $esResponse;
	}

	public function bulkRequest($data)
	{
		$options = [
			'contentType' => 'application/x-ndjson',
		];

		$esResponse = $this->request('post', '_bulk', $data, $options, $request);
		$body = $esResponse->getBody();

		if ($this->hasBulkActionErrors($body, $errors))
		{
			$e = new BulkRequestException($errors);
			$e->setRequest($request);
			$e->setResponse($esResponse);

			throw $e;
		}

		return $esResponse;
	}

	protected function getBulkActionEntry($action, $type, $id, ?array $source = null)
	{
		$index = $this->config['index'];

		$actionLine = [
			$action => [
				'_index' => $index,
				'_id' => "{$type}-{$id}",
			],
		];

		$bulk = json_encode($actionLine);

		if (is_array($source))
		{
			if ($action == 'index' || $action == 'create')
			{
				$this->addTypeToDataForSingleTypeIndex($type, $source);
			}

			$sourceData = json_encode($source);
			if ($sourceData === false)
			{
				$jsonError = json_last_error_msg();
				\XF::logError("Failed to JSON encode data ($jsonError) for bulk Elasticsearch action ($action) against ($type:$id). Ignored.");
				return null;
			}

			$bulk .= "\n" . $sourceData;
		}

		return $bulk;
	}

	protected function addTypeToDataForSingleTypeIndex($type, array &$data)
	{
		$data['type'] = $type;
	}

	protected function getErrorMessage(array $body)
	{
		if (isset($body['error']['failed_shards'][0]['reason']['caused_by']['reason']))
		{
			$shardErrorReason = $body['error']['failed_shards'][0]['reason'];
			$causedBy = $shardErrorReason['caused_by'];
			return sprintf(
				"%s, %s: %s",
				strval($shardErrorReason['type'] ?? ''),
				strval($causedBy['type'] ?? ''),
				strval($causedBy['reason'])
			);
		}

		if (isset($body['error']['root_cause'][0]['reason']))
		{
			return strval($body['error']['root_cause'][0]['reason']);
		}

		if (isset($body['error']['reason']))
		{
			return strval($body['error']['reason']);
		}

		return null;
	}

	protected function hasBulkActionErrors(array $body, &$errors = null)
	{
		$errors = [];

		if (empty($body['errors']) || empty($body['items']))
		{
			return false;
		}

		foreach ($body['items'] AS $itemContainer)
		{
			$item = reset($itemContainer);
			$action = key($itemContainer);

			$status = $item['status'];
			if ($status >= 400 && $status <= 599)
			{
				if ($action == 'delete' && $status == 404)
				{
					// don't treat a 404 for removal as an error since it's
					continue;
				}

				$uniqueId = $item['_id'];
				$errors[$uniqueId] = $this->getErrorMessage($item);
			}
		}

		return (count($errors) > 0);
	}

	protected function getBaseUrl()
	{
		$config = $this->config;

		$protocol = $config['https'] ? 'https' : 'http';
		$host = $config['host'] ?: 'localhost';
		$port = $config['port'] ?: 9200;
		$username = $config['username'];

		$uri = new Uri("{$protocol}://{$host}:{$port}");

		if ($username)
		{
			$uri = $uri->withUserInfo($username, $config['password']);
		}

		return $uri;
	}

	public function getPrintableBaseUrl()
	{
		$config = $this->config;

		$protocol = $config['https'] ? 'https' : 'http';
		$host = $config['host'];
		$port = $config['port'];
		$username = $config['username'];

		if ($username)
		{
			return "{$protocol}://{$username}:********@{$host}:{$port}";
		}
		else
		{
			return "{$protocol}://{$host}:{$port}";
		}
	}

	public function getTypeAndIdFromHit(array $hit)
	{
		$typeAndId = explode('-', $hit['_id'], 2);

		// make sure the ID is an int
		$typeAndId[1] = intval($typeAndId[1]);

		return $typeAndId;
	}

	/**
	 * @deprecated Elasticsearch 7+ is required
	 */
	protected function isSingleTypeResult($resultType)
	{
		return true;
	}

	public function getConfig()
	{
		return $this->config;
	}

	#[\ReturnTypeWillChange]
	public function offsetGet($offset)
	{
		switch ($offset)
		{
			case 'printableBaseUrl': return $this->getPrintableBaseUrl();
			case 'config': return $this->config;

			default:
				if (array_key_exists($offset, $this->config))
				{
					return $this->config[$offset];
				}
				else
				{
					return null;
				}
		}
	}

	public function offsetExists($offset): bool
	{
		switch ($offset)
		{
			case 'printableBaseUrl': return true;
			case 'config': return true;
			default: return array_key_exists($offset, $this->config);
		}
	}

	public function offsetSet($offset, $value): void
	{
		throw new \LogicException("Cannot update Elasticsearch offsets");
	}

	public function offsetUnset($offset): void
	{
		throw new \LogicException("Cannot update Elasticsearch offsets");
	}
}
