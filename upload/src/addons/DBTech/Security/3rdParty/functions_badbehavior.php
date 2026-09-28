<?php
/*
Bad Behavior - detects and blocks unwanted Web accesses
Copyright (C) 2005,2006,2007,2008,2009,2010,2011,2012 Michael Hampton

Bad Behavior is free software; you can redistribute it and/or modify it under
the terms of the GNU Lesser General Public License as published by the Free
Software Foundation; either version 3 of the License, or (at your option) any
later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License along
with this program. If not, see <http://www.gnu.org/licenses/>.

Please report any problems to bad . bots AT ioerror DOT us
http://bad-behavior.ioerror.us/
*/

###############################################################################
###############################################################################

define('BB2_CWD', \XF::getAddOnDirectory() . '/DBTech/Security/3rdParty');

// Bad Behavior callback functions.

// Return current time in the format preferred by your database.
function bb2_db_date()
{
	return gmdate('Y-m-d H:i:s');	// Example is MySQL format
}

// Return the number of rows in a particular query.
function bb2_db_num_rows($result)
{
	if ($result !== false)
	{
		return count($result);
	}
	return 0;
}

// Run a query and return the results, if any.
// Should return FALSE if an error occurred.
// Bad Behavior will use the return value here in other callbacks.
function bb2_db_query($query)
{
	if (!$query)
	{
		return false;
	}

	$db = \XF::db();

	try
	{
		if (preg_match("/^\\s*(insert|delete|update|replace|alter|set) /i", $query))
		{
			$result = $db->query($query);

			return $result->rowsAffected();
		}

		$results = $db->fetchAll($query);
	}
	catch (\Exception $e)
	{
		return false;
	}

	if (empty($results) or !is_array($results))
	{
		return false;
	}

	foreach ($results AS &$row)
	{
		$row = get_object_vars($row);
	}

	return $results;
}

// Create the SQL query for inserting a record in the database.
// See example for MySQL elsewhere.
function bb2_insert($settings, $package, $key)
{
	$db = \XF::db();

	$ip = $db->quote($package['ip']);
	$request_method = $db->quote($package['request_method']);
	$request_uri = $db->quote($package['request_uri']);
	$server_protocol = $db->quote($package['server_protocol']);
	$user_agent = $db->quote($package['user_agent']);

	$headers = "$request_method $request_uri $server_protocol\n";
	foreach ($package['headers'] AS $h => $v)
	{
		$headers .= "$h: $v\n";
	}
	$headers = $db->quote($headers);

	$request_entity = '';
	if (!strcasecmp($request_method, 'POST'))
	{
		foreach ($package['request_entity'] AS $h => $v)
		{
			$request_entity .= "$h: $v\n";
		}
	}
	$request_entity = $db->quote($request_entity);

	return "INSERT INTO `" . $settings['log_table'] . "`
	(`ip`, `date`, `request_method`, `request_uri`, `server_protocol`, `http_headers`, `user_agent`, `request_entity`, `key`)
VALUES
	($ip, NOW(), $request_method, $request_uri, $server_protocol, $headers, $user_agent, $request_entity, '$key')";
}

// Return emergency contact email address.
function bb2_email()
{
	$xenOptions = \XF::options();

	if ($xenOptions->contactEmailAddress != '')
	{
		return str_replace(['@', '.'], ['(&#64;)', '(&#46;)'], $xenOptions->contactEmailAddress);
	}
	else if ($xenOptions->defaultEmailAddress != '')
	{
		return str_replace(['@', '.'], ['(&#64;)', '(&#46;)'], $xenOptions->defaultEmailAddress);
	}
	else
	{
		return '';
	}
}

// Converts vB's yes/no in vBulletin Options to true/false
function __bb2_read_settings_helper($value)
{
	return $value == 1;
}

// retrieve settings from database
function bb2_read_settings()
{
	$xenOptions = \XF::options();

	$apiKey = $xenOptions->dbtech_security_badbehavior_httpbl_key;

	// http:BL Do we have an API Key?
	// All Access Keys are 12-characters in length, lower case, and contain only alpha characters (no numbers).
	if (strlen($apiKey) != 12 or !ctype_lower($apiKey))
	{
		$apiKey = '';
	}

	// return settings
	return [
		'log_table'               => 'xf_dbtech_security_bad_behavior',
		'display_stats'           => false,
		'strict'                  => __bb2_read_settings_helper($xenOptions->dbtech_security_badbehavior_strict),
		'verbose'                 => __bb2_read_settings_helper($xenOptions->dbtech_security_badbehavior_verbose),
		'logging'                 => __bb2_read_settings_helper($xenOptions->dbtech_security_badbehavior_logging),
		'httpbl_key'              => $apiKey,
		'httpbl_threat'           => $xenOptions->dbtech_security_badbehavior_httpbl_threat ? $xenOptions->dbtech_security_badbehavior_httpbl_threat : 25,
		'httpbl_maxage'           => $xenOptions->dbtech_security_badbehavior_httpbl_maxage ? $xenOptions->dbtech_security_badbehavior_httpbl_maxage : 30,
		'eu_cookie'               => __bb2_read_settings_helper($xenOptions->dbtech_security_badbehavior_eucookie),
		'offsite_forms'           => true,
		'reverse_proxy'           => __bb2_read_settings_helper($xenOptions->dbtech_security_badbehavior_reverse_proxy['enabled']),
		'reverse_proxy_header'    => $xenOptions->dbtech_security_badbehavior_reverse_proxy['header'] ?: 'X-Forwarded-For',
		'reverse_proxy_addresses' => [],
	];
}

// Return the top-level relative path of wherever we are (for cookies)
// You should provide in $url the top-level URL for your site.
function bb2_relative_path()
{
	$xenOptions = \XF::options();

	$url = parse_url($xenOptions->boardUrl);
	return ($url['path'] ?? '') . '/';
}

// Calls inward to Bad Behavor itself.
require_once BB2_CWD . '/bad-behavior/core.inc.php';