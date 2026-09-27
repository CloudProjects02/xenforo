<?php

namespace DBTech\Shop\Api\Controller;

use DBTech\Shop\Finder\TradePostCommentFinder;
use DBTech\Shop\Service\TradePost\DeleterService;
use DBTech\Shop\Service\TradePost\EditorService;
use XF\Api\Controller\AbstractController;
use XF\Api\ControllerPlugin\ReactionPlugin;
use XF\Api\Mvc\Reply\ApiResult;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\PrintableException;

/**
 * @api-group Trade posts
 */
class TradePost extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 */
	protected function preDispatchController($action, ParameterBag $params): void
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
	 * @param ParameterBag $params
	 *
	 * @return ApiResult
	 * @throws Exception
	 */
	public function actionGet(ParameterBag $params): ApiResult
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id, ['api|trade']);

		if ($this->filter('with_comments', 'bool'))
		{
			$commentData = $this->getCommentsOnTradePostPaginated($tradePost, $this->filterPage());
		}
		else
		{
			$commentData = [];
		}

		$result = $tradePost->toApiResult(Entity::VERBOSITY_VERBOSE, [
			'with_trade' => true,
		]);

		$result = [
			'trade_post' => $result,
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
	 * @param ParameterBag $params
	 *
	 * @return ApiResult
	 * @throws Exception
	 */
	public function actionGetComments(ParameterBag $params): ApiResult
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
	 * @throws Exception
	 */
	protected function getCommentsOnTradePostPaginated(
		\DBTech\Shop\Entity\TradePost $tradePost,
		int $page = 1,
		?int $perPage = null
	): array
	{
		$perPage = intval($perPage);
		if ($perPage <= 0)
		{
			$perPage = \XF::app()->options()->messagesPerPage;
		}

		$commentFinder = $this->setupCommentsFinder($tradePost);

		$total = $commentFinder->total();
		$this->assertValidApiPage($page, $perPage, $total);

		$commentFinder->limitByPage($page, $perPage);
		$postResults = $commentFinder->fetch()->toApiResults(Entity::VERBOSITY_VERBOSE);

		return [
			'comments' => $postResults,
			'pagination' => $this->getPaginationData($postResults, $page, $perPage, $total),
		];
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 *
	 * @return TradePostCommentFinder
	 */
	protected function setupCommentsFinder(\DBTech\Shop\Entity\TradePost $tradePost): TradePostCommentFinder
	{
		/** @var TradePostCommentFinder $finder */
		$finder = \XF::app()->finder(TradePostCommentFinder::class);
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
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionPost(ParameterBag $params): AbstractReply
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
			'trade_post' => $tradePost->toApiResult(),
		]);
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 *
	 * @return EditorService
	 */
	protected function setupTradePostEdit(\DBTech\Shop\Entity\TradePost $tradePost): EditorService
	{
		$input = $this->filter([
			'message' => '?str',
			'author_alert' => 'bool',
			'author_alert_reason' => 'str',
		]);

		$editor = \XF::app()->service(EditorService::class, $tradePost);

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
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 * @throws PrintableException
	 */
	public function actionDelete(ParameterBag $params): AbstractReply
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

		$deleter = \XF::app()->service(DeleterService::class, $tradePost);

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
	 * @param ParameterBag $params
	 *
	 * @return ApiResult
	 * @throws Exception
	 */
	public function actionPostReact(ParameterBag $params): ApiResult
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		$reactPlugin = $this->plugin(ReactionPlugin::class);
		return $reactPlugin->actionReact($tradePost);
	}

	/**
	 * @param int $id
	 * @param array $with
	 *
	 * @return \DBTech\Shop\Entity\TradePost
	 *
	 * @throws Exception
	 * @noinspection PhpReturnDocTypeMismatchInspection
	 * @noinspection PhpIncompatibleReturnTypeInspection
	 */
	protected function assertViewableTradePost(int $id, array $with = ['api']): \DBTech\Shop\Entity\TradePost
	{
		return $this->assertViewableApiRecord(\DBTech\Shop\Entity\TradePost::class, $id, $with);
	}
}