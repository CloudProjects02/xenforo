<?php

namespace XFRM\EmbedResolver;

use XF\EmbedResolver\AbstractHandler;

class ResourceRating extends AbstractHandler
{
	public function getEntityWith(): array
	{
		return ['Resource', 'Resource.Category', 'Resource.User'];
	}
}
