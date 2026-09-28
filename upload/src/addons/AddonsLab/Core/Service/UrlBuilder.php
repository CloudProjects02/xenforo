<?php

namespace AddonsLab\Core\Service;
class UrlBuilder
{
    public function addArgument($url, $key, $value)
    {
        if (strpos($url, '?') === false)
        {
            return $url . '?' . $key . '=' . urlencode($value);
        }

        return $url . '&' . $key . '=' . urlencode($value);
    }

    public function addArguments($url, array $arguments)
    {
        $query = $this->_http_build_query($arguments);

        if (strpos($url, '?') === false)
        {
            return $url . '?' . $query;
        }

        return $url . '&' . $query;
    }

    public function removeArgument($url, $argumentName)
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (!$query)
        {
            $queryArray = array();
        }
        else
        {
            parse_str($query, $queryArray);
        }

        unset($queryArray[$argumentName]);

        return str_replace('?' . $query, '?' . $this->_http_build_query($queryArray), $url);
    }

    protected function _http_build_query($args)
    {
        $query = array();
        foreach ($args as $argName => $argValue)
        {
            if (
                (is_array($argValue) && empty($argValue))
                || (is_scalar($argValue) && strlen($argValue) === 0)
            )
            {
                unset($args[$argName]);
            }
        }

        return http_build_query($args);
    }
}