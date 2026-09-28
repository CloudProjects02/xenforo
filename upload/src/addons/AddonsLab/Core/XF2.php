<?php

namespace AddonsLab\Core;

use AddonsLab\Core\Service\AccountDeleterInterface;
use AddonsLab\Core\Service\FlashMessageProvider;
use AddonsLab\Core\Service\ArrayHelper;
use AddonsLab\Core\Service\OptionBuilder;
use AddonsLab\Core\Service\PhraseMapper;
use AddonsLab\Core\Service\ThreadCopyProviderInterface;
use AddonsLab\Core\Service\UrlBuilder;
use AddonsLab\Core\Service\Utf8Converter;
use AddonsLab\Core\Xf2\AppTrait;
use AddonsLab\Licensing\LicenseValidationService;

class XF2 extends App
{
    use AppTrait;
}