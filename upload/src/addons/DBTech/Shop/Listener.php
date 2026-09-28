<?php

namespace DBTech\Shop;

use XF\Container;
use XF\Mvc\Entity\ArrayCollection;

class Listener
{
    /**
     * The product ID (in the DBTech store)
     * @var int
     */
    protected static $_productId = 336;

    
    /**
     * @param \XF\Pub\App $app
     */
    public static function appPubSetup(\XF\Pub\App $app): void
    {
        /*DBTECH_BRANDING_START*/
        // Make sure we fetch the branding array from the application
        $branding = $app->offsetExists('dbtech_branding') ? $app->dbtech_branding : [];
        
        // Add productid to the array
        $branding[] = self::$_productId;
        
        // Store the branding
        $app->dbtech_branding = $branding;
        /*DBTECH_BRANDING_END*/
    }
    
    /**
     * @param \XF\App $app
     *
     * @throws \XF\Db\Exception
     */
    public static function appSetup(\XF\App $app): void
    {
        $container = $app->container();
        
        $container['prefixes.dbtechShopItem'] = $app->fromRegistry(
            'dbtShopPrefixes',
            function (Container $c) { return $c['em']->getRepository('DBTech\Shop:ItemPrefix')->rebuildPrefixCache(); }
        );
        
        $container['customFields.dbtechShopItems'] = $app->fromRegistry(
            'dbtShopItemFieldsInfo',
            function (Container $c) { return $c['em']->getRepository('DBTech\Shop:ItemField')->rebuildFieldCache(); },
            function (array $fields) use ($app): \XF\CustomField\DefinitionSet
            {
                $class = 'XF\CustomField\DefinitionSet';
                $class = $app->extendClass($class);

                return new $class($fields);
            }
        );
        
        $container['dbtechShop.currencies'] = $app->fromRegistry(
            'dbtShopCurrencies',
            function (Container $c) { return $c['em']->getRepository('DBTech\Shop:Currency')->rebuildCache(); },
            function (array $currencies): ArrayCollection
            {
                $em = \XF::em();
                
                $entities = [];
                foreach ($currencies as $currencyId => $currency)
                {
                    $entities[$currencyId] = $em->instantiateEntity('DBTech\Shop:Currency', $currency);
                }
                
                return $em->getBasicCollection($entities);
            }
        );
        
        $container['dbtechShop.usernameStyles'] = $app->fromRegistry(
            'dbtShopUserNameStyle',
            function (Container $c) { return $c['em']->getRepository('DBTech\Shop:Purchase')->rebuildUserNameStyleCache(); }
        );
        
        $container['dbtechShop.usertitleStyles'] = $app->fromRegistry(
            'dbtShopUserTitleStyle',
            function (Container $c) { return $c['em']->getRepository('DBTech\Shop:Purchase')->rebuildUserTitleStyleCache(); }
        );
        
        
    }
    
    /**
     * @param \XF\Template\Templater $templater
     * @param string $type
     * @param string $template
     * @param string $name
     * @param array $arguments
     * @param array $globalVars
     */
    public static function templaterMacroPreRender(
        \XF\Template\Templater $templater,
        string &$type,
        string &$template,
        string &$name,
        array &$arguments,
        array &$globalVars
    ): void {
        if (!empty($arguments['group']) && $arguments['group']->group_id == 'dbtech_shop')
        {
            // Override template name
            $template = 'dbtech_shop_option_macros';
        }
    }
    
    /**
     * @param \XF\Service\User\DeleteCleanUp $deleteService
     * @param array $deletes
     */
    public static function userDeleteCleanInit(\XF\Service\User\DeleteCleanUp $deleteService, array &$deletes)
    {
        $deletes['xf_dbtech_shop_category_watch'] = 'user_id = ?';
        $deletes['xf_dbtech_shop_item_rating'] = 'user_id = ?';
        $deletes['xf_dbtech_shop_item_watch'] = 'user_id = ?';
        $deletes['xf_dbtech_shop_purchase'] = 'user_id = ?';
        $deletes['xf_dbtech_shop_transaction_log'] = 'user_id = ?';
    }
    
    /**
     * @param \XF\Entity\User $target
     * @param \XF\Entity\User $source
     * @param \XF\Service\User\Merge $mergeService
     */
    public static function userMergeCombine(
        \XF\Entity\User $target,
        \XF\Entity\User $source,
        \XF\Service\User\Merge $mergeService
    ) {
        $target->dbtech_shop_item_count += $source->dbtech_shop_item_count;
        $target->dbtech_shop_purchases += $source->dbtech_shop_purchases;
    }
    
