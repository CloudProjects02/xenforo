<?php

namespace DBTech\Shop\Install;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

/**
 * @property \XF\AddOn\AddOn addOn
 * @property \XF\App app
 *
 * @method \XF\Db\AbstractAdapter db()
 * @method \XF\Db\SchemaManager schemaManager()
 * @method \XF\Db\Schema\Column addOrChangeColumn($table, $name, $type = null, $length = null)
 */
trait InstallDataTrait
{
	/**
	 * @return \Closure[]
	 */
	protected function getTables(): array
	{
		$tables = [];

		$tables['xf_dbtech_shop_bank'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'user_id', 'int');
			$this->addOrChangeColumn($table, 'currency_id', 'int');
			$this->addOrChangeColumn($table, 'points', 'decimal', '65,8')->unsigned(false)->setDefault(0);
			$this->addOrChangeColumn($table, 'last_interest_date', 'int')->setDefault(1);
			$table->addPrimaryKey(['user_id', 'currency_id']);
		};

		$tables['xf_dbtech_shop_cart'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'user_id', 'int');
			$this->addOrChangeColumn($table, 'item_id', 'int');
			$this->addOrChangeColumn($table, 'recipient_user_id', 'int');
			$this->addOrChangeColumn($table, 'recipient_username', 'varchar', 50);
			$this->addOrChangeColumn($table, 'quantity', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'message', 'mediumblob')->nullable(true);
			$table->addPrimaryKey(['user_id', 'item_id', 'recipient_user_id']);
		};

		$tables['xf_dbtech_shop_category'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'category_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'title', 'varchar', 100);
			$this->addOrChangeColumn($table, 'description', 'text');
			$this->addOrChangeColumn($table, 'parent_category_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'display_order', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'lft', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'rgt', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'depth', 'smallint', 5)->setDefault(0);
			$this->addOrChangeColumn($table, 'breadcrumb_data', 'blob');
			$this->addOrChangeColumn($table, 'item_count', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'last_update', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'last_item_title', 'varchar', 100)->setDefault('');
			$this->addOrChangeColumn($table, 'last_item_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'prefix_cache', 'mediumblob');
			$this->addOrChangeColumn($table, 'field_cache', 'mediumblob');
			$this->addOrChangeColumn($table, 'item_filters', 'blob');
			$this->addOrChangeColumn($table, 'require_prefix', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'thread_node_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'thread_prefix_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'item_update_notify', 'enum')->values(['thread', 'reply'])->setDefault('thread');
			$this->addOrChangeColumn($table, 'always_moderate_create', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'always_moderate_update', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'min_tags', 'smallint', 5)->setDefault(0);
			$this->addOrChangeColumn($table, 'sales', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'sales_amounts', 'mediumblob')->nullable(true);
			$this->addOrChangeColumn($table, 'latest_customer_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'latest_sale_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'beneficiary', 'int')->unsigned(false)->setDefault(-1);
			$this->addOrChangeColumn($table, 'beneficiary_split', 'tinyint', 3)->setDefault(100);
			$this->addOrChangeColumn($table, 'num_ratings', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'average_rating', 'double')->setDefault(0);
			$this->addOrChangeColumn($table, 'positive_percent', 'double')->setDefault(0);
			$this->addOrChangeColumn($table, 'negative_percent', 'double')->setDefault(0);
			$this->addOrChangeColumn($table, 'neutral_percent', 'double')->setDefault(0);
			$this->addOrChangeColumn($table, 'can_have_feedback', 'tinyint', 3)->setDefault(1);
			$table->addKey(['parent_category_id', 'lft']);
			$table->addKey(['lft', 'rgt']);
		};

		$tables['xf_dbtech_shop_category_field'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'category_id', 'int');
			$this->addOrChangeColumn($table, 'field_id', 'varbinary', 25);
			$table->addPrimaryKey(['category_id', 'field_id']);
			$table->addKey('field_id');
		};

		$tables['xf_dbtech_shop_category_prefix'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'category_id', 'int');
			$this->addOrChangeColumn($table, 'prefix_id', 'int');
			$table->addPrimaryKey(['category_id', 'prefix_id']);
			$table->addKey('prefix_id');
		};

		$tables['xf_dbtech_shop_category_watch'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'user_id', 'int');
			$this->addOrChangeColumn($table, 'category_id', 'int');
			$this->addOrChangeColumn($table, 'notify_on', 'enum')->values(['', 'item']);
			$this->addOrChangeColumn($table, 'send_alert', 'tinyint', 3);
			$this->addOrChangeColumn($table, 'send_email', 'tinyint', 3);
			$this->addOrChangeColumn($table, 'include_children', 'tinyint', 3);
			$table->addPrimaryKey(['user_id', 'category_id']);
			$table->addKey(['category_id', 'notify_on'], 'category_id_notify_on');
		};

		$tables['xf_dbtech_shop_currency'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'currency_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'title', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'description', 'blob')->nullable(true);
			$this->addOrChangeColumn($table, 'active', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'display_order', 'int')->setDefault(10);
			$this->addOrChangeColumn($table, 'table', 'varchar', 255)->setDefault('');
			$this->addOrChangeColumn($table, 'use_table_prefix', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'column', 'varchar', 255)->setDefault('');
			$this->addOrChangeColumn($table, 'use_user_id', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'user_id_column', 'varchar', '255')->setDefault('user_id');
			$this->addOrChangeColumn($table, 'decimals', 'tinyint', 2)->setDefault(0);
			$this->addOrChangeColumn($table, 'privacy', 'tinyint', 3)->setDefault(2);
			$this->addOrChangeColumn($table, 'blacklist', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'prefix', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'suffix', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'is_display_currency', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'can_bank', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'can_steal', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'can_trade', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'steal_protect', 'double')->setDefault('100.00');
			$this->addOrChangeColumn($table, 'interest', 'double')->unsigned(false)->setDefault(0);
			$this->addOrChangeColumn($table, 'customshops', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'per_reply', 'int')->setDefault(1);
			$this->addOrChangeColumn($table, 'per_thread', 'int')->setDefault(1);
			$this->addOrChangeColumn($table, 'credits_currency_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'sidebar', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'postbit', 'tinyint', 3)->setDefault(1);
		};

		$tables['xf_dbtech_shop_item'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'item_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'category_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'title', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'description', 'mediumblob')->nullable(true);
			$this->addOrChangeColumn($table, 'item_state', 'enum')->values(['visible', 'moderated', 'deleted'])->setDefault('visible');
			$this->addOrChangeColumn($table, 'display_order', 'int')->setDefault(10);
			$this->addOrChangeColumn($table, 'creation_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'last_update', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'purchases', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'price', 'double')->setDefault(0);
			$this->addOrChangeColumn($table, 'user_criteria', 'mediumblob')->nullable(true);
			$this->addOrChangeColumn($table, 'code', 'mediumblob')->nullable(true);
			$this->addOrChangeColumn($table, 'item_type_id', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'thread_node_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'thread_prefix_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'discussion_thread_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'field_cache', 'mediumblob');
			$this->addOrChangeColumn($table, 'item_fields', 'mediumblob');
			$this->addOrChangeColumn($table, 'item_filters', 'blob');
			$this->addOrChangeColumn($table, 'display_in_list', 'tinyint')->after('item_filters');
			$this->addOrChangeColumn($table, 'is_giftable', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'send_gift_pm', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'currency_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'length_amount', 'tinyint', 3);
			$this->addOrChangeColumn($table, 'length_unit', 'enum')->values(['day', 'month', 'year', '']);
			$this->addOrChangeColumn($table, 'user_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'username', 'varchar', 50);
			$this->addOrChangeColumn($table, 'ip_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'reaction_score', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'reactions', 'blob');
			$this->addOrChangeColumn($table, 'reaction_users', 'blob');
			$this->addOrChangeColumn($table, 'warning_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'warning_message', 'varchar', 255)->setDefault('');
			$this->addOrChangeColumn($table, 'item_flags', 'blob');
			$this->addOrChangeColumn($table, 'is_unique', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'is_only_giftable', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'moderation', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'can_reconfigure', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'can_regift', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'can_discard', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'is_always_hidden', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'purchasable_thread_creation', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'purchasable_thread_page', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'enabled_custom_shops', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'is_stealth_item', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'is_exclusive', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'auto_discard', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'auto_discard_expiry', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'buyback_currency_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'buyback_price', 'double')->setDefault(0);
			$this->addOrChangeColumn($table, 'buyback_time', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'buyback_replenish', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'notifications', 'mediumblob')->nullable(true);
			$this->addOrChangeColumn($table, 'notifications_config', 'mediumblob')->nullable(true);
			$this->addOrChangeColumn($table, 'stock', 'int')->unsigned(false)->setDefault('0');
			$this->addOrChangeColumn($table, 'maxstock', 'int')->unsigned(false)->setDefault('0');
			$this->addOrChangeColumn($table, 'refill_time', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'last_refill_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'rating_count', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'rating_sum', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'rating_avg', 'float', '')->setDefault(0);
			$this->addOrChangeColumn($table, 'rating_weighted', 'float', '')->setDefault(0);
			$this->addOrChangeColumn($table, 'review_count', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'icon_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'prefix_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'tags', 'mediumblob');
			$table->addKey(['category_id', 'last_update'], 'category_last_update');
			$table->addKey(['category_id', 'rating_weighted'], 'category_rating_weighted');
			$table->addKey('last_update');
			$table->addKey('rating_weighted');
			$table->addKey(['user_id', 'last_update']);
			$table->addKey('discussion_thread_id');
			$table->addKey('prefix_id');
		};

		$tables['xf_dbtech_shop_item_field'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'field_id', 'varbinary', 25);
			$this->addOrChangeColumn($table, 'display_group', 'varchar', 25)->setDefault('above_info');
			$this->addOrChangeColumn($table, 'display_order', 'int')->setDefault(1);
			$this->addOrChangeColumn($table, 'field_type', 'varbinary', 25)->setDefault('textbox');
			$this->addOrChangeColumn($table, 'field_choices', 'blob');
			$this->addOrChangeColumn($table, 'match_type', 'varbinary', 25)->setDefault('none');
			$this->addOrChangeColumn($table, 'match_params', 'blob');
			$this->addOrChangeColumn($table, 'max_length', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'required', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'user_editable', 'enum')->values(['yes', 'once', 'never'])->setDefault('yes');
			$this->addOrChangeColumn($table, 'moderator_editable', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'display_template', 'text');
			$table->addPrimaryKey('field_id');
			$table->addKey(['display_group', 'display_order'], 'display_group_order');
		};

		$tables['xf_dbtech_shop_item_field_value'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'item_id', 'int');
			$this->addOrChangeColumn($table, 'field_id', 'varbinary', 25);
			$this->addOrChangeColumn($table, 'field_value', 'mediumtext');
			$table->addPrimaryKey(['item_id', 'field_id']);
			$table->addKey('field_id');
		};

		$tables['xf_dbtech_shop_item_filter_map'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'item_id', 'int');
			$this->addOrChangeColumn($table, 'filter_id', 'varbinary', 25);
			$table->addPrimaryKey(['item_id', 'filter_id']);
			$table->addKey('filter_id');
		};

		$tables['xf_dbtech_shop_item_prefix'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'prefix_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'prefix_group_id', 'int');
			$this->addOrChangeColumn($table, 'display_order', 'int');
			$this->addOrChangeColumn($table, 'materialized_order', 'int');
			$this->addOrChangeColumn($table, 'css_class', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'allowed_user_group_ids', 'blob');
			$table->addKey('materialized_order');
		};

		$tables['xf_dbtech_shop_item_prefix_group'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'prefix_group_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'display_order', 'int');
		};

		$tables['xf_dbtech_shop_item_rating'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'item_rating_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'user_id', 'int');
			$this->addOrChangeColumn($table, 'rating', 'tinyint', 3);
			$this->addOrChangeColumn($table, 'rating_date', 'int');
			$this->addOrChangeColumn($table, 'message', 'mediumtext');
			$this->addOrChangeColumn($table, 'item_id', 'int');
			$this->addOrChangeColumn($table, 'author_response', 'mediumtext');
			$this->addOrChangeColumn($table, 'is_review', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'count_rating', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'rating_state', 'enum')->values(['visible','deleted'])->setDefault('visible');
			$this->addOrChangeColumn($table, 'warning_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'is_anonymous', 'tinyint', 3)->setDefault(0);
			$table->addUniqueKey(['item_id', 'user_id'], 'item_user_id');
			$table->addKey('user_id');
			$table->addKey(['count_rating', 'item_id']);
			$table->addKey(['item_id', 'rating_date']);
			$table->addKey('rating_date');
		};

		$tables['xf_dbtech_shop_item_watch'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'user_id', 'int');
			$this->addOrChangeColumn($table, 'item_id', 'int');
			$this->addOrChangeColumn($table, 'email_subscribe', 'tinyint', 3)->setDefault(0);
			$table->addPrimaryKey(['user_id', 'item_id']);
			$table->addKey(['item_id', 'email_subscribe']);
		};

		$tables['xf_dbtech_shop_lottery'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'lottery_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'title', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'description', 'mediumblob')->nullable(true);
			$this->addOrChangeColumn($table, 'active', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'ticket_price', 'double')->setDefault(0);
			$this->addOrChangeColumn($table, 'currency_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'numbers', 'mediumblob')->nullable(true);
			$this->addOrChangeColumn($table, 'draw_interval_days', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'next_draw_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'previous_draw_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'prizes', 'mediumblob');
			$this->addOrChangeColumn($table, 'drawn_numbers', 'mediumblob')->nullable(true);
			$this->addOrChangeColumn($table, 'tickets_sold', 'int')->setDefault(0);
		};


		$tables['xf_dbtech_shop_lottery_history'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'lottery_history_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'lottery_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'drawn_numbers', 'mediumblob')->nullable(true);
			$this->addOrChangeColumn($table, 'draw_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'tickets_sold', 'int')->setDefault(0);
		};

		$tables['xf_dbtech_shop_lottery_prize'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'lottery_prize_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'title', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'description', 'mediumblob')->nullable(true);
			$this->addOrChangeColumn($table, 'active', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'numbers', 'mediumblob')->nullable(true);
		};

		$tables['xf_dbtech_shop_lottery_prize_map'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'lottery_id', 'int');
			$this->addOrChangeColumn($table, 'lottery_prize_id', 'int');
			$this->addOrChangeColumn($table, 'currency_id', 'int');
			$this->addOrChangeColumn($table, 'prize_amount', 'double')->setDefault(0);
			$table->addPrimaryKey(['lottery_id', 'lottery_prize_id']);
			$table->addKey('currency_id');
		};

		$tables['xf_dbtech_shop_lottery_ticket'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'lottery_ticket_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'lottery_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'user_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'ticket_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'draw_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'numbers', 'mediumblob')->nullable(true);
			$this->addOrChangeColumn($table, 'lottery_prize_id', 'int')->setDefault(0);
			$table->addKey(['lottery_id', 'user_id'], 'lottery_user_id');
			$table->addKey(['lottery_id', 'draw_date'], 'lottery_id_draw_date');
		};

		$tables['xf_dbtech_shop_purchase'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'purchase_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'user_id', 'int');
			$this->addOrChangeColumn($table, 'buyer_user_id', 'int');
			$this->addOrChangeColumn($table, 'buyer_username', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'item_id', 'int');
			$this->addOrChangeColumn($table, 'dateline', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'configuration', 'mediumblob')->nullable(true);
			$this->addOrChangeColumn($table, 'message', 'mediumblob')->nullable(true);
			$this->addOrChangeColumn($table, 'active', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'hidden', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'configured', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'gifted', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'traded', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'feedback_status', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'expiry_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'discussion_thread_id', 'int')->setDefault(0);
			$table->addKey('item_id');
			$table->addKey('user_id');
			$table->addKey('buyer_user_id');
			$table->addKey('discussion_thread_id');
		};

		$tables['xf_dbtech_shop_thread_ban'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'thread_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'user_id', 'int')->setDefault(0);
			$table->addPrimaryKey(['thread_id', 'user_id']);
		};

		$tables['xf_dbtech_shop_trade'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'trade_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'creator_user_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'creator_username', 'varchar', 50);
			$this->addOrChangeColumn($table, 'recipient_user_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'recipient_username', 'varchar', 50);
			$this->addOrChangeColumn($table, 'created_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'updated_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'trade_state', 'enum')->values(['pending','open','awaiting_accept','accepted','cancelled'])->setDefault('pending');
			$this->addOrChangeColumn($table, 'creator_accepted', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'recipient_accepted', 'tinyint', 3)->setDefault(0);
			$this->addOrChangeColumn($table, 'conversation_id', 'int')->setDefault(0);
			$table->addKey('creator_user_id');
			$table->addKey('recipient_user_id');
		};

		$tables['xf_dbtech_shop_trade_offer'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'trade_id', 'int');
			$this->addOrChangeColumn($table, 'user_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'content_type', 'varchar', 25)->setDefault('dbtech_shop_purchase');
			$this->addOrChangeColumn($table, 'content_id', 'int');
			$this->addOrChangeColumn($table, 'quantity', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'finalized', 'tinyint')->setDefault(0);
			$table->addPrimaryKey(['trade_id', 'user_id', 'content_type', 'content_id']);
		};

		$tables['xf_dbtech_shop_trade_post'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'trade_post_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'trade_id', 'int');
			$this->addOrChangeColumn($table, 'user_id', 'int');
			$this->addOrChangeColumn($table, 'username', 'varchar', 50);
			$this->addOrChangeColumn($table, 'post_date', 'int');
			$this->addOrChangeColumn($table, 'message', 'mediumtext');
			$this->addOrChangeColumn($table, 'ip_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'message_state', 'enum')->values(['visible','moderated','deleted'])->setDefault('visible');
			$this->addOrChangeColumn($table, 'attach_count', 'smallint', 5)->setDefault(0);
			$this->addOrChangeColumn($table, 'reaction_score', 'int')->unsigned(false)->setDefault(0);
			$this->addOrChangeColumn($table, 'reactions', 'blob')->nullable();
			$this->addOrChangeColumn($table, 'reaction_users', 'blob');
			$this->addOrChangeColumn($table, 'comment_count', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'first_comment_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'last_comment_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'latest_comment_ids', 'blob');
			$this->addOrChangeColumn($table, 'warning_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'warning_message', 'varchar', 255)->setDefault('');
			$this->addOrChangeColumn($table, 'embed_metadata', 'blob')->nullable();
			$table->addKey(['trade_id', 'post_date']);
			$table->addKey('user_id');
			$table->addKey('post_date');
		};

		$tables['xf_dbtech_shop_trade_post_comment'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'trade_post_comment_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'trade_post_id', 'int');
			$this->addOrChangeColumn($table, 'user_id', 'int');
			$this->addOrChangeColumn($table, 'username', 'varchar', 50);
			$this->addOrChangeColumn($table, 'comment_date', 'int');
			$this->addOrChangeColumn($table, 'message', 'mediumtext');
			$this->addOrChangeColumn($table, 'ip_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'message_state', 'enum')->values(['visible','moderated','deleted'])->setDefault('visible');
			$this->addOrChangeColumn($table, 'reaction_score', 'int')->unsigned(false)->setDefault(0);
			$this->addOrChangeColumn($table, 'reactions', 'blob')->nullable();
			$this->addOrChangeColumn($table, 'reaction_users', 'blob');
			$this->addOrChangeColumn($table, 'warning_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'warning_message', 'varchar', 255)->setDefault('');
			$this->addOrChangeColumn($table, 'embed_metadata', 'blob')->nullable();
			$table->addKey(['trade_post_id', 'comment_date']);
			$table->addKey('user_id');
			$table->addKey('comment_date');
		};

		$tables['xf_dbtech_shop_trading_card'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'trading_card_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'title', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'description', 'mediumblob')->nullable(true);
			$this->addOrChangeColumn($table, 'active', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'rarity', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'filename', 'varchar', 255)->setDefault('');
		};

		$tables['xf_dbtech_shop_trading_card_collection'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'trading_card_id', 'int');
			$this->addOrChangeColumn($table, 'user_id', 'int');
			$this->addOrChangeColumn($table, 'trade_locked', 'tinyint', 3)->setDefault(0);
			$table->addPrimaryKey(['trading_card_id', 'user_id']);
		};

		$tables['xf_dbtech_shop_transaction_log'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'transaction_log_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'user_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'recipient_user_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'dateline', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'ip_id', 'int', 10)->setDefault(0);
			$this->addOrChangeColumn($table, 'action', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'content_type', 'varbinary', 25);
			$this->addOrChangeColumn($table, 'content_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'info', 'mediumblob')->nullable(true);
			$table->addKey('user_id');
			$table->addKey('recipient_user_id');
		};

		$tables['xf_dbtech_shop_item_usergroup_discount'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'item_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'user_group_id', 'int');
			$this->addOrChangeColumn($table, 'discount_type', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'discount_value', 'float', '')->setDefault(0);
			$table->addPrimaryKey(['item_id', 'user_group_id']);
		};


		
		return $tables;
	}
	
	/**
	 * @return array
	 */
	protected function getAlterDefinitions(): array
	{
		$definitions['xf_user'] = [
			'columns' => [
				'dbtech_shop_points'         => [
					'type'    => 'decimal',
					'length'  => '65,8',
					'unsigned' => false,
					'default' => 0
				],
				'dbtech_shop_purchase' => [
					'type'     => 'mediumblob',
					'nullable' => true,
				],
				'dbtech_shop_purchases' => [
					'type'    => 'int',
					'length'  => null,
					'default' => 0
				],
				'dbtech_shop_immunity' => [
					'type'    => 'int',
					'length'  => null,
					'default' => 0
				],
				'dbtech_shop_pendingtrades' => [
					'type'    => 'int',
					'length'  => null,
					'default' => 0
				],
				'dbtech_shop_item_count' => [
					'type'    => 'int',
					'length'  => null,
					'default' => 0
				],
			],
			'keys' => [
				// indexname => columns
				'dbtech_shop_item_count' => ['dbtech_shop_item_count']
			]
		];

		return $definitions;
	}
	
	/**
	 * @return string[]
	 */
	protected function getInstallQueries(): array
	{
		return [
			"
				INSERT IGNORE INTO xf_dbtech_shop_bank
					(`user_id`, `currency_id`, `points`)
				SELECT
					`user_id`,
					'1',
					'0.00'
				FROM `xf_user` AS `user`
				ORDER BY `user_id`
			",
			"
				INSERT IGNORE INTO `xf_dbtech_shop_currency`
					(`currency_id`, `title`, `description`, `active`, `display_order`, `table`, `use_table_prefix`, `column`, `use_user_id`, `user_id_column`, `decimals`, `privacy`, `blacklist`, `prefix`, `suffix`, `is_display_currency`, `can_bank`, `can_steal`, `can_trade`, `steal_protect`, `interest`, `customshops`, `per_reply`, `per_thread`, `credits_currency_id`)
				VALUES
					(1, 'Points', X'5468652064656661756C7420447261676F6E427974652053686F702063757272656E63792E', 1, 10, 'user', 1, 'dbtech_shop_points', 1, 'user_id', 0, 2, 1, '', '', 0, 1, 1, 1, -1.00, 0.00, 1, 1, 5, 0)
			",
			"
				REPLACE INTO `xf_dbtech_shop_category`
					(`category_id`,
					`title`,
					`description`,
					`parent_category_id`, `display_order`, `lft`, `rgt`, `depth`,
					`breadcrumb_data`, `item_count`,
					`last_update`, `last_item_title`,
					`last_item_id`, `prefix_cache`, `field_cache`,
					`item_filters`, `require_prefix`, `thread_node_id`,
					`thread_prefix_id`, `item_update_notify`,
					`always_moderate_create`, `always_moderate_update`,
					`min_tags`, `sales`, `sales_amounts`,
					`latest_customer_id`, `latest_sale_id`, `beneficiary`,
					`beneficiary_split`, `num_ratings`, `average_rating`,
					`positive_percent`, `negative_percent`,
					`neutral_percent`)
				VALUES
					(1,
					'Example category',
					'This is an example Shop category. You can manage the Shop categories via the <a href=\"" . \XF::options()->boardUrl . "/admin.php?dbtech-shop/categories/\" target=\"_blank\">Admin control panel</a>. From there, you can setup more categories or change the Shop options.',
					0, 1, 1, 2, 0, X'613A303A7B7D', 0, 0, '',
					0, X'', X'', X'', 0, 0, 0, 'thread', 0, 0,
					0, 0, NULL, 0, 0, -1, 100, 0, 0, 0, 0, 0)
			",
		];
	}

	/**
	 * Returns true if permissions were modified, otherwise false.
	 *
	 * @return bool
	 */
	protected function applyPermissionsInstall(): bool
	{
		// Regular perms
		$this->applyGlobalPermission('dbtech_shop', 'viewLottery', 'general', 'viewNode');
		$this->applyGlobalPermission('dbtech_shop', 'bank', 'general', 'viewNode');
		$this->applyGlobalPermission('dbtech_shop', 'steal', 'forum', 'postThread');
		$this->applyGlobalPermission('dbtech_shop', 'trade', 'forum', 'postThread');

		return true;
	}
	
	/**
	 * @return \Closure[]
	 */
	protected function getDefaultWidgetSetup(): array
	{
		return [
			'dbtech_shop_profilemusic' => function ($key, array $options = [])
			{
				$options = array_replace([], $options);

				$this->createWidget(
					$key,
					'dbtech_shop_profilemusic',
					[
						'positions' => [
							'member_view_sidebar' => 100
						],
						'options' => $options
					]
				);
			},
			'dbtech_shop_wallet' => function ($key, array $options = [])
			{
				$options = array_replace([], $options);

				$this->createWidget(
					$key,
					'dbtech_shop_wallet',
					[
						'positions' => [
							'dbtech_shop_overview_sidenav' => 100,
							'dbtech_shop_category_sidenav' => 100,
							'dbtech_shop_item_sidebar' => 100,
							'dbtech_shop_bank_sidebar' => 100,
							'dbtech_shop_lottery_sidebar' => 100,
							'dbtech_shop_trade_sidebar' => 100,
							'dbtech_shop_steal_sidebar' => 100,
						],
						'options' => $options
					],
					'Wallet'
				);
			},
			'dbtech_shop_cart' => function ($key, array $options = [])
			{
				$options = array_replace([], $options);

				$this->createWidget(
					$key,
					'dbtech_shop_cart',
					[
						'positions' => [
							'dbtech_shop_overview_sidenav' => 100,
							'dbtech_shop_category_sidenav' => 100,
							'dbtech_shop_item_sidebar' => 100
						],
						'options' => $options
					],
					'Cart'
				);
			},

			'dbtech_shop_list_top_items' => function ($key, array $options = [])
			{
				$options = array_replace([], $options);

				$this->createWidget(
					$key,
					'dbt_shop_top_items',
					[
						'positions' => [
							'dbtech_shop_overview_sidenav' => 100,
							'dbtech_shop_category_sidenav' => 100,
						],
						'options' => $options
					]
				);
			},
			'dbtech_shop_overview_latest_reviews' => function ($key, array $options = [])
			{
				$options = array_replace([], $options);

				$this->createWidget(
					$key,
					'dbt_shop_latest_reviews',
					[
						'positions' => ['dbtech_shop_overview_sidenav' => 200],
						'options' => $options
					]
				);
			},
			'dbtech_shop_overview_top_authors' => function ($key, array $options = [])
			{
				$options = array_replace([
					'member_stat_key' => 'dbtech_shop_most_items'
				], $options);

				$this->createWidget(
					$key,
					'member_stat',
					[
						'positions' => ['dbtech_shop_overview_sidenav' => 300],
						'options' => $options
					]
				);
			},
			'dbtech_shop_whats_new_overview_new_items' => function ($key, array $options = [])
			{
				$options = array_replace([
					'limit' => 10,
					'style' => 'full'
				], $options);

				$this->createWidget(
					$key,
					'dbt_shop_new_items',
					[
						'positions' => ['whats_new_overview' => 200],
						'options' => $options
					]
				);
			},
			'dbtech_shop_forum_overview_new_items' => function ($key, array $options = [])
			{
				$options = array_replace([], $options);

				$this->createWidget(
					$key,
					'dbt_shop_new_items',
					[
						'positions' => [
							'forum_list_sidebar' => 38,
							'forum_new_posts_sidebar' => 28
						],
						'options' => $options
					]
				);
			},
		];
	}
	
	/**
	 *
	 */
	protected function runPostInstallActions(): void
	{
		/** @var \XF\Service\RebuildNestedSet $service */
		$service = \XF::service('XF:RebuildNestedSet', 'DBTech\Shop:Category', [
			'parentField' => 'parent_category_id'
		]);
		$service->rebuildNestedSetInfo();

		/** @var \DBTech\Shop\Repository\ItemPrefix $itemPrefixRepo */
		$itemPrefixRepo = \XF::repository('DBTech\Shop:ItemPrefix');
		$itemPrefixRepo->rebuildPrefixCache();

		/** @var \DBTech\Shop\Repository\ItemField $itemFieldRepo */
		$itemFieldRepo = \XF::repository('DBTech\Shop:ItemField');
		$itemFieldRepo->rebuildFieldCache();
	}
	
	/**
	 * @return string[]
	 */
	protected function getAdminPermissions(): array
	{
		return [];
	}
	
	/**
	 * @return string[]
	 */
	protected function getPermissionGroups(): array
	{
		return [
			'dbtechShop',
			'dbtechShopAdmin',
			'dbtechShopTradePost'
		];
	}
	
	/**
	 * @return string[]
	 */
	protected function getContentTypes(): array
	{
		return [
			'dbtech_shop_category',
			'dbtech_shop_currency',
			'dbtech_shop_item',
			'dbtech_shop_purchase',
			'dbtech_shop_rating',
			'dbtech_shop_trade',
			'dbtech_shop_trade_comment',
			'dbtech_shop_trade_post',
		];
	}
	
	/**
	 * @return string[]
	 */
	protected function getRegistryEntries(): array
	{
		return [
			'dbtShopCategories',
			'dbtShopCurrencies',
			'dbtShopItemFieldsInfo',
			'dbtShopItems',
			'dbtShopPrefixes',
			'dbtShopUserNameStyle',
			'dbtShopUserTitleStyle'
		];
	}
}