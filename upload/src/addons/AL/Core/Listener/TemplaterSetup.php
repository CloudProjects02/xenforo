<?php

namespace AL\Core\Listener;

use XF\Container;
use XF\Template\Templater;

class TemplaterSetup
{

    public static function listen(Container $container, Templater $templater)
    {
        $templater->addFunction('apply_sort_params', function (Templater $templater, &$escape, array $params, $name, $defaultDirection, $order, $direction) {
            if ($order === $name) {
                if (!$direction) {
                    $direction = $defaultDirection;
                } else {
                    // Reverse the order
                    $direction = $direction === 'desc' ? 'asc' : 'desc';
                }
            } else {
                $direction = $defaultDirection;
            }
            $params['order'] = $name;
            $params['direction'] = $direction;
            return $params;
        });

        $templater->addFunction('date_time_for_user', function (Templater $templater, &$escape, $date, \XF\Entity\User $user, $format = null) {
            return \XF::asVisitor($user, function () use ($templater, &$escape, $date) {
                return $templater->fnDateTime($templater, $escape, $date);
            }, true);
        });


        $templater->addFilter('array_sub_replace', function ($templater, $value, &$escape, $key, $from, $to = null, $subAdId = null) {
            return self::replaceSubFilter($templater, $value, $escape, $key, $from, $to, $subAdId);
        });
    }

    public static function replaceSubFilter(Templater $templater, $value, &$escape, $rootKey, $subKey, $replaceWith = null, $subItemId = null)
    {
        if (!isset($value[$rootKey])) {
            return $value;
        }

        if (empty($value[$rootKey])) {
            unset($value[$rootKey]);
            return $value;
        }

        if ($subItemId && !empty($value[$rootKey][$subKey]) && is_array($value[$rootKey][$subKey])) {
            $subItemIndex = array_search($subItemId, $value[$rootKey][$subKey], false);
            if ($subItemIndex !== false) {
                $value[$rootKey][$subKey] = $templater->filterReplace($templater, $value[$rootKey][$subKey], $escape, $subItemIndex, $replaceWith);
                $value[$rootKey][$subKey] = array_filter($value[$rootKey][$subKey], function ($item) {
                    return $item !== null;
                });
            }

            if (empty($value[$rootKey][$subKey])) {
                unset($value[$rootKey][$subKey]);
            }
        } else {
            $value[$rootKey] = $templater->filterReplace($templater, $value[$rootKey], $escape, $subKey, $replaceWith);
        }


        $value[$rootKey] = array_filter($value[$rootKey], function ($item) {
            return $item !== null;
        });

        if (empty($value[$rootKey])) {
            unset($value[$rootKey]);
        }

        return $value;
    }
}

