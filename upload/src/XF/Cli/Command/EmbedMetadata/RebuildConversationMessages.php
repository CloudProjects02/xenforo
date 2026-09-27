<?php

namespace XF\Cli\Command\EmbedMetadata;

use XF\Job\ConversationEmbedMetadata;

class RebuildConversationMessages extends AbstractEmbedMetadataCommand
{
	protected function getCommandName()
	{
		return 'conversation-messages';
	}

	protected function getCommandDescription()
	{
		return 'Rebuilds embed metadata for direct message conversations.';
	}

	protected function getJobClass()
	{
		return ConversationEmbedMetadata::class;
	}
}
