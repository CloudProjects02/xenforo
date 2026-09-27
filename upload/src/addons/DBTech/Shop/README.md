DragonByte Shop for XenForo 2.3.0+
==================================

![Deploy](https://github.com/DragonByteTech/xf2shop/workflows/Deploy/badge.svg) ![Lint](https://github.com/DragonByteTech/xf2shop/workflows/Lint/badge.svg)  
  
Description
-----------

Members can buy permissions, features and more on your forum with points.

Requirements
------------

- PHP 8.0.0+

Recommendations
---------------

- PHP 8.2.0+
- DragonByte Credits v5.1.0+

Options
-------

#### DragonByte Tech: Shop

| Name | Description |
|---|---|
| Content deletion thread action | When content is deleted, take this action with any automatically created thread. |
| Default item owner | This setting controls who should own items created in the AdminCP. Ideally this should be set to a "bot" user that no-one logs in to. |
| Enable item ratings | If disabled, the entire item rating system will be disabled. |
| Automatic interest: Activity threshold | This controls how many days a user can remain inactive (i.e. not having visited the site) before they are no longer eligible to collect automatic interest.   Setting this value too high may lead to performance issues. |
| Inventory items per page | The number of items that will be listed per page in the user's inventory. |
| Maximum item icon dimensions | The maximum allowed dimensions for item icon images (width x height). Use 0 or blank to use default dimensions. |
| Items per page | The number of items that will be listed per page across the shop. |
| Default item list order | When viewing the item index or category overview pages, this will be the default order for items. |
| Lotteries per page | The number of lotteries that will be listed per page across the shop. |
| Minimum item review length | This setting has no effect if a review is not required and the user does not enter a review. |
| Minimum wallet balance | This is the minimum amount the user needs in their wallet in order to attempt to steal currency.   For instance, if this is set to 500 and the user tries to steal the currency Points from another user, then the thief needs at least 500 Points in their own wallet in order to attempt the theft. |
| Require purchase to rate items | If selected, users may only rate an item once they have purchased it. |
| Require a review when rating items | If enabled, users must submit a review when rating an item. |
| Reviews per page | The number of reviews that will be listed per page across the shop. |
| Maximum trade message length | The maximum number of characters that can be in a trade post or comment. Use 0 to disable the limit. |
| Trades per page | The number of trades that will be listed per page across the shop. |
| Transactions per page | The transaction log page lists the latest transactions for each user. This is the number of transactions to show before splitting to the next page. |
| Enable bank | This setting lets you control whether the Bank feature should be available. |
| Configuration notification sender | This addon may start a new conversation with users when an item is configured. Please input the username of the user that should start this conversation.      It's generally a good idea to create a "Forum Bot" user that starts these conversations, to indicate they are automated messages. |
| Postbit display: Show gift giver in tooltip | Enabling this setting will display the person who gifted a user an item if applicable. |
| Force formatting | It's possible to have DragonByte Shop force its usergroup style formatting to apply even when insufficient information is supplied.   Note: This has a negative effect on performance! |
| Display items in postbit | Choose the locations in which Items are displayed in the postbit.   Note: Heavily customised skins may render parts of this setting unusable. |
| Enable lotteries | This setting can be used to prevent users from viewing and participating in lotteries. |
| Require manual interest collection | With this setting you can require a member to come back every 24 hours to collect the interest money from the bank. |
| Maximum amount stolen | This setting lets you set a cap for the steal amount (after items that boost steal amount have been taken into account), between 0 and 100%.   Note: Setting this to 0 effectively makes every steal attempt result in 0 points stolen. |
| Maximum success rate | This setting lets you set a cap for the steal percent chance (after items that boost steal chance have been taken into account), between 0 and 100%.   Note: Setting this to 0 effectively makes every steal attempt fail. |
| Maximum penalty for unsuccessful theft | This setting lets you set a cap for the steal penalty (after items that boost steal penalty have been taken into account), between 0 and 100%. |
| Navbar tab |  |
| X newest items | Pick the number of "Newest Items" you wish to be displayed in the postbit. |
| Postbit integration | Choose the locations in which Points are displayed in the postbit.   Note: Heavily customised skins may render parts of this setting unusable. |
| Purchase notification sender | This addon may start a new conversation with users when an item is purchased. Please input the username of the user that should start this conversation.      It's generally a good idea to create a "Forum Bot" user that starts these conversations, to indicate they are automated messages. |
| Base amount stolen | If a member is successful in stealing, they will steal this percentage of the victim's current points (not counting points in the bank), between 0 and 100%.   You can also sell items that boost the percentage of points stolen. |
| Base success rate | This is the percentage chance of successful theft (0-100%).   You can also sell items which raise a member's chance of successfully stealing. |
| Enable stealing | This setting lets you control whether the Steal feature should be available. |
| Penalty for unsuccessful theft | If a member is unsuccessful at stealing, they will lose this amount of their current points (not counting points in their bank), between 0 and 100%. |
| Enable trading system | You can use this setting to globally disable item trading between members. |

#### Debug options (Debug only)

| Name | Description |
|---|---|
| Enable postbit display | If yes, the person's bank balance will also be displayed in the postbit. |
| DragonByte Shop License Key | This is the license key associated with your product. Removing this will affect your ability to use the free "Product Manager" product to check for updates. |

#### Unknown

| Name | Description |
|---|---|
| Enable Profile Block | This setting lets you disable the profile block. |
| Limit "Custom Icon" By Position | This will allow you to limit the total amount of "Custom Icon" icons that can be displayed in each unique display location. |
| (Pro) Override "Can Upload Custom Avatar"? | If yes, this will disallow avatar changes unless members purchases an item for it.      Required for the "Avatar Change" item to function correctly! |
| (Pro) Override "Can Upload Custom Avatar" Exempt Usergroups | Select the usergroup(s) that are exempt from the above override procedure. |
| (Pro) Override "Can Use Signature"? | If yes, this will disallow signature changes unless members purchases an item for it.      Required for the "Signature Change" item to function correctly! |
| (Pro) Override "Can Use Signature" Exempt Usergroups | Select the usergroup(s) that are exempt from the above override procedure. |
| Smilie Upload: Max Height | This is the maximum height (in pixels) for smilie uploads.   0 = No limit |
| Smilie Upload: Max Width | This is the maximum width (in pixels) for smilie uploads.   0 = No limit |

Permissions
-----------

#### DragonByte Shop permissions

- View
- Buy items
- React to items
- Review items
- Create items
- Add items without approval
- Edit own items
- Tag own item
- Tag any item
- Manage tags by others in own item
- Delete own items
- View lottery
- Use bank
- View private currencies
- Steal currency from others
- Trade items and currency

#### DragonByte Shop moderator permissions

- Use inline moderation on items
- View deleted items
- Delete any item
- Undelete items
- View deleted reviews
- Hard-delete any items
- Delete any item reviews
- Edit any items
- Reassign items
- Manage any tags
- View unapproved items
- Approve / unapprove items
- Give warnings on items

#### DragonByte Shop trade post permissions

- View trade posts
- React to trade posts
- Manage trade posts in participating trades
- Post new trade posts
- Comment on trade posts
- Delete own trade posts
- Edit own trade posts

#### DragonByte Shop trade post moderator permissions

- Can use inline moderation on trade posts
- Edit any trade posts
- Delete any trade posts
- Hard-delete any trade posts
- Give warnings on trade posts
- View deleted trade posts
- View unapproved trade posts
- Undelete trade posts
- Approve / unapprove trade posts

Admin Permissions
-----------------

- Manage DragonByte Shop

Style Properties
----------------

#### DragonByte Shop

| Property | Description |
|---|---|
| Enable trade post comment input toggle | If enabled, the input for a trade post comment will only be displayed after the toggle is clicked. |
| Enable Infinite Scroll | Toggles whether infinite scrolling is enabled for this style. |
| Require click to load | Controls whether loading the next page happens automatically, or upon pressing a button. |
| Only require click after X pages | If you want to require click only after a certain amount of pages have loaded, set this here.   0 = Always require click |
| Append to browser history | If selected, new pages loaded will also update the browser's history. |
| Show item owner on overview list |  |
| Item list style | This only affects the category view and the main home page overview. |
| Item rating style | Toggles how an item's rating appears in the sidebar on the item information page. |
| Item Rating Circle: Bar Width | The width of the bar in the item rating circle |
| Item Rating Circle: Background Color | The background color for the item rating circle's bar. |
| Item Rating Circle: Bar Color | The color of the rating circle bar. |

Widget Positions
----------------

| Position | Description |
|---|---|
| DragonByte Shop: Bank sidebar (`dbtech_shop_bank_sidebar`) | Position in the sidebar while viewing the bank. |
| DragonByte Shop: Item category: Sidenav (`dbtech_shop_category_sidenav`) | Displays inside the side navigation on the item category pages. Widget templates rendered in this position can use the current category entity in the `{$context.category}` param. |
| DragonByte Shop: Item page: Sidebar (`dbtech_shop_item_sidebar`) | Displays inside the sidebar on the item page. Widget templates rendered in this position can use the current item entity in the `{$context.item}` param. |
| DragonByte Shop: Lottery sidebar (`dbtech_shop_lottery_sidebar`) | Position in the sidebar while viewing the lotteries. |
| DragonByte Shop: Item overview: Sidenav (`dbtech_shop_overview_sidenav`) | Displays inside the side navigation on the item overview page. |
| DragonByte Shop: Steal sidebar (`dbtech_shop_steal_sidebar`) | Position in the sidebar while viewing the Steal page. |
| DragonByte Shop: Trading sidebar (`dbtech_shop_trade_sidebar`) | Position in the sidebar while viewing the Trading page. |

Widget Definitions
------------------

| Definition | Description |
|---|---|
| DragonByte Shop: Latest reviews (`dbt_shop_latest_reviews`) | Displays the latest item reviews. |
| DragonByte Shop: New Items (`dbt_shop_new_items`) | Displays the most recently updated items. |
| DragonByte Shop: Top Rated Items (`dbt_shop_top_items`) | Displays the top rated items. |
| DragonByte Shop: Cart (`dbtech_shop_cart`) | Displays a block with the contents of the user's shopping cart. |
| DragonByte Shop: Profile Music (`dbtech_shop_profilemusic`) | Displays a block containing the currently chosen profile music. |
| DragonByte Shop: Richest Users (`dbtech_shop_richest`) | Displays a block containing the top X richest users. |
| DragonByte Shop: Wallet (`dbtech_shop_wallet`) | Displays a block containing the available currencies for the current user. |

Cron Entries
------------

| Name | Run on... | Run at hours | Run at minutes |
|---|---|---|---|
| DragonByte Shop: Draw lotteries | Any day of the month | Any | 1 |
| DragonByte Shop: Process automatic thread bump | Any day of the month | Any | 0, 10, 20, 30, 40, 50 |
| DragonByte Shop: Expire items | Any day of the month | Any | 0, 10, 20, 30, 40, 50 |
| DragonByte Shop: Process interest | Any day of the month | 11PM | 59 |
| DragonByte Shop: Refill stock | Any day of the month | 12AM | 0 |

REST API Scopes
---------------

| Scope | Description |
|---|---|
| `dbtech_shop_trade_post:delete_hard` | Covers hard-deleting trade posts and trade post comments. |
| `dbtech_shop_trade_post:read` | Covers viewing trade posts and trade post comments. |
| `dbtech_shop_trade_post:write` | Covers creating, updating and soft-deleting trade posts and trade post comments. |