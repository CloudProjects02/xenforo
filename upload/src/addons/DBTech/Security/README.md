DragonByte Security for XenForo 2.3.0+
======================================

![Deploy](https://github.com/DragonByteTech/xf2security/workflows/Deploy/badge.svg) ![Lint](https://github.com/DragonByteTech/xf2security/workflows/Lint/badge.svg)  
  
Description
-----------

Improves Two-Factor Authentication, provides security alerts, and more.

Requirements
------------

- PHP 8.0.0+

Recommendations
---------------

- PHP 8.2.0+

Options
-------

#### DragonByte Tech: Security

| Name | Description |
|---|---|
| Account lock route whitelist | If a user's account is locked then the routes listed here will bypass being redirected to the account unlock page. The route path is the section of the URL to a page after your main forum directory URL, such as forums/ or pages/page-name/. Do not reference a route filter here. |
| Account breach check |  |
| Fingerprinting |  |
| Always show CAPTCHA when logging in to |  |
| Force password change route whitelist | If you decide to [Force password change](admin.php?dbtech-security/passwords/force-change/) then the routes listed here will bypass being redirected to the password change form. The route path is the section of the URL to a page after your main forum directory URL, such as forums/ or pages/page-name/. Do not reference a route filter here. |
| Prune "Admin Strikes Log" (Days) | This setting lets you control the automatic pruning of the "Admin Strikes Log". Removing old entries can cut down on your database usage size and improve performance.   0 = No automatic prune |
| Exempt Registered Members | Enabling this setting will stop checking the block list when a member has been registered the specified amount of days. |
| Enable Bad Behaviour Detection | This setting controls whether the Bad Behaviour protection is enabled. |
| Disable EU Cookie Exemption | If you believe cookies set by Bad Behaviour is not exempt from EU's "Cookie Law", enable this setting.   [Click here for more information.](http://bad-behavior.ioerror.us/support/configuration/) |
| http:BL API Key | DragonByte Security can integrate with the [http:BL](http://www.projecthoneypot.org/faq.php#g) service provided by [Project Honey Pot](http://www.projecthoneypot.org/) to provide more accurate results.   To use this service, [sign up for the service](http://www.projecthoneypot.org/httpbl_configure.php) and obtain an API key. |
| http:BL Maximum Age | When Project Honey Pot records suspicious activity, it also stores when the last incident was recorded. This setting controls how far back Project Honey Pot should scan its records. |
| http:BL Threat Level | Project Honey Pot analyses each HTTP request and gives it a score, indicating how likely this request is to originate from a spammer. DragonByte Security will block requests with a threat level score equal or higher to this setting.   [Click here for more information.](http://www.projecthoneypot.org/threat_info.php) |
| Enable Logging | It is recommended to leave logging enabled, but you can disable it with this setting. |
| Reverse Proxy | If you are using a proxy service like CloudFlare on your site, you may need to enable this setting to obtain the "real IP" of your users.   Enter the header to look for in the text box. |
| Enable Strict Mode | If you enable Strict Mode, more aggressive spam checks are enabled, however it also increases the chance of false positives. |
| Enable Verbose Logging | Enabling verbose logging causes DragonByte Security to log every single HTTP request, which depending on the size of your forum may cause significant performance loss.   Only enable this if you are sure you need it! |
| Security breach: Banned reason | This is the notice that will be used as the ban reason if a user or an IP address is banned due to a watcher triggering. |
| Security breach closed reason | This is the notice that will be put up in place for when the forum is closed due to a watcher triggering. |
| Include Moderators | This setting controls whether Moderators are included in the "non-administrator" part of the recovery criteria. |
| Support Email | If you wish to use the Email Recovery feature in DragonByte Security, please enter the email you want to use for unhandled recovery requests. This mail account will receive a report of all unhandled recovery requests along with the scores from the defined criteria. |
| Threshold | Any email recovery requests whose score is below this threshold will be forwarded to the support email listed above. |
| Globally whitelisted IP addresses | Use this option to exclude certain IP addresses from being banned by DragonByte Security, regardless of their actions.      You may enter a partial IP address (v4 or v6 format). Partial IPv4 addresses can be entered in the form of 192.168.\* or 192.168.1.1/16. Partial IPv6 addresses may be entered in the form of 2001:db8::/32. |
| Prune "IP Matcher Log" (Days) | This setting lets you control the automatic pruning of the "IP Matcher" log entries. This log contains the latest IP addresses administrators used to login to the AdminCP. Removing old entries can cut down on your database usage size and improve performance.   0 = No automatic prune |
| Prune "Login Strikes Log" (Days) | This setting lets you control the automatic pruning of the "Login Strikes Log". Removing old entries can cut down on your database usage size and improve performance.   0 = No automatic prune |
| Extended spider identification | Enabling this setting will add an additional 1000+ spiders to the identification list, which will help you track visiting spiders but may increase memory usage. |
| Send webmaster alert on template edits | This setting controls whether the webmaster will receive an alert whenever someone edits a template. |
| Block TOR exit nodes | If you would like to block Tor users from accessing your forum, you can block their exit nodes with this setting. |

#### Debug options (Debug only)

| Name | Description |
|---|---|
| Whitelisted IP Addresses | Use this option to allow only certain IP addresses to access the AdminCP of your board.      You may enter a partial IP address (v4 or v6 format). Partial IPv4 addresses can be entered in the form of 192.168.\* or 192.168.1.1/16. Partial IPv6 addresses may be entered in the form of 2001:db8::/32.      Warning: Disabling the option below will render you unable to access the AdminCP if your IP address changes! |
| Whitelisted IP Addresses - Exclude Super Administrators | Disabling this setting will cause even Super Administrators to be subject to IP Address Whitelists.      Warning: Disabling this option will render you unable to access the AdminCP if your IP address changes! |
| DragonByte Security License Key | This is the license key associated with your product. Removing this will affect your ability to use the free "Product Manager" product to check for updates. |

Permissions
-----------

#### DragonByte Tech: Security Permissions

- Minimum Password Length
- Password Requires Lower-case Characters
- Password Requires Upper-case Characters
- Password Requires Numbers
- Password Requires Symbols
- Password Expiry (Days)

Admin Permissions
-----------------

- Manage DragonByte Security

Style Properties
----------------

#### DragonByte Security

| Property | Description |
|---|---|
| Password Rule: Good | What's displayed when a password rule matches the requirement. |
| Password Rule: Bad | What's displayed when a password rule does not match the requirement. |

Cron Entries
------------

| Name | Run on... | Run at hours | Run at minutes |
|---|---|---|---|
| DragonByte Security: Update country list | The 1st of the month | 3AM | 0 |
| DragonByte Security: Update country IP addresses | Every Wednesday | 12AM | 0 |
| DragonByte Security: Backup settings | Any day of the month | 12AM | 45 |
| DragonByte Security: Prune logs | Any day of the month | 12AM | 0 |
| DragonByte Security: Prune sessions | Any day of the month | Any | 0 |
| DragonByte Security: Update TOR exit nodes | Any day of the month | 5AM | 0 |