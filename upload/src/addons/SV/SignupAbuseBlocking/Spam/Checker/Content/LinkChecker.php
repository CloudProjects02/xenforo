<?php
/**
 * @noinspection RegExpRedundantEscape
 */

namespace SV\SignupAbuseBlocking\Spam\Checker\Content;

use SV\SignupAbuseBlocking\Globals;
use SV\StandardLib\Helper;
use XF\Entity\User as UserAlias;
use XF\Mvc\Entity\Entity;
use XF\Spam\Checker\AbstractProvider;
use XF\Spam\Checker\ContentCheckerInterface;
use XF\Util\Arr;
use function array_filter;
use function array_map;
use function array_merge;
use function implode;
use function preg_match_all;
use function preg_quote;
use function preg_replace;

class LinkChecker extends AbstractProvider implements ContentCheckerInterface
{
    protected function getType(): string
    {
        return 'LinkChecker';
    }

    public function check(UserAlias $user, $message, array $extraParams = []): void
    {
        $options = $this->app()->options();

        $contentType = $extraParams['content_type'] ?? '';
        if ($contentType === '')
        {
            $entity = $extraParams['entity'] ?? null;
            if ($entity instanceof Entity)
            {
                $contentType = $entity->structure()->contentType ?? '';
            }
        }

        if (\strlen($contentType) !== 0)
        {
            // auto-link this if it is known to be "structured text"
            $types = Arr::stringToArray($options->svLinkSpamCheckerStructuredTextTypes ?? '', '/\r?\n/');
            if (in_array($contentType, $types, true))
            {
                // renders to html, but getUrlsFromMessage will extract http/https links anyway
                $message = \XF::app()->stringFormatter()->autoLinkStructuredText($message);
            }
        }

        if (Globals::$duringRegistration ?? false)
        {
            $defaultAction = $options->svLinkSpamCheckerRegistrationDefaultAction ?? '';
        }
        else
        {
            $defaultAction = $options->svLinkSpamCheckerDefaultAction ?? '';
            if (\strlen($contentType) !== 0)
            {
                $contentType = $this->shimContentType($contentType);
                $actions = $options->svLinkSpamCheckerDefaultActionByContentType ?? [];
                $contentAction = $actions[$contentType] ?? '';
                if (\strlen($contentAction) !== 0)
                {
                    $defaultAction = $contentAction;
                }
            }
        }
        $defaultAllowed = $defaultAction === 'allowed';

        $rawAllowList = \trim($options->svLinkSpamCheckerAllowedList ?? '');
        $rawModerateList = \trim($options->svLinkSpamCheckerModerateList ?? '');
        $rawDeniedList = \trim($options->svLinkSpamCheckerRejectList ?? '');

        if ($defaultAllowed && \strlen($rawModerateList) === 0 && \strlen($rawDeniedList) === 0)
        {
            $this->logDecision('allowed');

            return;
        }

        $extractedUrls = $this->getUrlsFromMessage($message);
        if (!$extractedUrls)
        {
            $this->logDecision('allowed');

            return;
        }

        $allowedList = $this->buildDomainList($rawAllowList);
        $moderateList = $this->buildDomainList($rawModerateList);
        $deniedList = $this->buildDomainList($rawDeniedList);

        // build the build list
        $domainList = $allowedList;
        $acceptedEnd = \count($domainList);
        if ($moderateList)
        {
            $domainList = array_merge($domainList, $moderateList);
        }
        $moderatedEnd = \count($domainList);
        if ($deniedList)
        {
            $domainList = array_merge($domainList, $deniedList);
        }

        if ($domainList)
        {
            $indexes = [];
            if ($this->matchDomainInList($domainList, $extractedUrls, $indexes))
            {
                foreach ($indexes as $i)
                {
                    $value = $i - 1;
                    if ($value < $acceptedEnd)
                    {
                        $this->logDecision('allowed');
                    }
                    else if ($value < $moderatedEnd)
                    {
                        $this->logDetail('spam_phrase_matched_x', [
                            'phrase' => $domainList[$value]
                        ]);
                        $this->logDecision('moderated');
                    }
                    else
                    {
                        $this->logDetail('spam_phrase_matched_x', [
                            'phrase' => $domainList[$value]
                        ]);
                        $this->logDecision('denied');
                    }
                }

                return;
            }
        }

        if (!$defaultAllowed)
        {
            $this->logDetail('spam_phrase_matched_x', [
                'phrase' => \XF::phrase('sv_reg_log.url_block')->render(),
            ]);
        }
        $this->logDecision($defaultAction);
    }

    protected function shimContentType(string $contentType): string
    {
        if ($contentType === 'ticket' && Helper::isAddOnActive('NF/Tickets', '2.10.3', '<='))
        {
            return 'nf_tickets_ticket';
        }

        return $contentType;
    }

    protected $bbCodeTagsWithUrls = [
        'url',
        'img',
        'bimg',
        'email',
    ];

