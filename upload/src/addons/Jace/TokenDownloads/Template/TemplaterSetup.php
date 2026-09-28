<?php

namespace Jace\TokenDownloads\Template;

class TemplaterSetup
{
    public static function fnPackageImage(\XF\Template\Templater $templater, &$escape, $package, $link = '', $attributes = [])
    {
        $escape = false;

        if ($imageUrl = $package['icon_url'])
        {
            $img = $templater->func('mustache', [
                'template' => '<img src="{{ $src }}" alt="{{ $alt }}" {{ attributes($attributes) }} />',
                'data' => [
                    'src' => $imageUrl,
                    'alt' => $package['title'],
                    'attributes' => $attributes
                ]
            ]);
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
    }
}