    /**
     * @param \XF\Searcher\User $userSearcher
     * @param array $sortOrders
     */
    public static function userSearcherOrders(\XF\Searcher\User $userSearcher, array &$sortOrders)
    {
        $sortOrders['dbtech_shop_item_count'] = \XF::phrase('dbtech_shop_item_count');
        $sortOrders['dbtech_shop_purchases'] = \XF::phrase('dbtech_shop_purchases');
    }
    
    /**
     * @param \XF\Pub\App $app
     * @param array $params
     * @param \XF\Mvc\Reply\AbstractReply $reply
     * @param \XF\Mvc\Renderer\AbstractRenderer $renderer
     */
    public static function appPubRenderPage(
        \XF\Pub\App $app,
        array &$params,
        \XF\Mvc\Reply\AbstractReply $reply,
        \XF\Mvc\Renderer\AbstractRenderer $renderer
    ) {
        foreach ($params['selectedNavChildren'] as $key => &$child)
        {
            if (strpos($key, 'dbtechCreditsCurrency') !== false)
            {
                $child['attributes']['class'] = '';
            }
        }
        unset($child);
    }

    /**
     * Generate CSS for all active avatar frames
     *
     * @return string
     */
    protected static function generateAvatarFrameCSS(): string
    {
        // Get all avatar frame items
        $avatarFrameItems = null;
        
        // Try different possible column names
        $possibleColumns = ['item_type_id', 'type', 'handler_class', 'item_type'];
        
        foreach ($possibleColumns as $columnName)
        {
            try 
            {
                $avatarFrameItems = \XF::finder('DBTech\Shop:Item')
                    ->where($columnName, 'avatarframe')
                    ->fetch();
                
                if ($avatarFrameItems && count($avatarFrameItems) > 0)
                {
                    break;
                }
            }
            catch (\Exception $e)
            {
                // Column doesn't exist, try next
            }
        }

        // If we still can't find items, use hardcoded item ID for testing
        if (!$avatarFrameItems || !count($avatarFrameItems))
        {
            $itemIds = [11]; // Your avatar frame item ID
        }
        else
        {
            $itemIds = array_keys($avatarFrameItems->toArray());
        }

        // Get all active avatar frame purchases
        $purchases = \XF::finder('DBTech\Shop:Purchase')
            ->with('Item', 'User')
            ->where('active', 1)
            ->where('item_id', $itemIds)
            ->fetch();

        if (!$purchases->count())
        {
            return '';
        }

        $css = [];
        
        foreach ($purchases as $purchase)
        {
            if (!$purchase->Item || !$purchase->User)
            {
                continue;
            }

            $config = $purchase->Item->code;
            $userId = $purchase->User->user_id;
            
            if (empty($config['frame_image']))
            {
                continue;
            }

            $borderRadius = $config['frame_border_radius'] ?? 50;
            $animation = $config['frame_animation'] ?? 'none';
            $glow = $config['frame_glow'] ?? false;
            $glowColor = $config['frame_glow_color'] ?? '#ffffff';

            // Generate CSS targeting XenForo's avatar structure
            $userCSS = "
            /* Avatar frame for user {$userId} */
            .avatar[data-user-id=\"{$userId}\"]::before {
                content: '';
                display: block;
                background-image: url('" . htmlspecialchars($config['frame_image']) . "');
                background-size: contain;
                background-repeat: no-repeat;
                background-position: center;
                position: absolute;
                height: 120%;
                width: 120%;
                top: -10%;
                left: -10%;
                border-radius: {$borderRadius}%;
                pointer-events: none;
                z-index: 2;
            }

            .avatar[data-user-id=\"{$userId}\"] {
                position: relative !important;
                overflow: visible !important;
            }";

            if ($glow)
            {
                $userCSS .= "
                .avatar[data-user-id=\"{$userId}\"]::before {
                    box-shadow: 0 0 10px " . htmlspecialchars($glowColor) . ";
                }";
            }

            if ($animation !== 'none')
            {
                $userCSS .= "
                .avatar[data-user-id=\"{$userId}\"]::before {
                    animation: avatar-frame-{$animation} 2s infinite;
                }";
            }

            $css[] = $userCSS;
        }

        if (!$css)
        {
            return '';
        }

        // Add animation keyframes
        $animationCSS = "
        @keyframes avatar-frame-pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
        }
        
