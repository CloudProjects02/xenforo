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
trait UpgradeLegacyTrait
{
	/**
	 *
	 */
	public function upgrade20160202Step1()
	{
		$sm = $this->schemaManager();

		$this->db()->emptyTable('xf_dbtech_shop_shoppingcart');

		foreach (['prepurchase', 'postpurchase', 'sellback', 'configure', 'gift', 'discard'] as $key)
		{
			$sm->alterTable('xf_dbtech_shop_item', function (Alter $table) use ($key)
			{
				$table->addColumn($key . '_callback_class', 'varchar', 75)->setDefault('');
				$table->addColumn($key . '_callback_method', 'varchar', 50)->setDefault('');
			});
		}

		$sm->alterTable('xf_dbtech_shop_shoppingcart', function (Alter $table)
		{
			$table->dropPrimaryKey();
			$table->dropColumns(['items']);
			$table->addColumn('shopid', 'int')->after('userid');
			$table->addColumn('itemid', 'int')->after('shopid');
			$table->addColumn('quantity', 'int')->setDefault(0)->after('itemid');
			$table->addPrimaryKey(['userid', 'shopid', 'itemid']);
		});

		$sm->alterTable('xf_dbtech_shop_purchase', function (Alter $table)
		{
			$table->addColumn('expirydate', 'int')->setDefault(0);
		});

		$sm->alterTable('xf_user', function (Alter $table)
		{
			$table->addColumn('dbtech_shop_purchases', 'int')->setDefault(0);
		});
	}

