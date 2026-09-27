<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Shop\XF\Service\User;

use DBTech\Shop\Repository\CategoryRepository;
use DBTech\Shop\Repository\PurchaseRepository;
use XF\Db\Exception;

/**
 * @extends \XF\Service\User\MergeService
 */
class MergeService extends XFCP_MergeService
{
	/**
	 *
	 */
	protected function combineData()
	{
		parent::combineData();

		$container = \XF::app()->container();
		if (isset($container['dbtechShop.currencies']) && $currencies = $container['dbtechShop.currencies'])
		{
			/** @var \DBTech\Shop\Entity\Currency[] $currencies */
			foreach ($currencies AS $currencyId => $currency)
			{
				// Merge these currencies
				$this->target->{$currency['column']} += $this->source->{$currency['column']};
			}
		}
	}

	/**
	 * @throws Exception
	 */
	protected function postMergeCleanUp()
	{
		// Bank
		$banks = $this->db()->fetchAll('SELECT * FROM xf_dbtech_shop_bank WHERE user_id = ?', [$this->source->user_id]);
		foreach ($banks AS $bank)
		{
			$this->db()->query('
				UPDATE xf_dbtech_shop_bank
				SET points = points + ?
				WHERE user_id = ?
					AND currency_id + ?
			', [$bank['points'], $this->target->user_id, $bank['currency_id']]);
		}

		// Delete bank amounts from source
		$this->db()->delete('xf_dbtech_shop_bank', 'user_id = ?', [$this->source->user_id]);

		// Update category beneficiary
		$this->db()->update('xf_dbtech_shop_category', ['beneficiary' => $this->target->user_id], 'beneficiary = ?', [$this->source->user_id]);

		// Update category owner
		//		$this->db()->update('xf_dbtech_shop_category', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);
		//		$this->db()->update('xf_dbtech_shop_category', ['username' => $this->target->username], 'user_id = ?', [$this->source->user_id]);

		// Update category latest customer
		$this->db()->update('xf_dbtech_shop_category', ['latest_customer_id' => $this->target->user_id], 'latest_customer_id = ?', [$this->source->user_id]);

		// Update cart
		$this->db()->update('xf_dbtech_shop_cart', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);

		// Update category watch
		$this->db()->update('xf_dbtech_shop_category_watch', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);

		// Update rating
		$this->db()->update('xf_dbtech_shop_item_rating', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);

		// Update item watch
		$this->db()->update('xf_dbtech_shop_item_watch', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);

		// Update lottery tickets
		$this->db()->update('xf_dbtech_shop_lottery_ticket', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);

		// Update purchase owner
		$this->db()->update('xf_dbtech_shop_purchase', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);

		// Update purchase buyer
		$this->db()->update('xf_dbtech_shop_purchase', ['buyer_username' => $this->target->username], 'buyer_user_id = ?', [$this->source->user_id]);
		$this->db()->update('xf_dbtech_shop_purchase', ['buyer_user_id' => $this->target->user_id], 'buyer_user_id = ?', [$this->source->user_id]);

		// Update thread ban
		$this->db()->update('xf_dbtech_shop_thread_ban', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);

		// Update trade
		$this->db()->update('xf_dbtech_shop_trade', ['creator_username' => $this->target->username], 'creator_user_id = ?', [$this->source->user_id]);
		$this->db()->update('xf_dbtech_shop_trade', ['recipient_username' => $this->target->username], 'recipient_user_id = ?', [$this->source->user_id]);

		// Update trade offer
		$this->db()->update('xf_dbtech_shop_trade_offer', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);

		// Update trade post
		$this->db()->update('xf_dbtech_shop_trade_post', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);
		$this->db()->update('xf_dbtech_shop_trade_post', ['username' => $this->target->username], 'user_id = ?', [$this->source->user_id]);

		// Update trade comment
		$this->db()->update('xf_dbtech_shop_trade_post_comment', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);
		$this->db()->update('xf_dbtech_shop_trade_post_comment', ['username' => $this->target->username], 'user_id = ?', [$this->source->user_id]);

		// Update trading card collection
		$this->db()->update('xf_dbtech_shop_trading_card_collection', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);

		// Update transaction log
		$this->db()->update('xf_dbtech_shop_transaction_log', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);
		$this->db()->update('xf_dbtech_shop_transaction_log', ['recipient_user_id' => $this->target->user_id], 'recipient_user_id = ?', [$this->source->user_id]);

		// Rebuild purchase cache
		\XF::app()->repository(PurchaseRepository::class)->rebuildCacheForUser($this->target);

		// Update category cache
		\XF::app()->repository(CategoryRepository::class)->rebuildCache();
	}
}