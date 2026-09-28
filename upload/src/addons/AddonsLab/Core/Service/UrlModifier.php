<?php

namespace AddonsLab\Tools;

class UrlModifier
{
    protected $url;

    public function __construct($url)
    {
        $this->url = $url;
    }

    public function addArgument($argumentName, $argumentValue)
    {
        $url = $this->url;

        $query = parse_url($url, PHP_URL_QUERY);

        if (!$query) {
            $queryArray = array();
        } else {
            $queryArray = $this->parse_str($query);
        }

        $queryArray[$argumentName] = $argumentValue;

        if (!$query) {
            return $url .= '?' . http_build_query($queryArray);
        }

        return str_replace('?' . $query, '?' . $this->http_build_query($queryArray), $url);
    }

    public function removeArgument($argumentName)
    {
        $url = $this->url;

        $query = parse_url($url, PHP_URL_QUERY);

        if (!$query) {
            $queryArray = array();
        } else {
            parse_str($query, $queryArray);
        }

        unset($queryArray[$argumentName]);

        return str_replace('?' . $query, '?' . $this->http_build_query($queryArray), $url);
    }

    public function parse_str($queryString)
    {
        $args = array();
        $queryString = explode('&', $queryString);

        foreach ($queryString AS $item) {
            if (!$item) {
                continue;
            }

            $item = explode('=', $item);

            if (empty($item[1])) {
                $item[1] = '';
            }

            $args[$item[0]] = urldecode($item[1]);
        }

        return $args;
    }

    public function http_build_query($args)
    {
        $query = array();
        foreach ($args AS $argName => $argValue) {
            if (
                (is_array($argValue) && empty($argValue)) 
                || (is_scalar($argValue) && strlen($argValue) === 0)
            ) {
                unset($args[$argName]);
            }
        }

        return http_build_query($args);
    }
}