	/**
	 *
	 */
	public function upgrade20160202Step2()
	{
		$this->query("
			UPDATE `xf_dbtech_shop_purchase`
				SET `expirydate` = 1
		");

		$this->query("
			UPDATE `xf_user`
				SET `dbtech_shop_purchase` = NULL
		");

		$this->query("
			UPDATE `xf_user`
				SET `dbtech_shop_purchases` = (
					SELECT COUNT(*)
					FROM `xf_dbtech_shop_purchase`
					WHERE `userid` = `xf_user`.`user_id`
				)
		");
	}

	/**
	 *
	 */
	public function upgrade20160209Step1()
	{
		foreach ([
			'threadhighlight' 	=> [
				'title' 		=> 'Thread Highlight',
				'description' 	=> 'Purchase the ability to highlight a thread on forum display.',
			],
			'postbithighlight' 	=> [
				'title' 		=> 'Postbit Highlight',
				'description' 	=> 'Purchase the ability to highlight your postbit on show thread.',
			],
		] as $filename => $info)
		{
			$this->query("
				REPLACE INTO `xf_dbtech_shop_itemtype`
					(`itemtypeid`, `title`, `description`)
				VALUES (
					" . $this->db()->quote($filename) . ",
					" . $this->db()->quote($info['title']) . ",
					" . $this->db()->quote($info['description']) . "
				)
			");
		}
	}

	/**
	 *
	 */
	public function upgrade20160308Step1()
	{
		foreach ([
			'threadhighlight2' 	=> [
				'title' 		=> 'Thread Highlight (Pre-Defined)',
				'description' 	=> 'Purchase the ability to highlight a thread on forum display.',
			],
			'postbithighlight2' => [
				'title' 		=> 'Postbit Highlight (Pre-Defined)',
				'description' 	=> 'Purchase the ability to highlight your postbit on show thread.',
			],
		] as $filename => $info)
		{
			$this->query("
				REPLACE INTO `xf_dbtech_shop_itemtype`
					(`itemtypeid`, `title`, `description`)
				VALUES (
					" . $this->db()->quote($filename) . ",
					" . $this->db()->quote($info['title']) . ",
					" . $this->db()->quote($info['description']) . "
				)
			");
		}

		// Purge the cache
		\XF::registry()->delete([
			'dbtech_shop_itemtype',
		]);
	}

	/**
	 *
	 */
	public function upgrade20160322Step1()
	{
		$this->query("
			UPDATE `xf_option`
				SET `option_value` = 'a:3:{s:7:\"enabled\";s:1:\"1\";s:13:\"left_position\";s:3:\"end\";s:14:\"right_position\";b:0;}'
				WHERE `option_id` = 'dbtech_shop_navbar'
		");
	}

	/**
	 *
	 */
	public function upgrade20160328Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_currency', function (Alter $table)
		{
			$table->dropColumns(['bitfield']);
			$table->changeColumn('description', 'blob')->nullable(true);
			$table->changeColumn('pointstable', 'varchar', 255)->setDefault('')->renameTo('table');
			$table->addColumn('useprefix', 'tinyint', 3)->setDefault(1)->after('table');
			$table->changeColumn('pointscolumn', 'varchar', 255)->setDefault('')->renameTo('column')->after('useprefix');
			$table->addColumn('userid', 'tinyint', 3)->setDefault(1)->after('column');
			$table->addColumn('usercol', 'varchar', 255)->setDefault('user_id')->after('userid');
			$table->changeColumn('rounding', 'tinyint', 2)->setDefault(0)->renameTo('decimals')->after('usercol');
			$table->changeColumn('privacy', 'tinyint', 3)->setDefault(2)->after('decimals');
			$table->addColumn('blacklist', 'tinyint', 3)->setDefault(0)->after('privacy');
			$table->addColumn('prefix', 'varchar', 50)->setDefault('')->after('blacklist');
			$table->addColumn('suffix', 'varchar', 50)->setDefault('')->after('prefix');
		});
	}

	/**
	 *
	 */
	public function upgrade20160328Step2()
	{
		$this->query("
			UPDATE `xf_dbtech_shop_currency`
				SET `privacy` = 2
		");

		// Purge the cache
		\XF::registry()->delete([
			'dbtech_shop_currency',
		]);
	}

	/**
	 *
	 */
	public function upgrade20160329Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_user', function (Alter $table)
		{
			$table->changeColumn('dbtech_shop_points', 'double')->unsigned(false)->setDefault(0);
		});
	}

	/**
	 *
	 */
	public function upgrade20160403Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_item', function (Alter $table)
		{
			$table->addColumn('customshops', 'tinyint', 3)->setDefault(0)->after('threadpage');
			$table->addColumn('stealthed', 'tinyint', 3)->setDefault(0)->after('customshops');
		});
	}

	/**
	 *
	 */
	public function upgrade20160408Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_lottery', function (Alter $table)
		{
			$table->addColumn('ticketssold', 'int')->setDefault(0);
		});
	}

	/**
	 *
	 */
	public function upgrade20160408Step2()
	{
		$this->query("
			UPDATE xf_dbtech_shop_lottery AS lottery
			SET `ticketssold` = (
				SELECT COUNT(*) FROM `xf_dbtech_shop_lotteryticket` WHERE `lotteryid` = `lottery`.`lotteryid`
			)
		");
	}

	/**
	 *
	 */
	public function upgrade20160410Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_currency', function (Alter $table)
		{
			$table->addColumn('creditscurrencyid', 'int')->setDefault(0);
		});
	}

	/**
	 *
	 */
	public function upgrade20160418Step1()
	{
		$sm = $this->schemaManager();

		$sm->createTable('xf_dbtech_shop_lotteryticket', function (Create $table)
		{
			$table->addColumn('lotteryticketid', 'int')->autoIncrement();
			$table->addColumn('lotteryid', 'int')->setDefault(0);
			$table->addColumn('userid', 'int')->setDefault(0);
			$table->addColumn('lotterydraw', 'int')->setDefault(0);
			$table->addColumn('numbers', 'mediumblob')->nullable(true);
			$table->addColumn('prizeid', 'int')->setDefault(0);
			$table->addKey(['lotteryid', 'userid'], 'lotteryid');
			$table->addKey(['lotteryid', 'lotterydraw'], 'lotteryid_2');
		});
	}

	/**
	 *
	 */
	public function upgrade20160426Step1()
	{
		$this->query("
			UPDATE `xf_dbtech_shop_category`
				SET `permissions` = NULL
		");

		$this->query("
			UPDATE `xf_dbtech_shop_item`
				SET `permissions` = NULL
		");

		$this->query("
			UPDATE `xf_dbtech_shop_shop`
				SET `permissions` = NULL
		");

		// Purge the cache
		\XF::registry()->delete([
			'dbtech_shop_category',
			'dbtech_shop_item',
			'dbtech_shop_shop',
		]);
	}

	/**
	 *
	 */
	public function upgrade20160430Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_lotteryticket', function (Create $table)
		{
			$table->addColumn('dateline', 'int')->setDefault(0)->after('userid');
		});
	}

	/**
	 *
	 */
	public function upgrade20160430Step2()
	{
		$this->query("
			UPDATE `xf_dbtech_shop_lotteryticket`
				SET `dateline` = `lotterydraw`
		");
	}

	/**
	 *
	 */
	public function upgrade20160508Step1()
	{
		$this->query("
			UPDATE `xf_dbtech_shop_item`
				SET `icon` = IF(SUBSTRING(`icon`, 1, 6) = 'items/', CONCAT('styles/DBTech/Shop/', `icon`), `icon`)
		");

		$this->query("
			UPDATE `xf_dbtech_shop_item`
				SET `shopicon` = IF(SUBSTRING(`shopicon`, 1, 6) = 'items/', CONCAT('styles/DBTech/Shop/', `shopicon`), `shopicon`)
		");

		// Purge the cache
		\XF::registry()->delete([
			'dbtech_shop_item',
		]);
	}

	/**
	 *
	 */
	public function upgrade20160517Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_bank', function (Alter $table)
		{
			$table->changeColumn('points', 'double')->unsigned(false)->setDefault(0);
		});

		// Purge the cache
		\XF::registry()->delete([
			'dbtech_shop_shop',
		]);
	}

	/**
	 *
	 */
	public function upgrade20160519Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_feedback', function (Alter $table)
		{
			$table->addColumn('shopid', 'int', 10)->setDefault(0)->after('userid');
			$table->addColumn('numratings', 'int', 10)->setDefault(0)->after('beneficiary_split');
		});
	}

	/**
	 *
	 */
	public function upgrade20160623Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_user', function (Alter $table)
		{
			$table->addColumn('dbtech_shop_pendingtrades', 'int')->setDefault(0)->after('dbtech_shop_purchases');
		});
	}

	/**
	 *
	 */
	public function upgrade20160703Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_transactionlog', function (Alter $table)
		{
			$table->changeColumn('ipaddress', 'varchar', 45)->setDefault('');
		});
	}

	/**
	 *
	 */
	public function upgrade20160722Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_item', function (Alter $table)
		{
			$table->addColumn('exclusiveitem', 'tinyint', 3)->setDefault(0);
		});
	}

	/**
	 *
	 */
	public function upgrade20160724Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_itemtype', function (Alter $table)
		{
			$table->addColumn('item_class', 'varchar', 75)->setDefault('');
		});
	}

	/**
	 *
	 */
	public function upgrade20160724Step2()
	{
		foreach ([
			'autobump' 			=> 'DBTech_Shop_Item_AutoBump',
			'avatarchange' 		=> 'DBTech_Shop_Item_AvatarChange',
			'createforum' 		=> 'DBTech_Shop_Item_Forum_Create',
			'custom' 			=> 'DBTech_Shop_Item_Custom',
			'customicon' 		=> 'DBTech_Shop_Item_CustomIcon',
			'deletethread' 		=> 'DBTech_Shop_Item_DeleteThread',
			'firemoderator' 	=> 'DBTech_Shop_Item_FireModerator',
			'forumdescription' 	=> 'DBTech_Shop_Item_Forum_Description',
			'forumpassword' 	=> 'DBTech_Shop_Item_ForumPassword',
			'forumpermission' 	=> 'DBTech_Shop_Item_Permission_Forum',
			'immunity' 			=> 'DBTech_Shop_Item_Immunity',
			'intpermission' 	=> 'DBTech_Shop_Item_Permission_Int',
			'moderateforum' 	=> 'DBTech_Shop_Item_Forum_Moderate',
			'movethread' 		=> 'DBTech_Shop_Item_MoveThread',
			'permission' 		=> 'DBTech_Shop_Item_Permission_Value',
			'postbithighlight' 	=> 'DBTech_Shop_Item_Style_Highlight_Postbit',
			'postbithighlight2' => 'DBTech_Shop_Item_Style_Highlight_Postbit_PreDefined',
			'poststyle' 		=> 'DBTech_Shop_Item_Style_Post',
			'poststyle2' 		=> 'DBTech_Shop_Item_Style_Post_PreDefined',
			'profilemusic' 		=> 'DBTech_Shop_Item_ProfileMusic',
			'signaturechange' 	=> 'DBTech_Shop_Item_SignatureChange',
			'smilie' 			=> 'DBTech_Shop_Item_Smilie',
			'smiliecategory' 	=> 'DBTech_Shop_Item_SmilieCategory',
			'stealchance' 		=> 'DBTech_Shop_Item_Steal_Chance',
			'stealmore' 		=> 'DBTech_Shop_Item_Steal_Amount',
			'sticky' 			=> 'DBTech_Shop_Item_Sticky',
			'threadban' 		=> 'DBTech_Shop_Item_ThreadBan',
			'threadbump' 		=> 'DBTech_Shop_Item_ThreadBump',
			'threadhighlight' 	=> 'DBTech_Shop_Item_Style_Highlight_Thread',
			'threadhighlight2' 	=> 'DBTech_Shop_Item_Style_Highlight_Thread_PreDefined',
			'threadtitlestyle' 	=> 'DBTech_Shop_Item_Style_ThreadTitle',
			'threadtitlestyle2' => 'DBTech_Shop_Item_Style_ThreadTitle_PreDefined',
			'usergroupchange' 	=> 'DBTech_Shop_Item_Change_UserGroup',
			'usernamechange' 	=> 'DBTech_Shop_Item_Change_UserName',
			'usernamestyle' 	=> 'DBTech_Shop_Item_Style_UserName',
			'usernamestyle2' 	=> 'DBTech_Shop_Item_Style_UserName_PreDefined',
			'usertitlechange' 	=> 'DBTech_Shop_Item_Change_UserTitle',
			'usertitlechange2' 	=> 'DBTech_Shop_Item_Change_UserTitle_PreDefined',
			'usertitlestyle' 	=> 'DBTech_Shop_Item_Style_UserTitle',
			'usertitlestyle2' 	=> 'DBTech_Shop_Item_Style_UserTitle_PreDefined',
		] as $itemTypeId => $itemClass)
		{
			$this->query(
				"
				UPDATE `xf_dbtech_shop_itemtype`
					SET `item_class` = " . $this->db()->quote($itemClass) . "
					WHERE `itemtypeid` = " . $this->db()->quote($itemTypeId)
			);
		}

		// Purge the cache
		\XF::registry()->delete([
			'dbtech_shop_itemtype',
		]);
	}

	/**
	 *
	 */
	public function upgrade20160726Step1()
	{
		foreach ([
			'autobump' 			=> 'DBTech_Shop_Item_AutoBump',
			'avatarchange' 		=> 'DBTech_Shop_Item_AvatarChange',
			'createforum' 		=> 'DBTech_Shop_Item_Forum_Create',
			'custom' 			=> 'DBTech_Shop_Item_Custom',
			'customicon' 		=> 'DBTech_Shop_Item_CustomIcon',
			'deletethread' 		=> 'DBTech_Shop_Item_DeleteThread',
			'firemoderator' 	=> 'DBTech_Shop_Item_FireModerator',
			'forumdescription' 	=> 'DBTech_Shop_Item_Forum_Description',
			'forumpassword' 	=> 'DBTech_Shop_Item_ForumPassword',
			'forumpermission' 	=> 'DBTech_Shop_Item_Permission_Forum',
			'immunity' 			=> 'DBTech_Shop_Item_Immunity',
			'intpermission' 	=> 'DBTech_Shop_Item_Permission_Int',
			'moderateforum' 	=> 'DBTech_Shop_Item_Forum_Moderate',
			'movethread' 		=> 'DBTech_Shop_Item_MoveThread',
			'permission' 		=> 'DBTech_Shop_Item_Permission_Value',
			'postbithighlight' 	=> 'DBTech_Shop_Item_Style_Highlight_Postbit',
			'postbithighlight2' => 'DBTech_Shop_Item_Style_Highlight_Postbit_PreDefined',
			'poststyle' 		=> 'DBTech_Shop_Item_Style_Post',
			'poststyle2' 		=> 'DBTech_Shop_Item_Style_Post_PreDefined',
			'profilemusic' 		=> 'DBTech_Shop_Item_ProfileMusic',
			'signaturechange' 	=> 'DBTech_Shop_Item_SignatureChange',
			'smilie' 			=> 'DBTech_Shop_Item_Smilie',
			'smiliecategory' 	=> 'DBTech_Shop_Item_SmilieCategory',
			'stealchance' 		=> 'DBTech_Shop_Item_Steal_Chance',
			'stealmore' 		=> 'DBTech_Shop_Item_Steal_Amount',
			'sticky' 			=> 'DBTech_Shop_Item_Sticky',
			'threadban' 		=> 'DBTech_Shop_Item_ThreadBan',
			'threadbump' 		=> 'DBTech_Shop_Item_ThreadBump',
			'threadhighlight' 	=> 'DBTech_Shop_Item_Style_Highlight_Thread',
			'threadhighlight2' 	=> 'DBTech_Shop_Item_Style_Highlight_Thread_PreDefined',
			'threadtitlestyle' 	=> 'DBTech_Shop_Item_Style_ThreadTitle',
			'threadtitlestyle2' => 'DBTech_Shop_Item_Style_ThreadTitle_PreDefined',
			'usergroupchange' 	=> 'DBTech_Shop_Item_Change_UserGroup',
			'usernamechange' 	=> 'DBTech_Shop_Item_Change_UserName',
			'usernamestyle' 	=> 'DBTech_Shop_Item_Style_UserName',
			'usernamestyle2' 	=> 'DBTech_Shop_Item_Style_UserName_PreDefined',
			'usertitlechange' 	=> 'DBTech_Shop_Item_Change_UserTitle',
			'usertitlechange2' 	=> 'DBTech_Shop_Item_Change_UserTitle_PreDefined',
			'usertitlestyle' 	=> 'DBTech_Shop_Item_Style_UserTitle',
			'usertitlestyle2' 	=> 'DBTech_Shop_Item_Style_UserTitle_PreDefined',
		] as $itemTypeId => $itemClass)
		{
			$this->query(
				"
				UPDATE `xf_dbtech_shop_itemtype`
					SET `item_class` = " . $this->db()->quote($itemClass) . "
					WHERE `itemtypeid` = " . $this->db()->quote($itemTypeId)
			);
		}

		// Purge the cache
		\XF::registry()->delete([
			'dbtech_shop_itemtype',
		]);
	}

	/**
	 *
	 */
	public function upgrade20160729Step1()
	{
		$sm = $this->schemaManager();

		$sm->createTable('xf_dbtech_shop_trade', function (Create $table)
		{
			$table->addColumn('tradeid', 'int')->autoIncrement();
			$table->addColumn('user1', 'int')->setDefault(0);
			$table->addColumn('user2', 'int')->setDefault(0);
			$table->addColumn('created_date', 'int')->setDefault(0);
			$table->addColumn('updated_date', 'int')->setDefault(0);
			$table->addColumn('status', 'enum')->values(['pending','open','awaiting_accept','accepted','cancelled'])->setDefault('pending');
			$table->addColumn('user1_accepted', 'tinyint', 3)->setDefault(0);
			$table->addColumn('user2_accepted', 'tinyint', 3)->setDefault(0);
			$table->addColumn('conversation_id', 'int')->setDefault(0);
			$table->addKey('user1');
			$table->addKey('user2');
		});

		$sm->createTable('xf_dbtech_shop_trade_offer', function (Create $table)
		{
			$table->addColumn('tradeid', 'int');
			$table->addColumn('userid', 'int')->setDefault(0);
			$table->addColumn('feature', 'enum')->values(['item','currency'])->setDefault('item');
			$table->addColumn('featureid', 'int');
			$table->addColumn('quantity', 'int')->setDefault(0);
			$table->addPrimaryKey(['tradeid', 'userid', 'feature', 'featureid']);
		});
	}

	/**
	 *
	 */
	public function upgrade20160730Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_currency', function (Alter $table)
		{
			$table->addColumn('cantrade', 'tinyint', 3)->setDefault(1)->after('cansteal');
		});

		// Purge the cache
		\XF::registry()->delete([
			'dbtech_shop_currency',
		]);
	}

	/**
	 *
	 */
	public function upgrade20160928Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_currency', function (Alter $table)
		{
			$table->addColumn('displaycurrency', 'tinyint', 3)->setDefault(0)->after('suffix');
		});

		// Purge the cache
		\XF::registry()->delete([
			'dbtech_shop_currency',
		]);
	}

	/**
	 *
	 */
	public function upgrade20161005Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_item', function (Alter $table)
		{
			$table->addColumn('autodiscard', 'tinyint', 3)->setDefault(0)->after('exclusiveitem');
		});

		// Purge the cache
		\XF::registry()->delete([
			'dbtech_shop_item',
		]);
	}

	/**
	 *
	 */
	public function upgrade20161012Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_item', function (Alter $table)
		{
			$table->addColumn('autodiscard_expiry', 'tinyint', 3)->setDefault(0)->after('autodiscard');
		});

		// Purge the cache
		\XF::registry()->delete([
			'dbtech_shop_item',
		]);
	}

	/**
	 *
	 */
	public function upgrade20161019Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_shopinventory', function (Alter $table)
		{
			$table->addColumn('buybackcurrencyid', 'int')->setDefault(0)->after('price');
			$table->addColumn('dateline', 'int')->setDefault(0)->after('active');
		});
	}

	/**
	 *
	 */
	public function upgrade20161019Step2()
	{
		$this->query("
			UPDATE `xf_dbtech_shop_shopinventory`
				SET `buybackcurrencyid` = `currencyid`
		");

		$this->query("
			UPDATE `xf_dbtech_shop_shopinventory`
				SET `dateline` = UNIX_TIMESTAMP()
		");
	}

	/**
	 *
	 */
	public function upgrade20161122Step1()
	{
		$this->query("
			INSERT INTO `xf_dbtech_shop_itemtype`
				(`itemtypeid`, `title`, `description`, `active`, `item_class`)
			VALUES
				('postbackground', 'Post Background', X'507572636861736520746865206162696C69747920746F206368616E676520746865206261636B67726F756E64206F6620796F757220706F7374732E', 1, 'DBTech_Shop_Item_Style_PostBackground')
		");

		$this->query("
			INSERT INTO `xf_dbtech_shop_itemtype`
				(`itemtypeid`, `title`, `description`, `active`, `item_class`)
			VALUES
				('postbackground2', 'Post Background (Pre-Defined)', X'507572636861736520746865206162696C69747920746F206368616E676520746865206261636B67726F756E64206F6620796F757220706F73747320746F2061207072652D646566696E6564207374796C652E', 1, 'DBTech_Shop_Item_Style_PostBackground_PreDefined')
		");

		// Purge the cache
		\XF::registry()->delete([
			'dbtech_shop_itemtype',
		]);
	}

	/**
	 *
	 */
	public function upgrade20161201Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_item', function (Alter $table)
		{
			$table->addColumn('user_criteria', 'mediumblob')->nullable(true)->after('permissions');
		});

		$sm->alterTable('xf_dbtech_shop_purchase', function (Alter $table)
		{
			$table->addColumn('gifted', 'tinyint', 3)->setDefault(0)->after('hidden');
			$table->addColumn('traded', 'tinyint', 3)->setDefault(0)->after('gifted');
		});
	}

	/**
	 *
	 */
	public function upgrade20161201Step2()
	{
		$this->query("
			UPDATE `xf_dbtech_shop_purchase`
			SET `gifted` = 1
			WHERE `userid` != `buyer`
		");
	}

	/**
	 *
	 */
	public function upgrade20170314Step1()
	{
		$this->query("
			UPDATE `xf_dbtech_shop_itemtype`
				SET `item_class` = REPLACE(`item_class`, '_', '\\\')
				WHERE `item_class` LIKE 'DBTech_Shop_%'
		");

		$this->query("
			UPDATE `xf_dbtech_shop_itemtype`
				SET `item_class` = 'DBTech\\\Shop\\\Item\\\Permission\\\IntValue'
				WHERE `itemtypeid` = 'intpermission'
		");

		$this->query("
			UPDATE `xf_dbtech_shop_itemtype`
				SET `item_class` = 'DBTech\\\Shop\\\Item\\\Permission\\\RawValue'
				WHERE `itemtypeid` = 'permission'
		");

		// Purge the cache
		\XF::registry()->delete([
			'dbt_shop_itemtype',
		]);
	}

	/**
	 *
	 */
	public function upgrade806000031Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_currency', function (Alter $table)
		{
			$table->addColumn('sidebar', 'tinyint', 3)->setDefault(1);
		});
	}
}