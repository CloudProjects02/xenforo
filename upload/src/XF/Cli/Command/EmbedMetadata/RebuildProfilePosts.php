<?php

namespace XF\Cli\Command\EmbedMetadata;

use XF\Job\ProfilePostEmbedMetadata;

class RebuildProfilePosts extends AbstractEmbedMetadataCommand
{
	protected function getCommandName()
	{
		return 'profile-posts';
	}

	protected function getCommandDescription()
	{
		return 'Rebuilds embed metadata for profile posts.';
	}

	protected function getJobClass()
	{
		return ProfilePostEmbedMetadata::class;
	}
}
