<?php

namespace XFRM\Install\Data;

use XF\Db\Schema\Create;
use XF\Install\Data\AbstractMySql;

class MySql extends AbstractMySql
{
	public function getTables(): array
	{
		$tables = [];

		$tables['xf_rm_category'] = function (Create $table)
		{
			$table->addColumn('resource_category_id', 'int')->autoIncrement();
			$table->addColumn('title', 'varchar', 100);
			$table->addColumn('description', 'text');
			$table->addColumn('parent_category_id', 'int')->setDefault(0);
			$table->addColumn('display_order', 'int')->setDefault(0);
			$table->addColumn('lft', 'int')->setDefault(0);
			$table->addColumn('rgt', 'int')->setDefault(0);
			$table->addColumn('depth', 'smallint')->setDefault(0);
			$table->addColumn('breadcrumb_data', 'blob');
			$table->addColumn('resource_count', 'int')->setDefault(0);
			$table->addColumn('featured_count', 'smallint')->setDefault(0);
			$table->addColumn('last_update', 'int')->setDefault(0);
			$table->addColumn('last_resource_title', 'varchar', 100)->setDefault('');
			$table->addColumn('last_resource_id', 'int')->setDefault(0);
			$table->addColumn('field_cache', 'mediumblob');
			$table->addColumn('review_field_cache', 'mediumblob');
			$table->addColumn('prefix_cache', 'mediumblob');
			$table->addColumn('require_prefix', 'tinyint')->setDefault(0);
			$table->addColumn('thread_node_id', 'int')->setDefault(0);
			$table->addColumn('thread_prefix_id', 'int')->setDefault(0);
			$table->addColumn('allow_local', 'tinyint')->setDefault(0);
			$table->addColumn('allow_external', 'tinyint')->setDefault(0);
			$table->addColumn('allow_commercial_external', 'tinyint')->setDefault(0);
			$table->addColumn('allow_fileless', 'tinyint')->setDefault(0);
			$table->addColumn('always_moderate_create', 'tinyint')->setDefault(0);
			$table->addColumn('always_moderate_update', 'tinyint')->setDefault(0);
			$table->addColumn('min_tags', 'smallint')->setDefault(0);
			$table->addColumn('enable_versioning', 'tinyint')->setDefault(1);
			$table->addColumn('enable_support_url', 'tinyint')->setDefault(1);
			$table->addColumn('auto_feature', 'tinyint')->setDefault(0);
			$table->addKey(['parent_category_id', 'lft']);
			$table->addKey(['lft', 'rgt']);
		};

		$tables['xf_rm_category_field'] = function (Create $table)
		{
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('resource_category_id', 'int');
			$table->addPrimaryKey(['field_id', 'resource_category_id']);
			$table->addKey('resource_category_id');
		};

		$tables['xf_rm_category_prefix'] = function (Create $table)
		{
			$table->addColumn('resource_category_id', 'int');
			$table->addColumn('prefix_id', 'int');
			$table->addPrimaryKey(['resource_category_id', 'prefix_id']);
			$table->addKey('prefix_id');
		};

		$tables['xf_rm_category_review_field'] = function (Create $table)
		{
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('resource_category_id', 'int');
			$table->addPrimaryKey(['field_id', 'resource_category_id']);
			$table->addKey('resource_category_id');
		};

		$tables['xf_rm_category_watch'] = function (Create $table)
		{
			$table->addColumn('user_id', 'int');
			$table->addColumn('resource_category_id', 'int');
			$table->addColumn('notify_on', 'enum')->values(['','resource','update']);
			$table->addColumn('send_alert', 'tinyint');
			$table->addColumn('send_email', 'tinyint');
			$table->addColumn('include_children', 'tinyint');
			$table->addPrimaryKey(['user_id', 'resource_category_id']);
			$table->addKey(['resource_category_id', 'notify_on'], 'node_id_notify_on');
		};

		$tables['xf_rm_resource'] = function (Create $table)
		{
			$table->addColumn('resource_id', 'int')->autoIncrement();
			$table->addColumn('title', 'varchar', 100)->setDefault('');
			$table->addColumn('tag_line', 'varchar', 100)->setDefault('');
			$table->addColumn('user_id', 'int');
			$table->addColumn('username', 'varchar', 100)->setDefault('');
			$table->addColumn('team_member_user_ids', 'blob');
			$table->addColumn('resource_state', 'enum')->values(['visible','moderated','deleted'])->setDefault('visible');
			$table->addColumn('resource_type', 'varchar', 25);
			$table->addColumn('resource_date', 'int');
			$table->addColumn('resource_category_id', 'int');
			$table->addColumn('current_version_id', 'int');
			$table->addColumn('description_update_id', 'int')->comment('Points to the resource update that acts as the description for this resource');
			$table->addColumn('discussion_thread_id', 'int')->comment('Points to an automatically-created thread for this resource');
			$table->addColumn('external_url', 'varchar', 500)->setDefault('');
			$table->addColumn('external_purchase_url', 'varchar', 500)->setDefault('');
			$table->addColumn('price', 'decimal', '10,2')->setDefault(0.00);
			$table->addColumn('currency', 'varchar', 3)->setDefault('');
			$table->addColumn('view_count', 'int')->setDefault(0);
			$table->addColumn('download_count', 'int')->setDefault(0);
			$table->addColumn('rating_count', 'int')->setDefault(0);
			$table->addColumn('rating_sum', 'int')->setDefault(0);
			$table->addColumn('rating_avg', 'float', '')->setDefault(0);
			$table->addColumn('rating_weighted', 'float', '')->setDefault(0);
			$table->addColumn('rating_split', 'blob')->nullable();
			$table->addColumn('update_count', 'int')->setDefault(0);
			$table->addColumn('review_count', 'int')->setDefault(0);
			$table->addColumn('last_update', 'int');
			$table->addColumn('alt_support_url', 'varchar', 500)->setDefault('');
			$table->addColumn('custom_fields', 'mediumblob');
			$table->addColumn('prefix_id', 'int')->setDefault(0);
			$table->addColumn('icon_date', 'int')->setDefault(0);
			$table->addColumn('icon_optimized', 'tinyint')->setDefault(0);
			$table->addColumn('tags', 'mediumblob');
			$table->addColumn('featured', 'tinyint')->setDefault(0);
			$table->addKey(['resource_category_id', 'last_update'], 'category_last_update');
			$table->addKey(['resource_category_id', 'rating_weighted'], 'category_rating_weighted');
			$table->addKey('last_update');
			$table->addKey('rating_weighted');
			$table->addKey(['user_id', 'last_update']);
			$table->addKey('discussion_thread_id');
			$table->addKey('prefix_id');
		};

		$tables['xf_rm_resource_update'] = function (Create $table)
		{
			$table->addColumn('resource_update_id', 'int', 11)->autoIncrement();
			$table->addColumn('resource_id', 'int');
			$table->addColumn('team_user_id', 'int');
			$table->addColumn('team_username', 'varchar', 50);
			$table->addColumn('title', 'varchar', 100)->setDefault('')->comment('Title field is optional, and is not used in the first post.');
			$table->addColumn('message', 'mediumtext')->comment('Supports BB code');
			$table->addColumn('message_state', 'enum')->values(['visible','moderated','deleted'])->setDefault('visible');
			$table->addColumn('post_date', 'int');
			$table->addColumn('attach_count', 'int')->setDefault(0);
			$table->addColumn('reaction_score', 'int')->unsigned(false)->setDefault(0);
			$table->addColumn('reactions', 'blob')->nullable();
			$table->addColumn('reaction_users', 'blob');
			$table->addColumn('ip_id', 'int')->setDefault(0);
			$table->addColumn('warning_id', 'int')->setDefault(0);
			$table->addColumn('warning_message', 'varchar', 255)->setDefault('');
			$table->addColumn('last_edit_date', 'int')->setDefault(0);
			$table->addColumn('last_edit_user_id', 'int')->setDefault(0);
			$table->addColumn('edit_count', 'int')->setDefault(0);
			$table->addColumn('embed_metadata', 'blob')->nullable();
			$table->addKey(['resource_id', 'post_date']);
			$table->addKey('team_user_id');
		};

		$tables['xf_rm_resource_download'] = function (Create $table)
		{
			$table->addColumn('resource_download_id', 'int')->autoIncrement();
			$table->addColumn('resource_version_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addColumn('resource_id', 'int');
			$table->addColumn('last_download_date', 'int');
			$table->addUniqueKey(['resource_version_id', 'user_id'], 'version_user');
			$table->addKey(['user_id', 'resource_id'], 'user_resource');
		};

		$tables['xf_rm_resource_field'] = function (Create $table)
		{
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('display_group', 'varchar', 25)->setDefault('above_info');
			$table->addColumn('display_order', 'int')->setDefault(1);
			$table->addColumn('field_type', 'varbinary', 25)->setDefault('textbox');
			$table->addColumn('field_choices', 'blob');
			$table->addColumn('match_type', 'varbinary', 25)->setDefault('none');
			$table->addColumn('match_params', 'blob');
			$table->addColumn('max_length', 'int')->setDefault(0);
			$table->addColumn('required', 'tinyint')->setDefault(0);
			$table->addColumn('display_template', 'text');
			$table->addColumn('wrapper_template', 'text');
			$table->addColumn('viewable_resource', 'tinyint')->setDefault(1);
			$table->addPrimaryKey('field_id');
			$table->addKey(['display_group', 'display_order'], 'display_group_order');
		};

		$tables['xf_rm_resource_field_value'] = function (Create $table)
		{
			$table->addColumn('resource_id', 'int');
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('field_value', 'mediumtext');
			$table->addPrimaryKey(['resource_id', 'field_id']);
			$table->addKey('field_id');
		};

		$tables['xf_rm_resource_review_field'] = function (Create $table)
		{
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('display_group', 'varchar', 25)->setDefault('above_info');
			$table->addColumn('display_order', 'int')->setDefault(1);
			$table->addColumn('field_type', 'varbinary', 25)->setDefault('textbox');
			$table->addColumn('field_choices', 'blob');
			$table->addColumn('match_type', 'varbinary', 25)->setDefault('none');
			$table->addColumn('match_params', 'blob');
			$table->addColumn('max_length', 'int')->setDefault(0);
			$table->addColumn('required', 'tinyint')->setDefault(0);
			$table->addColumn('display_template', 'text');
			$table->addColumn('wrapper_template', 'text');
			$table->addPrimaryKey('field_id');
			$table->addKey(['display_group', 'display_order'], 'display_group_order');
		};

		$tables['xf_rm_resource_review_field_value'] = function (Create $table)
		{
			$table->addColumn('resource_rating_id', 'int');
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('field_value', 'mediumtext');
			$table->addPrimaryKey(['resource_rating_id', 'field_id']);
			$table->addKey('field_id');
		};

		$tables['xf_rm_resource_prefix'] = function (Create $table)
		{
			$table->addColumn('prefix_id', 'int')->autoIncrement();
			$table->addColumn('prefix_group_id', 'int');
			$table->addColumn('display_order', 'int');
			$table->addColumn('materialized_order', 'int')->comment('Internally-set order, based on prefix_group.display_order, prefix.display_order');
			$table->addColumn('css_class', 'varchar', 50)->setDefault('');
			$table->addColumn('allowed_user_group_ids', 'blob');
			$table->addKey('materialized_order');
		};

		$tables['xf_rm_resource_prefix_group'] = function (Create $table)
		{
			$table->addColumn('prefix_group_id', 'int')->autoIncrement();
			$table->addColumn('display_order', 'int');
		};

		$tables['xf_rm_resource_rating'] = function (Create $table)
		{
			$table->addColumn('resource_rating_id', 'int')->autoIncrement();
			$table->addColumn('resource_version_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addColumn('rating', 'tinyint');
			$table->addColumn('rating_date', 'int');
			$table->addColumn('message', 'mediumtext');
			$table->addColumn('resource_id', 'int');
			$table->addColumn('version_string', 'varchar', 50);
			$table->addColumn('author_response_team_user_id', 'int')->setDefault(0);
			$table->addColumn('author_response_team_username', 'varchar', 50)->setDefault('');
			$table->addColumn('author_response', 'mediumtext');
			$table->addColumn('is_review', 'tinyint')->setDefault(0);
			$table->addColumn('count_rating', 'tinyint')->setDefault(1)->comment('Whether this counts towards the global resource rating.');
			$table->addColumn('rating_state', 'enum')->values(['visible','deleted'])->setDefault('visible');
			$table->addColumn('warning_id', 'int')->setDefault(0);
			$table->addColumn('is_anonymous', 'tinyint')->setDefault(0);
			$table->addColumn('custom_fields', 'mediumblob');
			$table->addColumn('vote_score', 'int')->unsigned(false);
			$table->addColumn('vote_count', 'int')->setDefault(0);
			$table->addUniqueKey(['resource_version_id', 'user_id'], 'version_user_id');
			$table->addKey('user_id');
			$table->addKey(['count_rating', 'resource_id']);
			$table->addKey(['resource_id', 'rating_date']);
			$table->addKey('rating_date');
			$table->addKey('author_response_team_user_id');
		};

		$tables['xf_rm_resource_team_member'] = function (Create $table)
		{
			$table->addColumn('resource_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addPrimaryKey(['resource_id', 'user_id']);
			$table->addKey('user_id');
		};

		$tables['xf_rm_resource_version'] = function (Create $table)
		{
			$table->addColumn('resource_version_id', 'int')->autoIncrement();
			$table->addColumn('resource_id', 'int');
			$table->addColumn('team_user_id', 'int');
			$table->addColumn('team_username', 'varchar', 50);
			$table->addColumn('version_string', 'varchar', 50);
			$table->addColumn('release_date', 'int');
			$table->addColumn('download_url', 'varchar', 250)->setDefault('');
			$table->addColumn('download_count', 'int')->setDefault(0);
			$table->addColumn('rating_count', 'int')->setDefault(0);
			$table->addColumn('rating_sum', 'int')->setDefault(0);
			$table->addColumn('version_state', 'enum')->values(['visible','moderated','deleted'])->setDefault('visible');
			$table->addColumn('file_count', 'smallint')->setDefault(0);
			$table->addKey(['resource_id', 'release_date']);
			$table->addKey('team_user_id');
		};

		$tables['xf_rm_resource_view'] = function (Create $table)
		{
			$table->addColumn('resource_id', 'int');
			$table->addColumn('total', 'int');
			$table->addPrimaryKey('resource_id');
		};

		$tables['xf_rm_resource_watch'] = function (Create $table)
		{
			$table->addColumn('user_id', 'int');
			$table->addColumn('resource_id', 'int');
			$table->addColumn('email_subscribe', 'tinyint')->setDefault(0);
			$table->addPrimaryKey(['user_id', 'resource_id']);
			$table->addKey(['resource_id', 'email_subscribe']);
		};

		return $tables;
	}

	public function getData(): array
	{
		$data = [];

		$data['xf_rm_category'] = "
			REPLACE INTO `xf_rm_category`
				(`resource_category_id`,
				`title`,
				`description`,
				`parent_category_id`, `depth`, `lft`, `rgt`, `display_order`,
				`resource_count`, `last_update`, `last_resource_title`, `last_resource_id`, `breadcrumb_data`,
				`allow_local`, `allow_external`, `allow_commercial_external`, `allow_fileless`,
				`thread_node_id`, `thread_prefix_id`,
				`always_moderate_create`, `always_moderate_update`,
				field_cache, prefix_cache, review_field_cache)
			VALUES
				(1,
				'Example category',
				'This is an example resource manager category. You can manage the resource manager categories via the Admin control panel. From there, you can setup more categories or change the resource manager options.',
				0, 0, 1, 2, 1,
				0, 0, '', 0, '[]',
				1, 1, 1, 1,
				0, 0,
				0, 0,
				'', '', '');
		";

		$data['xf_admin_permission_entry'] = "
			REPLACE INTO xf_admin_permission_entry
				(user_id, admin_permission_id)
			SELECT user_id, 'resourceManager'
			FROM xf_admin_permission_entry
			WHERE admin_permission_id = 'node'
		";

		return $data;
	}
}
