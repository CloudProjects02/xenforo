<?php
/** 
* @package [AL] Filter Framework
* @author AddonsLab
* @license https://addonslab.com/
* @link https://addonslab.com/
* @version 1.4.3
This software is furnished under a license and may be used and copied
only  in  accordance  with  the  terms  of such  license and with the
inclusion of the above copyright notice.  This software  or any other
copies thereof may not be provided or otherwise made available to any
other person.  No title to and  ownership of the  software is  hereby
transferred.                                                         
                                                                     
You may not reverse  engineer, decompile, defeat  license  encryption
mechanisms, or  disassemble this software product or software product
license.  AddonsLab may terminate this license if you don't comply with
any of these terms and conditions.  In such event,  licensee  agrees 
to return licensor  or destroy  all copies of software  upon termination 
of the license.
*/


namespace AL\FilterFramework\Service;

use XF\Service\AbstractService;

/**
 * Class ColorConverter
 * Color functions adopted from https://github.com/hasbridge/php-color.
 */
class ColorConverter extends AbstractService
{
    public function getContrastColor($rgbColor)
    {
        [$r, $g, $b] = array_values($this->rgbToInt($rgbColor));
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

        return ($yiq >= 128) ? 'black' : 'white';
    }

    /*public function getColorDistance(array $labColor1, array $labColor2)
    {
        $lDiff = pow($labColor2['l'] - $labColor1['l'], 2);
        $aDiff = pow($labColor2['a'] - $labColor1['a'], 2);
        $bDiff = pow($labColor2['b'] - $labColor1['b'], 2);

        return sqrt($lDiff + $aDiff + $bDiff);
    }*/

    public function rgbToLabCie($rgbColor)
    {
        $xyz = $this->rgbToXyz($rgbColor);

        if (!$xyz)
        {
            return false;
        }

        // Ovserver = 2*, Iluminant=D65
        $xyz['x'] /= 95.047;
        $xyz['y'] /= 100;
        $xyz['z'] /= 108.883;

        $xyz = array_map(function ($item) {
            if ($item > 0.008856)
            {
                // return $item ^ (1/3);
                return pow($item, 1 / 3);
            }

            return (7.787 * $item) + (16 / 116);
        }, $xyz);

        return [
            'l' => (116 * $xyz['y']) - 16,
            'a' => 500 * ($xyz['x'] - $xyz['y']),
            'b' => 200 * ($xyz['y'] - $xyz['z']),
        ];
    }

    public function rgbToXyz($rgbColor)
    {
        $rgb = $this->rgbToInt($rgbColor);

        if ($rgb === false)
        {
            return false;
        }

        // Normalize RGB values to 1
        $rgb = array_map(function ($item) {
            return $item / 255;
        }, $rgb);

        $rgb = array_map(function ($item) {
            if ($item > 0.04045)
            {
                $item = pow(($item + 0.055) / 1.055, 2.4);
            }
            else
            {
                $item = $item / 12.92;
            }

            return $item * 100;
        }, $rgb);

        // Observer. = 2°, Illuminant = D65
        return [
            'x' => ($rgb['red'] * 0.4124) + ($rgb['green'] * 0.3576) + ($rgb['blue'] * 0.1805),
            'y' => ($rgb['red'] * 0.2126) + ($rgb['green'] * 0.7152) + ($rgb['blue'] * 0.0722),
            'z' => ($rgb['red'] * 0.0193) + ($rgb['green'] * 0.1192) + ($rgb['blue'] * 0.9505),
        ];
    }

    public function rgbToInt($rgbColor)
    {
        if (preg_match('#rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)#', $rgbColor, $match))
        {
            return [
                'red' => $match[1],
                'green' => $match[2],
                'blue' => $match[3],
            ];
        }

        if (empty($rgbColor))
        {
            return false;
        }

        $color = @hexdec($rgbColor);

        return [
            'red' => (int) (255 & ($color >> 16)),
            'green' => (int) (255 & ($color >> 8)),
            'blue' => (int) (255 & $color),
        ];
    }
}
