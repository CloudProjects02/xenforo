<?php

namespace DBTech\Shop\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;

/**
 * @api-group Trade posts
 */
class TradePost extends AbstractController
{
	/**
	 * @param $action
	 * @param \XF\Mvc\ParameterBag $params
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('dbtech_shop_trade_post');
	}

	/**
	 * @api-desc Gets information about the specified trade post.
	 *
	 * @api-in bool $with_comments If specified, the response will include a page of comments.
	 * @api-in int $page The page of comments to include
	 *
	 * @api-out TradePost $trade_post
	 * @api-see self::getCommentsOnTradePostPaginated()
	 *
	 * @param \XF\Mvc\ParameterBag $params
	 *
	 * @return \XF\Api\Mvc\Reply\ApiResult
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionGet(ParameterBag $params): \XF\Api\Mvc\Reply\ApiResult
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id, 'api|trade');

		if ($this->filter('with_comments', 'bool'))
		{
			$commentData = $this->getCommentsOnTradePostPaginated($tradePost, $this->filterPage());
		}
		else
		{
			$commentData = [];
		}

		$result = $tradePost->toApiResult(Entity::VERBOSITY_VERBOSE, [
			'with_trade' => true
		]);

		$result = [
			'trade_post' => $result
		];
		$result += $commentData;

		return $this->apiResult($result);
	}

	/**
	 * @api-desc Gets a page of comments on the specified trade post.
	 *
	 * @api-in int $page
	 *
	 * @api-see self::getCommentsOnTradePostPaginated
	 *
	 * @param \XF\Mvc\ParameterBag $params
	 *
	 * @return \XF\Api\Mvc\Reply\ApiResult
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionGetComments(ParameterBag $params): \XF\Api\Mvc\Reply\ApiResult
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		$commentData = $this->getCommentsOnTradePostPaginated($tradePost, $this->filterPage());

		return $this->apiResult($commentData);
	}

	/**
	 * @api-out TradePostComment[] $comments List of comments on the requested page
	 * @api-out pagination $pagination Pagination details
	 *
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 * @param int $page
	 * @param null|int $perPage
	 *
	 * @return array
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function getCommentsOnTradePostPaginated(
		\DBTech\Shop\Entity\TradePost $tradePost,
		int $page = 1,
		?int $perPage = null
	): array {
		$perPage = intval($perPage);
		if ($perPage <= 0)
		{
			$perPage = $this->options()->messagesPerPage;
		}

		$commentFinder = $this->setupCommentsFinder($tradePost);

		$total = $commentFinder->total();
		$this->assertValidApiPage($page, $perPage, $total);

		$commentFinder->limitByPage($page, $perPage);
		$postResults = $commentFinder->fetch()->toApiResults(Entity::VERBOSITY_VERBOSE);

		return [
			'comments' => $postResults,
			'pagination' => $this->getPaginationData($postResults, $page, $perPage, $total)
		];
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 *
	 * @return \DBTech\Shop\Finder\TradePostComment
	 */
	protected function setupCommentsFinder(\DBTech\Shop\Entity\TradePost $tradePost): \DBTech\Shop\Finder\TradePostComment
	{
		/** @var \DBTech\Shop\Finder\TradePostComment $finder */
		$finder = $this->finder('DBTech\Shop:TradePostComment');
		$finder
			->forTradePost($tradePost)
			->setDefaultOrder('comment_date', 'desc')
			->with('api');

		return $finder;
	}

	/**
	 * @api-desc Updates the specified trade post.
	 *
	 * @api-in str $message
	 * @api-in bool $author_alert
	 * @api-in bool $author_alert_reason
	 *
	 * @api-out true $success
	 * @api-out TradePost $trade_post
	 *
	 * @param \XF\Mvc\ParameterBag $params
	 *
	 * @return \XF\Api\Mvc\Reply\ApiResult|\XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionPost(ParameterBag $params)
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		if (\XF::isApiCheckingPermissions() && !$tradePost->canEdit($error))
		{
			return $this->noPermission($error);
		}

		$editor = $this->setupTradePostEdit($tradePost);

		if (\XF::isApiCheckingPermissions())
		{
			$editor->checkForSpam();
		}

		if (!$editor->validate($errors))
		{
			return $this->error($errors);
		}

		$editor->save();

		return $this->apiSuccess([
			'trade_post' => $tradePost->toApiResult()
		]);
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 *
	 * @return \DBTech\Shop\Service\TradePost\Editor
	 */
	protected function setupTradePostEdit(\DBTech\Shop\Entity\TradePost $tradePost): \DBTech\Shop\Service\TradePost\Editor
	{
		$input = $this->filter([
			'message' => '?str',
			'author_alert' => 'bool',
			'author_alert_reason' => 'str'
		]);

		/** @var \DBTech\Shop\Service\TradePost\Editor $editor */
		$editor = $this->service('DBTech\Shop:TradePost\Editor', $tradePost);

		if ($input['message'] !== null)
		{
			$editor->setMessage($input['message']);
		}

		if ($input['author_alert'] && $tradePost->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $input['author_alert_reason']);
		}

		return $editor;
	}

	/**
	 * @api-desc Deletes the specified trade post. Default to soft deletion.
	 *
	 * @api-in bool $hard_delete
	 * @api-in str $reason
	 * @api-in bool $author_alert
	 * @api-in str $author_alert_reason
	 *
	 * @api-out true $success
	 *
	 * @param \XF\Mvc\ParameterBag $params
	 *
	 * @return \XF\Api\Mvc\Reply\ApiResult|\XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionDelete(ParameterBag $params)
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		if (\XF::isApiCheckingPermissions() && !$tradePost->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		$type = 'soft';
		$reason = $this->filter('reason', 'str');

		if ($this->filter('hard_delete', 'bool'))
		{
			$this->assertApiScope('dbtech_shop_trade_post:delete_hard');

			if (\XF::isApiCheckingPermissions() && !$tradePost->canDelete('hard', $error))
			{
				return $this->noPermission($error);
			}

			$type = 'hard';
		}

		/** @var \DBTech\Shop\Service\TradePost\Deleter $deleter */
		$deleter = $this->service('DBTech\Shop:TradePost\Deleter', $tradePost);

		if ($this->filter('author_alert', 'bool') && $tradePost->canSendModeratorActionAlert())
		{
			$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		$deleter->delete($type, $reason);

		return $this->apiSuccess();
	}

	/**
	 * @api-desc Reacts to the specified trade post
	 *
	 * @api-see \XF\Api\ControllerPlugin\Reaction::actionReact()
	 *
	 * @param \XF\Mvc\ParameterBag $params
	 *
	 * @return \XF\Api\Mvc\Reply\ApiResult
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionPostReact(ParameterBag $params): \XF\Api\Mvc\Reply\ApiResult
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		/** @var \XF\Api\ControllerPlugin\Reaction $reactPlugin */
		$reactPlugin = $this->plugin('XF:Api:Reaction');
		return $reactPlugin->actionReact($tradePost);
	}

	/**
	 * @param int $id
	 * @param string|array $with
	 *
	 * @return \DBTech\Shop\Entity\TradePost|\XF\Mvc\Entity\Entity
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableTradePost(int $id, $with = 'api')
	{
		return $this->assertViewableApiRecord('DBTech\Shop:TradePost', $id, $with);
	}
}