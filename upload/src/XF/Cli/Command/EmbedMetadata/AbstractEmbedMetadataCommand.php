<?php

namespace XF\Cli\Command\EmbedMetadata;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;
use XF\Cli\Command\JobRunnerTrait;

use function count;

abstract class AbstractEmbedMetadataCommand extends AbstractCommand
{
	use JobRunnerTrait;

	/**
	 * Name of the command suffix (do not include the command namespace)
	 *
	 * @return string
	 */
	abstract protected function getCommandName();

	abstract protected function getCommandDescription();

	abstract protected function getJobClass();

	protected function getCommandAliases()
	{
		return [];
	}

	protected function getSupportedTypes()
	{
		return ['quotes', 'attachments', 'embeds', 'images', 'unfurls'];
	}

	protected function configureOptions()
	{
		return;
	}

	protected function configure()
	{
		$supportedTypes = $this->getSupportedTypes();

		$this
			->setName('xf-embed-metadata:' . $this->getCommandName())
			->setDescription($this->getCommandDescription())
			->addOption(
				'log-queries',
				null,
				InputOption::VALUE_REQUIRED,
				'Enable query logger for this job. true / false Default: false',
				'false'
			)
			->addOption(
				'batch',
				'b',
				InputOption::VALUE_REQUIRED,
				'Batch size for this job. Default: 1000.',
				1000
			)
			->addOption(
				'types',
				null,
				InputOption::VALUE_REQUIRED,
				'Comma-separated list of metadata types to rebuild. Supported: '
					. implode(', ', $supportedTypes)
					. '. Default: all supported types.',
				''
			)
			->addOption(
				'resume',
				null,
				InputOption::VALUE_NONE,
				'Resume an existing pending job instead of starting a new one. Without this flag, re-running the command discards any prior progress and restarts from the beginning.'
			);

		if ($this->getCommandAliases())
		{
			$this->setAliases($this->getCommandAliases());
		}

		$this->configureOptions();
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$jobUniqueKey = 'xfEmbedMetadataJob-' . $this->getCommandName();

		if ($input->getOption('resume'))
		{
			if (\XF::app()->jobManager()->getUniqueJob($jobUniqueKey))
			{
				$this->runJob($jobUniqueKey, $output);
				return 0;
			}

			$output->writeln("<error>There are no pending jobs of this type to resume.</error>");
			return 1;
		}

		$params = $this->getJobParams($input, $error);
		if ($error)
		{
			$output->writeln('<error>' . $error . '</error>');
			return 1;
		}

		\XF::db()->logQueries($params['log-queries']);
		unset($params['log-queries']);

		$this->setupAndRunJob(
			$jobUniqueKey,
			$this->getJobClass(),
			$params,
			$output
		);

		return 0;
	}

	protected function getJobParams(InputInterface $input, &$error = null)
	{
		$supportedTypes = $this->getSupportedTypes();

		$typesRaw = trim((string) $input->getOption('types'));
		$types = array_values(array_unique(array_filter(
			array_map('trim', explode(',', $typesRaw)),
			'strlen'
		)));

		if (!$types)
		{
			$types = $supportedTypes;
		}
		else
		{
			$unknown = array_values(array_diff($types, $supportedTypes));
			if ($unknown)
			{
				$error = sprintf(
					'Unknown type%s "%s". Supported: %s',
					count($unknown) > 1 ? 's' : '',
					implode('", "', $unknown),
					implode(', ', $supportedTypes)
				);
				return [];
			}
		}

		$logQueries = $input->getOption('log-queries');
		if ($logQueries === 'false')
		{
			$logQueries = false;
		}

		return [
			'log-queries' => (bool) $logQueries,
			'batch' => max(1, (int) $input->getOption('batch')),
			'types' => $types,
		];
	}
}
