<?php

namespace AddonsLab\Core\Service;

class Utf8Converter
{
    protected $fullUnicode = false;

    protected $sourceCharset = null;
    protected $sourceConvertHtml = false;

    /**
     * @param bool $fullUnicode
     */
    public function setFullUnicode($fullUnicode)
    {
        $this->fullUnicode = $fullUnicode;
    }

    public function setSourceCharset($charset, $convertHtml = false)
    {
        $this->sourceCharset = $charset;
        $this->sourceConvertHtml = $convertHtml;
    }

    /**
     * @param $string
     * @param null $fromCharset
     * @param null $convertHtml
     * @return string|null
     */
    public function convertToUtf8($string, $fromCharset = null, $convertHtml = null)
    {
        if ($fromCharset === null)
        {
            $fromCharset = $this->sourceCharset;
        }
        if ($convertHtml === null)
        {
            $convertHtml = $this->sourceConvertHtml;
        }

        $string = strval($string);

        if (preg_match('/[\x80-\xff]/', $string))
        {
            if ($fromCharset)
            {
                $newString = false;
                if (function_exists('mb_convert_encoding'))
                {
                    $newString = @mb_convert_encoding($string, 'utf-8', $fromCharset);

                    if (PHP_VERSION_ID < 70204 && $this->isInvalidUtf8($newString))
                    {
                        // 7.2.4 has a fix for so bad replacements in the mbstring tables. Some edge cases
                        // can cause failures, so we need to check this and fallback to iconv if needed.
                        $newString = false;
                    }
                }
                if (!$newString && function_exists('iconv'))
                {
                    $newString = @iconv($fromCharset, 'UTF-8//IGNORE', $string);
                    if ($newString && strtolower($fromCharset) == 'utf-8')
                    {
                        $newString = utf8_bad_replace($newString);
                    }
                }
                $string = ($newString ? $newString : preg_replace('/[\x80-\xff]/', '', $string));
            }
            else
            {
                $string = utf8_bad_replace($string);
            }
        }

        $string = utf8_unhtml($string, $convertHtml);

        return $this->stripExtendedUtf8IfNeeded($string);
    }

    public function stripExtendedUtf8IfNeeded($string)
    {
        if (!$this->fullUnicode)
        {
            $string = preg_replace('/[\xF0-\xF7].../', '', $string);
            $string = preg_replace('/[\xF8-\xFB]..../', '', $string);
        }

        return $string;
    }
}