<?php

namespace XF\Cli\Command\Development;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;
use XF\Util\File;

class GenerateFinders extends AbstractCommand
{
	use RequiresDevModeTrait;

	protected function configure()
	{
		$this
			->setName('xf-dev:generate-finders')
			->setDescription('Generates skeleton finders from entities')
			->addArgument(
				'addon',
				InputArgument::REQUIRED,
				'Add-on ID to generate Finder classes for.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$ds = \XF::$DS;
		$addOnId = $input->getArgument('addon');
		if ($addOnId === 'XF')
		{
			$searchDir = \XF::getSourceDirectory() . $ds . 'XF' . $ds . 'Entity';
		}
		else
		{
			$manager = \XF::app()->addOnManager();
			$addOn = $manager->getById($addOnId);
			if (!$addOn || !$addOn->isAvailable())
			{
				$output->writeln('Add-on could not be found.');
				return 1;
			}

			$addOnId = $addOn->getAddOnId();
			$searchDir = $manager->getAddOnPath($addOnId) . $ds . 'Entity';

			$addOnId = str_replace('/', '\\', $addOnId);
		}

		if (!file_exists($searchDir) || !is_dir($searchDir))
		{
			$output->writeln('<error>The selected add-on does not appear to have an Entity directory.</error>');
			return 1;
		}

		$iterator = new \RegexIterator(
			File::getRecursiveDirectoryIterator($searchDir, null, null),
			'/\.php$/'
		);

		/** @var \SplFileInfo $file */
		foreach ($iterator AS $name => $file)
		{
			$entityClass = str_replace($searchDir, '', $file->getRealPath());
			$entityClass = str_replace('.php', '', $entityClass);
			$entityClass = trim(str_replace(\XF::$DS, '\\', $entityClass), '\\');
			$entity = $addOnId . ':' . $entityClass;
			$fqEntityClass = sprintf('%s\Entity\%s', "\\$addOnId", $entityClass);
			$namespace = sprintf('%s\Finder', $addOnId);
			$finderClass = $entityClass . 'Finder';
			$fqFinderClass = sprintf('%s\Finder\%s', "\\$addOnId", $finderClass);

			if (!class_exists($fqEntityClass))
			{
				continue;
			}

			$reflection = new \ReflectionClass($fqEntityClass);
			if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait())
			{
				continue;
			}

			$docPlaceholder = $this->getDocPlaceholder();

			$parts = explode('\\', $namespace);
			if ($parts[0] === 'XF')
			{
				$path = \XF::getSourceDirectory() . \XF::$DS . implode(\XF::$DS, $parts);
			}
			else
			{
				$path = \XF::getAddOnDirectory() . \XF::$DS . implode(\XF::$DS, $parts);
			}

			$fileName = $path . \XF::$DS . $finderClass . '.php';
			$legacyFileName = $path . \XF::$DS . $entityClass . '.php';

			$entityShortName = substr($fqEntityClass, strrpos($fqEntityClass, '\\') + 1);
			$entityClassForImport = ltrim($fqEntityClass, '\\');

			// Skip add-ons not yet migrated to the Finder suffix: the suffixed
			// stub would shadow their real legacy finder via class aliasing.
			if ($entityClass !== $finderClass && is_file($legacyFileName))
			{
				continue;
			}

			if (!is_file($fileName))
			{
				// Generating new finder

				$importClasses = [
					'use XF\Mvc\Entity\Finder;',
					'use XF\Mvc\Entity\AbstractCollection;',
					"use $entityClassForImport;",
				];

				sort($importClasses);
				$importClasses = implode("\n", $importClasses);

				$fileOutput = <<< FINDEROUT
<?php

namespace $namespace;

$importClasses

/** <XF:DOC_COMMENT> */
class $finderClass extends Finder
{
}

FINDEROUT;
			}
			else
			{
				// Updating existing finder

				$finderReflection = new \ReflectionClass($fqFinderClass);

				if (!is_file($fileName))
				{
					continue;
				}

				$contents = file_get_contents($fileName);
				$existingComment = $finderReflection->getDocComment();

				$pattern = '/^use\s+\\\\?' . preg_quote($entityClassForImport, '/') . ';\s*\n/m';
				$contents = preg_replace($pattern, '', $contents);

				preg_match_all('/^use .+;$/m', $contents, $matches, PREG_OFFSET_CAPTURE);
				$firstUse = $matches[0][0];
				$insertPos = $firstUse[1];
				$contents = substr_replace(
					$contents,
					"use $entityClassForImport;\n",
					$insertPos,
					0
				);

				if (!$existingComment)
				{
					$search = 'class ' . $finderClass . ' extends ';
					$replace = "$docPlaceholder\n$search";
					$fileOutput = str_replace($search, $replace, $contents);
				}
				else
				{
					$fileOutput = str_replace($existingComment, $docPlaceholder, $contents);
				}
			}

			$newComment = <<< COMMENTOUT
/**
 * @method AbstractCollection<$entityShortName> fetch(?int \$limit = null, ?int \$offset = null)
 * @method AbstractCollection<$entityShortName> fetchDeferred(?int \$limit = null, ?int \$offset = null)
 * @method $entityShortName|null fetchOne(?int \$offset = null)
 * @extends Finder<$entityShortName>
 */
COMMENTOUT;
			$newComment = rtrim($newComment);

			$fileOutput = str_replace($docPlaceholder, $newComment, $fileOutput);
			$output->writeln("Writing Finder for entity $entity...");

			$finderDir = dirname($fileName);
			if (!file_exists($finderDir) && !File::createDirectory($finderDir, false))
			{
				$output->writeln("Could not create directory $finderDir. Check permissions.");
				return 5;
			}

			if (!is_writable($finderDir))
			{
				$output->writeln("File for $fileName could not be written to. Check directories exist and permissions.");
				return 5;
			}

			if (File::writeFile($fileName, $fileOutput, false))
			{
				$output->writeln("Written out Finder for entity $entity");
			}
			else
			{
				$output->writeln("Could not write out Finder for entity $entity");
			}

			$output->writeln("");
		}

		$output->writeln("Done!");
		return 0;
	}

	protected function getDocPlaceholder(): string
	{
		return '/** <XF:DOC_COMMENT> */';
	}
}
