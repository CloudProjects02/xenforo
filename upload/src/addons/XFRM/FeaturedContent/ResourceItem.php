<?php

namespace XFRM\FeaturedContent;

use XF\Entity\FeaturedContent;
use XF\FeaturedContent\AbstractHandler;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Repository\AttachmentRepository;

use function in_array;

/**
 * @extends AbstractHandler<\XFRM\Entity\ResourceItem>
 */
class ResourceItem extends AbstractHandler
{
	public function getContentImage(
		Entity $content,
		?string $sizeCode = null
	): ?string
	{
		return $content->getCoverImage();
	}

	public function getContentSnippet(Entity $content): string
	{
		return $this->getSnippetFromString($content->Description->message);
	}

	public function getContentStructuredData(Entity $content): array
	{
		return $content->getStructuredData();
	}

	public function shouldAutoFeature(Entity $content): bool
	{
		return $content->Category->auto_feature ?? false;
	}

	public function onContentFeature(
		Entity $content,
		FeaturedContent $feature
	): void
	{
		parent::onContentFeature($content, $feature);

		if ($content->resource_category_id)
		{
			\XF::db()->query(
				'UPDATE xf_rm_category
					SET featured_count = featured_count + 1
					WHERE resource_category_id = ?',
				[$content->resource_category_id]
			);
		}
	}

	public function onContentUnfeature(
		Entity $content,
		FeaturedContent $feature
	): void
	{
		parent::onContentUnfeature($content, $feature);

		if ($content->resource_category_id)
		{
			\XF::db()->query(
				'UPDATE xf_rm_category
					SET featured_count = IF(featured_count > 0, featured_count - 1, 0)
					WHERE resource_category_id = ?',
				[$content->resource_category_id]
			);
		}
	}

	public function getEntityWithForStyle(string $style): array
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

	protected function addAttachmentsToContent(
		AbstractCollection $imagelessContent,
		AbstractCollection $content
	): void
	{
		$descriptions = $imagelessContent
			->filter(function (\XFRM\Entity\ResourceItem $resourceItem): bool
			{
				return $resourceItem->Description !== null;
			})
			->pluckNamed('Description', 'description_update_id');

		$attachmentRepo = \XF::repository(AttachmentRepository::class);
		$attachmentRepo->addAttachmentsToContent($descriptions, 'resource_update');
	}
}
