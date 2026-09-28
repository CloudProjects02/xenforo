<?php

namespace XFRM\TrendingContent;

use XF\Mvc\Entity\AbstractCollection;
use XF\Repository\AttachmentRepository;
use XF\TrendingContent\AbstractHandler;
use XFRM\Entity\ResourceItem;

use function in_array;

/**
 * @extends AbstractHandler<ResourceItem>
 */
class ResourceItemHandler extends AbstractHandler
{
	public function getEntityWith(string $style): array
	{
		$visitor = \XF::visitor();

		$with = [
			'Category',
			'Category.Permissions|' . $visitor->permission_combination_id,
			'User',
		];

		if (
			in_array($style, ['article', 'carousel'], true) ||
			$this->areAttachmentsHydratedForStyle($style)
		)
		{
			$with[] = 'Description';
		}

		return $with;
	}

	public function filterContent(AbstractCollection $content): AbstractCollection
	{
		return $content->filter(
			function (ResourceItem $item): bool
			{
				return $item->canView() && !$item->isIgnored();
			}
		);
	}

	public function addAttachmentsToContent(AbstractCollection $content): void
	{
		$descriptions = $content
			->filter(function (ResourceItem $resourceItem): bool
			{
				return $resourceItem->Description !== null;
			})
			->pluckNamed('Description', 'description_update_id');

		$attachmentRepo = \XF::repository(AttachmentRepository::class);
		$attachmentRepo->addAttachmentsToContent($descriptions, 'resource_update');
	}
}
