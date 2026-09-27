<?php

namespace XF\Cli\Command\EmbedMetadata;

use XF\Job\PostEmbedMetadata;

class RebuildPosts extends AbstractEmbedMetadataCommand
{
	protected function getCommandName()
	{
		return 'posts';
	}

	protected function getCommandDescription()
	{
		return 'Rebuilds embed metadata for posts.';
	}

	protected function getJobClass()
	{
		return PostEmbedMetadata::class;
	}
}