    protected function extractUrlsWithRegex(string $s): array
    {
        $tags = implode('|', array_map(function (string $s) {
            return preg_quote($s);
        }, $this->bbCodeTagsWithUrls));

        /** @noinspection RegExpSuspiciousBackref */
        if (preg_match_all('{
				(?: # delimiter that suggests the start of a url
                    (?(DEFINE)
                        (?<scheme> (?:ftp|https?):// )
                        (?<tags>   ' . $tags . ' )
                    )
				    (?&scheme) |                               # bare url
				    (?:src|href)=[\'"]\s*(?&scheme)? |         # relative URL in a html tag
				    \[(?&tags)\s*=\s*(?:\'|"|)\s*(?&scheme)? | # url in tag argument
				    \[(?&tags)\]\s*(?&scheme)?                 # url in tag body
				)
				\s*
                (?: # Skip username/passwords in urls
				    [\w\.-]+(?:\:[\w\.-]+)?@
				)?
				(?: # hilarious flexible definition of a url domain
					\[ (?<ipv6>   [0-9a-f:]+  ) ]  |     #  IPv6, require square brackets in urls
					   (?<domain> [\w\.-]+       )       #  Domains/ipv4
				)
			}xiu',
            $s,
            $matches
        ))
        {
            return  array_merge(array_filter($matches['domain']), array_filter($matches['ipv6']));
        }

        return [];
    }

    /**
     * @param $message
     * @return string[]
     */
    protected function getUrlsFromMessage($message): array
    {
        $urls = [];

        if (preg_match_all('/\[email\](.*?)\[\/email\]/ui', $message, $matches) && !empty($matches[1]))
        {
            $urls = array_merge($urls, $matches[1]);
        }

        $urls = array_merge($urls, $this->extractUrlsWithRegex($message));

        if (preg_match_all('/(\[media=.*?\].*?\[\/media\])/ui', $message, $matches) && !empty($matches[1]))
        {
            $embedSites = \XF::app()->container('bbCode.media');
            // on finding media bb-codes, render and then try to extract URLs
            $mediaEmbeds = \array_unique($matches[1]);
            foreach ($mediaEmbeds as $mediaEmbed)
            {
                // try extracting from the site_url for the embed first
                $urlsFromEmbeds = [];
                if (\preg_match('/^\[media=(.*?)\]/ui', $mediaEmbed, $matches) && !empty($matches[1]))
                {
                    $embedName = \mb_strtolower($matches[1]);
                    if (isset($embedSites[$embedName]))
                    {
                        $url = trim($embedSites[$embedName]['site_url'] ?? '');
                        if ($url !== '')
                        {
                            if (mb_strpos('://', $url) !== 0)
                            {
                                /** @noinspection HttpUrlsUsage */
                                $url = 'http://' . $url;
                            }
                            $urlsFromEmbeds = array_merge($urlsFromEmbeds, $this->extractUrlsWithRegex($url));
                        }
                    }
                }

                $urlsFromEmbeds = array_filter($urlsFromEmbeds);

                // fall back on parsing the bb-code
                if (count($urlsFromEmbeds) === 0)
                {
                    $snippet = $this->app->bbCode()->render($mediaEmbed, 'html', 'post:preview', null);
                    $urlsFromEmbeds = $this->extractUrlsWithRegex($snippet);
                }

                $urls = array_merge($urls, $urlsFromEmbeds);
            }
        }

        $filteredUrls = [];
        foreach ($urls as $s)
        {
            $s = $this->normalizeDomain($s);

            $filteredUrls[$s] = true;
        }
        // remove empty url
        unset($filteredUrls['']);
        // remove s9e/MediaEmbed proxies
        unset($filteredUrls['s9e.github.io']);

        return \array_keys($filteredUrls);
    }

    /**
     * @param string $filter
     * @return string[]
     */
    protected function buildDomainList(string $filter): array
    {
        if (\strlen($filter) === 0)
        {
            return [];
        }

        return \array_unique(array_filter(array_map([$this, 'normalizeDomain'], Arr::stringToArray($filter, '/\r?\n/u'))));
    }

    protected function normalizeDomain(string $url): string
    {
        // apply some normalization (whitespace, and remove trailing '.')
        $url = preg_replace('/^\s+/u', '', $url);
        $url = preg_replace('/[\.\s]+$/u', '', $url);
        // normalise utf8 to punycode
        $s = \idn_to_ascii($url);
        if ($s === false)
        {
            $s = $url;
        }

        return $s;
    }

    /**
     * @param string[] $domainList
     * @param string[] $extractedUrls
     * @param string[] $matchedIndexes
     * @return bool
     */
    protected function matchDomainInList(array $domainList, array $extractedUrls, array &$matchedIndexes): bool
    {
        $matchedIndexes = [];
        $regex = '/' . implode('|', array_map(function (string $item) {
                return '(' . preg_quote($item, '/') . '$)';
            }, $domainList)) . '/ui';

        foreach ($extractedUrls as $url)
        {
            if (\preg_match($regex, $url, $matches))
            {
                // start from 1 to skip whole string match
                for ($i = 1; $i < \count($matches); $i++)
                {
                    if (!isset($matchedIndexes[$i]) && !empty($matches[$i]))
                    {
                        $matchedIndexes[$i] = true;
                    }
                }
            }
        }

        $matchedIndexes = \array_keys($matchedIndexes);

        return \count($matchedIndexes) > 0;
    }

    public function submitSpam($contentType, $contentIds)
    {
    }

    public function submitHam($contentType, $contentIds)
    {
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
