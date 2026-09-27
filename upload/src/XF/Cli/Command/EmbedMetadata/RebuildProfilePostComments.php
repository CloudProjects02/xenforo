<?php

namespace XF\Cli\Command\EmbedMetadata;

use XF\Job\ProfilePostCommentEmbedMetadata;

class RebuildProfilePostComments extends AbstractEmbedMetadataCommand
{
	protected function getCommandName()
	{
		return 'profile-post-comments';
	}

	protected function getCommandDescription()
	{
		return 'Rebuilds embed metadata for profile post comments.';
	}

	protected function getJobClass()
	{
		return ProfilePostCommentEmbedMetadata::class;
	}
}