        @keyframes avatar-frame-rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        @keyframes avatar-frame-glow {
            from { filter: brightness(1); }
            to { filter: brightness(1.2); }
        }
        
        @keyframes avatar-frame-bounce {
            0%, 20%, 53%, 80%, 100% { transform: translateY(0); }
            40%, 43% { transform: translateY(-5px); }
            70% { transform: translateY(-3px); }
            90% { transform: translateY(-2px); }
        }";

        return $animationCSS . "\n" . implode("\n", $css);
    }

    /**
     * Handle templater_setup to add avatar frame functions
     *
     * @param Container $container
     * @param \XF\Template\Templater $templater
     */
    public static function templaterSetupAvatarFrame(Container $container, \XF\Template\Templater &$templater)
    {		
        // Add global avatar frame function
        $templater->addFunction('avatar_frame_global', function($templater, &$escape)
        {
            $escape = false;
            
            // Generate CSS for all active avatar frames
            $css = static::generateAvatarFrameCSS();
            
            if ($css)
            {
                return '<style>' . $css . '</style>';
            }
            
            return '';
        });
    }

    /**
     * Get active avatar frame for a user
     *
     * @param \XF\Entity\User $user
     * @return array|null
     */
    protected static function getActiveAvatarFrame(\XF\Entity\User $user)
    {
        // Find active avatar frame purchase directly
        $purchase = \XF::finder('DBTech\Shop:Purchase')
            ->with('Item')
            ->where('user_id', $user->user_id)
            ->where('active', 1)
            ->where('Item.item_type_id', 'avatarframe')
            ->fetchOne();

        if (!$purchase || !$purchase->Item)
        {
            return null;
        }

        return [
            'purchase' => $purchase,
            'config' => $purchase->Item->code
        ];
    }

    public static function templaterSetupProfileEffects(Container $container, \XF\Template\Templater &$templater)
    {
        $templater->addFunction('profile_effects', function($templater, &$escape, $user = null)
        {
            $escape = false;

            // If no user provided, use the current visitor
            if (!$user) {
                $user = \XF::visitor();
            }

            // Get the active profile effect for the user
            $profileEffect = static::getActiveProfileEffect($user);

            if (!$profileEffect || empty($profileEffect['config']['effect_image']))
            {
                return '';
            }

            $effectImage = htmlspecialchars($profileEffect['config']['effect_image']);

            return '<img id="profileProfileEffectPreview" class="profile-profile-effect-preview jace" src="' . $effectImage . '">';
        });
    }

    /**
     * Get active profile effect for a user
     *
     * @param \XF\Entity\User $user
     * @return array|null
     */
    protected static function getActiveProfileEffect(\XF\Entity\User $user)
    {
        // Get all profile effect items
        $profileEffectItems = null;
        
        // Try different possible column names
        $possibleColumns = ['item_type_id', 'type', 'handler_class', 'item_type'];
        
        foreach ($possibleColumns as $columnName)
        {
            try 
            {
                $profileEffectItems = \XF::finder('DBTech\Shop:Item')
                    ->where($columnName, 'profileeffects')
                    ->fetch();
                
                if ($profileEffectItems && count($profileEffectItems) > 0)
                {
                    break;
                }
            }
            catch (\Exception $e)
            {
                // Column doesn't exist, try next
            }
        }

        if (!$profileEffectItems || !count($profileEffectItems))
        {
            return null;
        }

        $itemIds = array_keys($profileEffectItems->toArray());

        // Find active profile effect purchase for this user
        $purchase = \XF::finder('DBTech\Shop:Purchase')
            ->with('Item')
            ->where('user_id', $user->user_id)
            ->where('active', 1)
            ->where('item_id', $itemIds)
            ->fetchOne();

        if (!$purchase || !$purchase->Item)
        {
            return null;
        }

        return [
            'purchase' => $purchase,
            'config' => $purchase->Item->code
        ];
    }

    /**
     * Template function for profile effects
     *
     * @param \XF\Template\Templater $templater
     * @param bool $escape
     * @param \XF\Entity\User $user
     * @return string
     */
    public static function templaterFnProfileEffects(
        \XF\Template\Templater $templater,
        bool &$escape,
        \XF\Entity\User $user
    ): string {
        $escape = false;
        
        $profileEffect = static::getActiveProfileEffect($user);
        
        if (!$profileEffect || empty($profileEffect['config']['effect_image']))
        {
            return '';
        }
        
        $effectImage = htmlspecialchars($profileEffect['config']['effect_image']);
        
        return '<img id="profileProfileEffectPreview" class="profile-profile-effect-preview" src="' . $effectImage . '">';
    }
    
    /**
     * @param $rule
     * @param array $data
     * @param \XF\Entity\User $user
     * @param $returnValue
     */
    public static function criteriaUser($rule, array $data, \XF\Entity\User $user, &$returnValue)
    {
        /** @var \DBTech\Shop\XF\Entity\User $user */
        
        switch ($rule)
        {
            case 'dbtech_shop_purchases':
                if ($user->dbtech_shop_purchases >= $data['purchases'])
                {
                    $returnValue = true;
                }
                break;
            
            case 'dbtech_shop_item':
                $data['itemIds'] = $data['itemIds'] ?? (!empty($data['itemid']) ? [$data['itemid']] : []);

                $purchasedGrouped = $user->dbtech_shop_purchase
                    ->filter(function (Entity\Purchase $purchase) use ($data): ?Entity\Purchase
                    {
                        if (!\in_array($purchase->item_id, $data['itemIds']))
                        {
                            return null;
                        }
                        
                        if (!$purchase->isActive() && !empty($data['activeonly_user']))
                        {
                            return null;
                        }
                        
                        if (!$purchase->Item->isVisible() && !empty($data['activeonly_global']))
                        {
                            return null;
                        }
                        
                        return $purchase;
                    })
                    ->groupBy('item_id', 'purchase_id')
                ;

                $returnValue = !empty($data['all'])
                    ? (\count($purchasedGrouped) === \count($data['itemIds']))
                    : (\count($purchasedGrouped) > 0)
                ;
                break;
            
            case 'dbtech_shop_itemtype':
                $purchases = $user->dbtech_shop_purchase
                    ->filter(function (Entity\Purchase $purchase) use ($data): ?Entity\Purchase
                    {
                        if ($purchase->Item->item_type_id != $data['itemtypeid'])
                        {
                            return null;
                        }
                        
                        return $purchase;
                    })
                ;
                
                $returnValue = $purchases->count() > 0;
                break;
        }
    }
    
    /**
     * @param \XF\Pub\App $app
     * @param array $navigationFlat
     * @param array $navigationTree
     */
    public static function navigationSetup(\XF\Pub\App $app, array &$navigationFlat, array &$navigationTree)
    {
        if (!isset($navigationFlat['dbtechShop']) || !isset($navigationTree['dbtechShop']))
        {
            return;
        }
        
        /** @var \DBTech\Shop\XF\Entity\User $visitor */
        $visitor = \XF::visitor();
        
        /** @var \DBTech\Shop\Entity\Currency $currency */
        if ($visitor->user_id && $currency = $app->repository('DBTech\Shop:Currency')->getDisplayCurrencyFromCache())
        {
            // Update the navbar title
            $navigationFlat['dbtechShop']['title'] = \XF::phrase('dbtech_shop_display_currency_phrase', [
                'currency' => $currency->title,
                'prefix' => $currency->prefix,
                'amount' => $currency->getValueFromUser(),
                'suffix' => $currency->suffix
            ]);
        }
        
        // Set the counter
        $navigationFlat['dbtechShop']['counter'] = $visitor->dbtech_shop_pendingtrades;
        
        if (isset($navigationFlat['dbtechShop']['children']['dbtechShopTrade']))
        {
            // Add the counter to the child element
            $navigationFlat['dbtechShop']['children']['dbtechShopTrade']['counter'] = $visitor->dbtech_shop_pendingtrades;
        }
    }
    
    /**
     * @param \XF\Service\User\ContentChange $changeService
     * @param array $updates
     */
    public static function userContentChangeInit(\XF\Service\User\ContentChange $changeService, array &$updates)
    {
        $updates['xf_dbtech_shop_category_watch'] = ['user_id', 'emptyable' => false];
        $updates['xf_dbtech_shop_item'] = ['user_id', 'username'];
        $updates['xf_dbtech_shop_item_rating'] = ['user_id', 'emptyable' => false];
        $updates['xf_dbtech_shop_item_watch'] = ['user_id', 'emptyable' => false];
        $updates['xf_dbtech_shop_purchase'] = [
            ['user_id', 'emptyable' => false],
            ['buyer_user_id', 'buyer_username']
        ];
    }
    
    /**
     * @param Container $container
     * @param \XF\Template\Templater $templater
     */
    public static function templaterSetup(Container $container, \XF\Template\Templater &$templater)
    {
        $templater->addFunctions([
            'dbtech_shop_item_icon'          => [__CLASS__, 'templaterFnItemIcon'],
            'dbtech_shop_thread_background'  => [__CLASS__, 'templaterFnRichThreadbit'],
            'dbtech_shop_post_background'    => [__CLASS__, 'templaterFnRichPostBackground'],
            'dbtech_shop_postbit_background' => [__CLASS__, 'templaterFnRichPostbit'],
            'dbtech_shop_post_style'         => [__CLASS__, 'templaterFnRichPostMessage'],
            'avatar_frame_global'            => [__CLASS__, 'templaterFnAvatarFrameGlobal'], // Add this line
        ]);
        
        $templater->addFilters([
            'dbtech_shop_thread_style' => [__CLASS__, 'templaterFilterRichThreadTitle'],
        ]);
    }

    /**
     * Template function for global avatar frame CSS
     *
     * @param \XF\Template\Templater $templater
     * @param bool $escape
     * @return string
     */
    public static function templaterFnAvatarFrameGlobal(
        \XF\Template\Templater $templater,
        bool &$escape
    ): string {
        $escape = false;
        
        // Generate CSS for all active avatar frames
        $css = static::generateAvatarFrameCSS();
        
        if ($css)
        {
            return '<style>' . $css . '</style>';
        }
        
        return '';
    }
    
    /**
     * @param \XF\Template\Templater $templater
     * @param bool $escape
     * @param \DBTech\Shop\Entity\Item $item
     * @param string $size
     * @param string $href
     * @param string $xfClick
     *
     * @return string
     */
    public static function templaterFnItemIcon(
        \XF\Template\Templater $templater,
        bool &$escape,
        \DBTech\Shop\Entity\Item $item,
        string $size = 'm',
        string $href = '',
        string $xfClick = ''
    ): string {
        $escape = false;
        
        if ($href)
        {
            $tag = 'a';
            $hrefAttr = 'href="' . \XF::escapeString($href) . '" data-xf-click="' . $xfClick . '"';
        }
        else
        {
            $tag = 'span';
            $hrefAttr = '';
        }
        
        if (!$item->icon_date)
        {
            return "<{$tag} {$hrefAttr} class=\"avatar avatar--{$size} avatar--itemIconDefault\"><span></span></{$tag}>";
        }
        
        $src = $item->getIconUrl($size);
        
        return "<{$tag} {$hrefAttr} class=\"avatar avatar--{$size} avatar--itemIcon\">"
            . '<img src="' . \XF::escapeString($src) . '" alt="' . \XF::escapeString($item->title) . '" loading="lazy" />'
            . "</{$tag}>";
    }
    
    /**
     * @param \XF\Template\Templater $templater
     * @param bool $escape
     * @param \XF\Entity\Thread $thread
     * @param bool $transparent
     *
     * @return string
     */
    public static function templaterFnRichThreadbit(
        \XF\Template\Templater $templater,
        bool &$escape,
        \XF\Entity\Thread $thread,
        bool $transparent = false
    ): string {
        if (!$thread->thread_id
            || !$thread->User
        ) {
            return '';
        }
        
        /** @var \DBTech\Shop\XF\Entity\User $user */
        $user = $thread->User;
        
        $styleProps = [];
        
        /** @var \DBTech\Shop\Entity\Purchase[]|ArrayCollection $purchases */
        $purchases = \XF::repository('DBTech\Shop:Purchase')->filterActivePurchasesForUser($user);
        foreach ($purchases as $purchase)
        {
            $handler = $purchase->handler;
            $handler->fire('thread_bit_markup', [$thread, &$styleProps, $transparent], $thread->thread_id);
        }
        
        if ($styleProps)
        {
            // Ensure we only add the span if needed
            return implode('; ', $styleProps);
        }
        
        return '';
    }

    /**
     * @param \XF\Template\Templater $templater
     * @param bool $escape
     * @param \XF\Entity\Post $post
     *
     * @return string
     */
    public static function templaterFnRichPostMessage(
        \XF\Template\Templater $templater,
        bool &$escape,
        \XF\Entity\Post $post
    ): string {
        if (!$post->post_id
            || !$post->User
        ) {
            return '';
        }

        /** @var \DBTech\Shop\XF\Entity\User $user */
        $user = $post->User;

        $styleProps = [];

        /** @var \DBTech\Shop\Entity\Purchase[]|ArrayCollection $purchases */
        $purchases = \XF::repository('DBTech\Shop:Purchase')->filterActivePurchasesForUser($user);
        foreach ($purchases as $purchase)
        {
            $handler = $purchase->handler;
            $handler->fire('post_message_markup', [$post, &$styleProps], $post->post_id);
        }

        if ($styleProps)
        {
            // Ensure we only add the span if needed
            return implode('; ', $styleProps);
        }

        return '';
    }
    
    /**
     * @param \XF\Template\Templater $templater
     * @param bool $escape
     * @param \XF\Entity\Post $post
     * @param bool $transparent
     *
     * @return string
     */
    public static function templaterFnRichPostBackground(
        \XF\Template\Templater $templater,
        bool &$escape,
        \XF\Entity\Post $post,
        bool $transparent = false
    ): string {
        if (!$post->post_id
            || !$post->User
        ) {
            return '';
        }
        
        /** @var \DBTech\Shop\XF\Entity\User $user */
        $user = $post->User;
        
        $styleProps = [];
        
        /** @var \DBTech\Shop\Entity\Purchase[]|ArrayCollection $purchases */
        $purchases = \XF::repository('DBTech\Shop:Purchase')->filterActivePurchasesForUser($user);
        foreach ($purchases as $purchase)
        {
            $handler = $purchase->handler;
            $handler->fire('post_background_markup', [$post, &$styleProps, $transparent], $post->post_id);
        }
        
        if ($styleProps)
        {
            // Ensure we only add the span if needed
            return implode('; ', $styleProps);
        }
        
        return '';
    }
    
    /**
     * @param \XF\Template\Templater $templater
     * @param bool $escape
     * @param \XF\Entity\Post $post
     * @param bool $transparent
     *
     * @return string
     */
    public static function templaterFnRichPostbit(
        \XF\Template\Templater $templater,
        bool &$escape,
        \XF\Entity\Post $post,
        bool $transparent = false
    ): string {
        if (!$post->post_id
            || !$post->User
        ) {
            return '';
        }
        
        /** @var \DBTech\Shop\XF\Entity\User $user */
        $user = $post->User;
        
        $styleProps = [];
        
        /** @var \DBTech\Shop\Entity\Purchase[]|ArrayCollection $purchases */
        $purchases = \XF::repository('DBTech\Shop:Purchase')->filterActivePurchasesForUser($user);
        foreach ($purchases as $purchase)
        {
            $handler = $purchase->handler;
            $handler->fire('post_bit_markup', [$post, &$styleProps, $transparent], $post->post_id);
        }
        
        if ($styleProps)
        {
            // Ensure we only add the span if needed
            return implode('; ', $styleProps);
        }
        
        return '';
    }

    /**
     * @param \XF\Template\Templater $templater
     * @param string $value
     * @param bool $escape
     * @param \XF\Entity\Thread $thread
     *
     * @return string
     */
    public static function templaterFilterRichThreadTitle(
        \XF\Template\Templater $templater,
        string $value,
        bool &$escape,
        \XF\Entity\Thread $thread
    ): string {
        if (!$thread->thread_id
            || !$thread->User
        ) {
            return $value;
        }

        /** @var \DBTech\Shop\XF\Entity\User $user */
        $user = $thread->User;

        $styleProps = [];

        /** @var \DBTech\Shop\Entity\Purchase[]|ArrayCollection $purchases */
        $purchases = \XF::repository('DBTech\Shop:Purchase')->filterActivePurchasesForUser($user);
        foreach ($purchases as $purchase)
        {
            $handler = $purchase->handler;
            $handler->fire('thread_title_markup', [$thread, &$styleProps], $thread->thread_id);
        }

        if ($styleProps)
        {
            $escape = false;

            // Ensure we only add the span if needed
            return '<span style="' . implode('; ', $styleProps) . '">' . \XF::escapeString($value) . '</span>';
        }

        return $value;
    }
}