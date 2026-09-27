<?php

namespace XF\Api\Controller;

use XF\Entity\EmbedResolverTrait;
use XF\Repository\EmbedResolverRepository;

/**
 * @api-group oEmbed
 */
class OEmbedController extends AbstractController
{
	public function allowUnauthenticatedRequest($action)
	{
		return $this->options()->allowExternalEmbed;
	}

	/**
	 * @api-desc Returns oEmbed data for the given URL (oEmbed 1.0 consumer endpoint).
	 *
	 * @api-in <req> str $url The URL of the content to retrieve oEmbed data for.
	 *
	 * @api-out str $version oEmbed version (always '1.0')
	 * @api-out str $type oEmbed type (e.g. 'rich')
	 * @api-out str $provider_name
	 * @api-out str $provider_url
	 * @api-out str $author_name
	 * @api-out str $author_url
	 * @api-out str $html HTML embed code
	 * @api-out str $referrer
	 * @api-out int $cache_age Recommended cache lifetime in seconds
	 */
	public function actionGet()
	{
		$this->assertRequiredApiInput('url');

		$url = $this->filter('url', 'str');

		$embedRepo = $this->app->repository(EmbedResolverRepository::class);

		/** @var EmbedResolverTrait $content */
		$content = $embedRepo->getEntityFromUrl($url);

		if (!$content)
		{
			return $this->apiError(
				\XF::phrase('requested_content_for_url_x_unavailable', ['url' => $url]),
				'requested_content_unavailable',
				['url' => $url]
			);
		}

		return $this->apiResult($content->getOembedOutput());
	}
}
