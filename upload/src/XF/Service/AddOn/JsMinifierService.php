<?php

namespace XF\Service\AddOn;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Utils;
use XF\App;
use XF\Service\AbstractService;
use XF\Util\File;

class JsMinifierService extends AbstractService
{
	protected $jsPath;
	protected $minPath;

	protected $options;

	/**
	 * @var Client
	 */
	protected $client;

	// @phpstan-ignore-next-line
	public function __construct(App $app, $jsPath, $minPath = null, array $compilerOptions = [])
	{
		parent::__construct($app);

		$this->jsPath = $jsPath;
		if ($minPath !== null)
		{
			$this->minPath = $minPath;
		}
		else
		{
			$this->minPath = preg_replace('(\.js$)', '.min.js', $jsPath, 1);
		}

		$this->setHttpClient();
	}

	/**
	 * Set compiler options passed into the Compiler Service API.
	 * Only used if $config['development']['closureCompilerPath'] is not set.
	 *
	 * @param array $options
	 *
	 * @deprecated No longer used.
	 */
	protected function setCompilerOptions(array $options = [])
	{
		$this->options = array_replace([
			'js_code' => file_get_contents($this->jsPath),
			'compilation_level' => 'SIMPLE_OPTIMIZATIONS',
			'output_info' => 'compiled_code',
			'output_format' => 'json',
		], $options);
	}

	/**
	 * Setup HTTP client to communicate with the Compiler Service API.
	 * Only used if $config['development']['closureCompilerPath'] is not set.
	 */
	protected function setHttpClient()
	{
		$this->client = $this->app->http()->client();
	}

	/**
	 * Minify JS code using either the Compiler Service API or local closure
	 * compiler JAR if $config['development']['closureCompilerPath'] is set.
	 *
	 * @return null|string
	 * @throws \ErrorException
	 */
	public function minify()
	{
		$compilerPath = \XF::config('development')['closureCompilerPath'];

		if ($compilerPath !== null)
		{
			$result = shell_exec("java -jar " . escapeshellarg($compilerPath) . " --js " . escapeshellarg($this->jsPath) . " --rewrite_polyfills false --warning_level QUIET");

			if ($result === false || trim($result) === '')
			{
				throw new \ErrorException('Empty result or error provided by the compiler.');
			}
		}
		else
		{
			$result = $this->request();

			if (isset($result['serverErrors']))
			{
				$this->processErrors($result['serverErrors'], 'Server errors encountered while compiling: ');
			}
			else if (!isset($result['compiledCode']) || trim($result['compiledCode']) === '')
			{
				throw new \ErrorException('Empty result provided by the compiler.');
			}

			$result = $result['compiledCode'];
		}

		File::writeFile($this->minPath, trim($result), false);

		return $result;
	}

	protected function request($getErrors = false)
	{
		$client = $this->client;

		try
		{
			$response = $client->post(\XF::XF_API_URL . 'closure-compiler.json', [
				'http_errors' => false,
				'headers' => [
					'XF-LICENSE-API-KEY' => \XF::XF_LICENSE_KEY,
				],
				'body' => file_get_contents($this->jsPath),
			]);
			$contents = $response->getBody()->getContents();

			if (empty($contents))
			{
				return null;
			}

			return Utils::jsonDecode($contents, true);
		}
		catch (TransferException $e)
		{
			return null;
		}
	}

	protected function processErrors(array $errors, $errorPrefix = '')
	{
		$output = [];
		foreach ($errors AS $error)
		{
			$output[] = $error['error'] ?? $error;
		}
		throw new \ErrorException(($errorPrefix ? $errorPrefix . ' ' : '') . implode(', ', $output));
	}
}
