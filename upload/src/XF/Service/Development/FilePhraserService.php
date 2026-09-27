<?php

namespace XF\Service\Development;

use XF\App;
use XF\Entity\AddOn;
use XF\Entity\Phrase;
use XF\Service\AbstractService;
use XF\Util\File;

use function is_int, is_string, strlen;

class FilePhraserService extends AbstractService
{
	protected $addOn;

	protected $phrasePrefix = '';

	public function __construct(App $app, AddOn $addOn)
	{
		parent::__construct($app);

		$this->addOn = $addOn;
	}

	public function setPhrasePrefix(string $prefix): void
	{
		$this->phrasePrefix = $prefix;
	}

	protected function getAddOnFileRoot(): string
	{
		if ($this->addOn->addon_id == 'XF')
		{
			return \XF::getSourceDirectory() . '/XF';
		}
		else
		{
			return \XF::getAddOnDirectory() . '/' . $this->addOn->addon_id;
		}
	}

	public function getPrintableFileName(string $file): string
	{
		$root = $this->getAddOnFileRoot();
		$fullFile = $root . '/' . $file;

		$realRoot = realpath(\XF::getRootDirectory());
		$realFile = realpath($fullFile);
		if ($realRoot === false || $realFile === false)
		{
			return str_replace('\\', '/', $fullFile);
		}

		$realRoot = str_replace('\\', '/', $realRoot);
		$fullFile = str_replace('\\', '/', $realFile);

		if (strpos($fullFile, $realRoot) === 0)
		{
			return ltrim(substr($fullFile, strlen($realRoot)), '/');
		}

		return $fullFile;
	}

	public function getPhrasableFileList(): array
	{
		$root = $this->getAddOnFileRoot();

		$printRoot = str_replace(\XF::getRootDirectory(), '', $root);
		$printRoot = str_replace('\\', '/', $printRoot);
		$printRoot = trim($printRoot, '/') . '/';

		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
		$files = [];
		while ($iterator->valid())
		{
			if ($iterator->isFile() && substr($iterator->getFilename(), -4) == '.php')
			{
				$contents = file_get_contents($iterator->key());
				if ($contents !== false && preg_match('/("|\')~~[^\n]+~~\\1/', $contents))
				{
					$subName = str_replace('\\', '/', $iterator->getSubPathName());
					$files[$subName] = $printRoot . $subName;
				}
			}

			$iterator->next();
		}

		uksort($files, 'strnatcasecmp');

		return $files;
	}

