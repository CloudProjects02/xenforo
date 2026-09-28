<?php

namespace Jace\TokenDownloads;

class Listener
{
    public static function templaterSetup(\XF\Container $container, \XF\Template\Templater &$templater)
    {
        $templater->addFunction('package_image', function(\XF\Template\Templater $templater, &$escape, $package, $link = '', $attributes = [])
        {
            $escape = false;

            if (is_array($package) && isset($package['icon_url']))
            {
                $iconUrl = $package['icon_url'];
                $title = $package['title'] ?? '';
            }
            else if (is_object($package))
            {
                $iconUrl = $package->getIconUrl();
                $title = $package->title ?? '';
            }
            else
            {
                $iconUrl = null;
                $title = '';
            }

            if ($iconUrl)
            {
                $img = '<img src="' . htmlspecialchars($iconUrl) . '" alt="' . htmlspecialchars($title) . '"';
                
                if ($attributes)
                {
                    foreach ($attributes as $attr => $value)
                    {
                        $img .= ' ' . htmlspecialchars($attr) . '="' . htmlspecialchars($value) . '"';
                    }
                }
                
                $img .= ' />';
            }
            else
            {
                $img = '<span class="avatar avatar--default avatar--default--package"><span></span></span>';
            }

            if ($link)
            {
                return '<a href="' . htmlspecialchars($link) . '">' . $img . '</a>';
            }
            else
            {
                return $img;
            }
        });
    }
}