	public function isProcessableFile(
		string $file,
		?string &$error = null,
		?string &$finalPath = null,
		bool $requireWritable = true
	): bool
	{
		$root = $this->getAddOnFileRoot();
		$fullFile = $root . '/' . $file;

		if (!file_exists($fullFile))
		{
			$error = "File '$fullFile' cannot be found";
			return false;
		}

		if (!is_readable($fullFile))
		{
			$error = "File '$fullFile' is not readable.";
			return false;
		}

		$realRoot = realpath($root);
		if ($realRoot === false)
		{
			$error = "Unable to resolve real path for root '$root'.";
			return false;
		}

		$realFile = realpath($fullFile);
		if ($realFile === false)
		{
			$error = "Unable to resolve real path for file '$fullFile'.";
			return false;
		}

		$fullFile = $realFile;
		$realRootWithSep = rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$fullFileWithSep = rtrim($fullFile, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		if (strpos($fullFileWithSep, $realRootWithSep) !== 0)
		{
			$error = "File '$fullFile' must be within the add-on's root";
			return false;
		}

		if (substr($fullFile, -4) != '.php')
		{
			$error = "File '$fullFile' must be a PHP file";
			return false;
		}

		if ($requireWritable && !is_writable($fullFile))
		{
			$error = "File '$fullFile' is not writable.";
			return false;
		}

		$finalPath = $fullFile;

		return true;
	}

	public function analyzeFile(string $file): array
	{
		if (!$this->isProcessableFile($file, $error, $fullFile, false))
		{
			throw new \InvalidArgumentException($error);
		}

		$contents = file_get_contents($fullFile);
		if (!is_string($contents))
		{
			throw new \RuntimeException("Failed reading file '{$fullFile}'.");
		}

		return $this->analyzeString($contents);
	}

	public function analyzeString(string $contents): array
	{
		$replacements = [];

		/** @var PhraserService $phraser */
		$phraser = $this->service(PhraserService::class);

		preg_match_all('/("|\')~~([^\n]+?)~~\\1/', $contents, $matches, PREG_SET_ORDER);
		foreach ($matches AS $rawMatch)
		{
			if (isset($replacements[$rawMatch[0]]))
			{
				continue;
			}

			$text = trim($rawMatch[2]);

			$vars = [];
			preg_match_all(
				'/' . $rawMatch[1] . '\s+\.\s+(.+?)\s\.\s+' . $rawMatch[1] . '/',
				$text,
				$varMatches,
				PREG_SET_ORDER
			);
			foreach ($varMatches AS $varMatch)
			{
				$code = $varMatch[1];
				$code = preg_replace('/^htmlspecialchars\((.+)\)$/i', '\\1', $code);

				$var = preg_replace('/^[^(]+\(.*\$([^,]+).*\)$/', '$1', $code);
				$var = preg_replace('/[^a-z0-9_]/i', '', $var);

				$vars[$var] = $code;
				$text = str_replace($varMatch[0], '{' . $var . '}', $text);
			}

			preg_match_all('/\{?(\$([a-z0-9[\]_>-]+))\}?/i', $text, $varMatches, PREG_SET_ORDER);
			foreach ($varMatches AS $varMatch)
			{
				if (preg_match('/\[(\'|"|)([^\'"\]]+)\\1\]$/', $varMatch[2], $innerMatch))
				{
					$var = $innerMatch[2];
				}
				else if (preg_match('/->([a-z0-9_]+)$/i', $varMatch[2], $innerMatch))
				{
					$var = $innerMatch[1];
				}
				else
				{
					$var = $varMatch[2];
				}

				$var = preg_replace('/[^a-z0-9_]/i', '', $var);

				if (!isset($vars[$var]))
				{
					$vars[$var] = $varMatch[1];
					$text = str_replace($varMatch[0], '{' . $var . '}', $text);
				}
			}

			$text = str_replace('\\' . $rawMatch[1], $rawMatch[1], $text);
			$text = str_replace('\\\\', '\\', $text);

			$name = $phraser->getPhraseNameFromText($text, $this->phrasePrefix);

			$info = $phraser->adjustForExistingPhrase($name, $text, $vars, $this->addOn->addon_id);
			$name = $info['phraseName'];
			$text = $info['phraseText'];
			$vars = $info['vars'];
			$exists = $info['exists'];

			if ($vars)
			{
				$arrayParts = [];
				foreach ($vars AS $phraseVar => $varCode)
				{
					$varCode = preg_replace('/\[([a-z0-9_]+)\]/i', "['\\1']", $varCode);
					$arrayParts[] = "'$phraseVar' => $varCode";
				}

				$code = '\XF::phrase(\'' . $name . '\', [' . implode(', ', $arrayParts) . '])';
			}
			else
			{
				$code = '\XF::phrase(\'' . $name . '\')';
			}

				$replacements[$rawMatch[0]] = [
					'original' => $rawMatch[0],
					'name' => $name,
					'text' => $text,
					'code' => $code,
					'existingName' => ($exists ? $name : ''),
				];
		}

		return $replacements;
	}

	public function applyFileChanges(string $file, array $replacements): bool
	{
		if (!$this->isProcessableFile($file, $error, $fullFile))
		{
			throw new \InvalidArgumentException($error);
		}

		$originalContents = file_get_contents($fullFile);
		if (!is_string($originalContents))
		{
			throw new \RuntimeException("Failed reading file '{$fullFile}'.");
		}
		$originalPerms = $this->getFilePermissions($fullFile);

		$contents = $this->applyStringChanges($originalContents, $replacements);
		$this->replaceFileContents($fullFile, $contents, $originalPerms);

		try
		{
			$this->persistPhraseChanges($replacements);
		}
		catch (\Throwable $e)
		{
			try
			{
				$this->replaceFileContents($fullFile, $originalContents, $originalPerms);
			}
			catch (\Throwable $restoreException)
			{
				throw new \RuntimeException(
					"Failed persisting phrase changes and failed restoring original file '{$fullFile}': "
					. $restoreException->getMessage(),
					0,
					$e
				);
			}

			throw $e;
		}

		return true;
	}

	public function applyStringChanges(string $contents, array $replacements): string
	{
		foreach ($replacements AS $replacement)
		{
			$contents = str_replace($replacement['original'], $replacement['code'], $contents);
		}

		return $contents;
	}

	protected function persistPhraseChanges(array $replacements): void
	{
		$this->db()->beginTransaction();

		try
		{
			$createdNames = [];

			foreach ($replacements AS $replacement)
			{
				if ($replacement['existingName'] === $replacement['name'])
				{
					continue;
				}

				$name = $replacement['name'];
				if (isset($createdNames[$name]))
				{
					continue;
				}

				$existing = $this->em()->findOne(Phrase::class, [
					'title' => $name,
					'language_id' => 0,
					'addon_id' => $this->addOn->addon_id,
				]);
				if ($existing)
				{
					$createdNames[$name] = true;
					continue;
				}

				$phrase = $this->em()->create(Phrase::class);
				$phrase->title = $name;
				$phrase->phrase_text = $replacement['text'];
				$phrase->language_id = 0;
				$phrase->addon_id = $this->addOn->addon_id;
				$phrase->save(true, false);

				$createdNames[$name] = true;
			}

			$this->db()->commit();
		}
		catch (\Throwable $e)
		{
			$this->db()->rollback();
			throw $e;
		}
	}

	protected function replaceFileContents(string $fullFile, string $contents, ?int $permissions = null): void
	{
		$tempFile = tempnam(dirname($fullFile), basename($fullFile) . '.tmp.');
		if ($tempFile === false)
		{
			throw new \RuntimeException("Failed creating temporary file for '{$fullFile}'.");
		}

		$bytesWritten = file_put_contents($tempFile, $contents);
		if ($bytesWritten === false)
		{
			@unlink($tempFile);
			throw new \RuntimeException("Failed writing updated contents to temporary file '{$tempFile}'.");
		}

		if ($permissions !== null)
		{
			@chmod($tempFile, $permissions);
		}

		if (!@rename($tempFile, $fullFile))
		{
			@unlink($tempFile);
			throw new \RuntimeException("Failed replacing '{$fullFile}' with updated content.");
		}

		if ($permissions !== null)
		{
			@chmod($fullFile, $permissions);
		}
		else
		{
			File::makeWritableByFtpUser($fullFile);
		}
	}

	protected function getFilePermissions(string $fullFile): ?int
	{
		$perms = @fileperms($fullFile);
		if (!is_int($perms))
		{
			return null;
		}

		return ($perms & 0777);
	}
}
