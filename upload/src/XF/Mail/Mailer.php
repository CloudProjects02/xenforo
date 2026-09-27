<?php

namespace XF\Mail;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;
use XF\Entity\User;
use XF\Job\MailSend;
use XF\Language;

use function count, is_array;

class Mailer
{
	/**
	 * @var Templater
	 */
	protected $templater;

	/**
	 * @var AbstractTransport
	 */
	protected $defaultTransport;

	/**
	 * @var Styler|null
	 */
	protected $styler;

	/**
	 * @var bool
	 */
	protected $queue;

	/**
	 * @var DkimSigner|null
	 */
	protected $signer;

	/**
	 * @var string|null
	 */
	protected $defaultFromEmail;

	/**
	 * @var string|null
	 */
	protected $defaultFromName;

	/**
	 * @var string|null
	 */
	protected $defaultReturnPath;

	/**
	 * @var bool
	 */
	protected $defaultUseVerp;

	/**
	 * @var class-string<Mail>
	 */
	protected $mailClass = Mail::class;

	public function __construct(Templater $templater, AbstractTransport $defaultTransport, ?Styler $styler = null, bool $queue = true)
	{
		$this->templater = $templater;
		$this->defaultTransport = $defaultTransport;
		$this->styler = $styler;
		$this->queue = $queue;

		$dkimOptions = \XF::options()->emailDkim;
		if (
			$dkimOptions['enabled']
			&& $dkimOptions['verified']
			&& extension_loaded('openssl')
		)
		{
			$key = \XF::registry()->get('emailDkimKey');

			if ($key)
			{
				$selector = $dkimOptions['selector'] ?? 'xenforo';
				$this->signer = new DkimSigner(
					$key,
					$dkimOptions['domain'],
					$selector
				);
			}
		}

	}

	/**
	 * @return class-string<Mail>
	 */
	public function getMailClass()
	{
		return $this->mailClass;
	}

	/**
	 * @param class-string<Mail> $class
	 *
	 * @return void
	 */
	public function setMailClass($class)
	{
		$this->mailClass = $class;
	}

	/**
	 * @param string $email
	 * @param string|null $name
	 *
	 * @return void
	 */
	public function setDefaultFrom($email, $name = null)
	{
		if ($email)
		{
			$this->defaultFromEmail = $email;
			$this->defaultFromName = $name;
		}
		else
		{
			$this->defaultFromEmail = null;
			$this->defaultFromName = null;
		}
	}

	/**
	 * @return string|null
	 */
	public function getDefaultFromEmail()
	{
		return $this->defaultFromEmail;
	}

	/**
	 * @return string|null
	 */
	public function getDefaultFromName()
	{
		return $this->defaultFromName;
	}

	/**
	 * @param string|null $email
	 * @param bool $useVerp
	 *
	 * @return void
	 */
	public function setDefaultReturnPath($email, $useVerp = false)
	{
		if ($email)
		{
			$this->defaultReturnPath = $email;
		}
		else
		{
			$this->defaultReturnPath = null;
		}
	}

	/**
	 * @return string|null
	 */
	public function getDefaultReturnPath()
	{
		return $this->defaultReturnPath;
	}

	/**
	 * @return void
	 */
	public function setDefaultUseVerp(bool $useVerp = false)
	{
		$this->defaultUseVerp = $useVerp;
	}

	/**
	 * @return bool
	 */
	public function getDefaultUseVerp()
	{
		return $this->defaultUseVerp;
	}

	/**
	 * @return Mail
	 */
	public function newMail()
	{
		$mailClass = $this->mailClass;
		$mail = new $mailClass($this);
		$this->applyMailDefaults($mail);

		return $mail;
	}

	/**
	 * @return void
	 */
	public function applyMailDefaults(Mail $mail)
	{
		if ($this->defaultFromEmail)
		{
			$mail->setFrom($this->defaultFromEmail, $this->defaultFromName);
		}
		if ($this->defaultReturnPath)
		{
			$mail->setReturnPath($this->defaultReturnPath, $this->defaultUseVerp);
		}
	}

	/**
	 * @param string $toEmail
	 *
	 * @return string
	 */
	public function calculateBounceHmac($toEmail)
	{
		return substr(hash_hmac('md5', $toEmail, \XF::config('globalSalt')), 0, 8);
	}

	/**
	 * @param string $html
	 *
	 * @return string
	 */
	public function generateTextBody($html)
	{
		if ($this->styler)
		{
			return $this->styler->generateTextBody($html);
		}

		return '';
	}

	/**
	 * @param string $name
	 * @param array<string, mixed> $params
	 *
	 * @return array{
	 *     subject: string,
	 *     html: string,
	 *     text: string,
	 *     headers: array<string, string>,
	 * }
	 */
	public function renderMailTemplate($name, array $params, ?Language $language = null, ?User $toUser = null)
	{
		if (!$language)
		{
			$language = \XF::language();
		}

		$templater = $this->templater;

		// for info purposes
		$params['template'] = $name;

		$output = $this->renderPartialMailTemplate($name, $params, $language, $toUser);
		$parts = $this->pullComponentsFromTemplateOutput($output);

		if (!$parts['text'] && !$parts['html'])
		{
			throw new \LogicException("Template email:$name did not render to anything. It must provide either a text or HTML body.");
		}

		$containerTemplate = $templater->pageParams['template'] ?? 'MAIL_CONTAINER';
		if ($containerTemplate)
		{
			if (!strpos($containerTemplate, ':'))
			{
				$containerTemplate = 'email:' . $containerTemplate;
			}

			$containerParams = array_replace($templater->pageParams, $parts);

			$containerOutput = $templater->renderTemplate($containerTemplate, $containerParams);
			$containerParts = $this->pullComponentsFromTemplateOutput($containerOutput);
		}
		else
		{
			$containerParts = ['subject' => '', 'html' => '', 'text' => ''];
		}

		$subject = $parts['subject'] && $containerParts['subject'] ? $containerParts['subject'] : $parts['subject'];
		$html = $parts['html'] && $containerParts['html'] ? $containerParts['html'] : $parts['html'];
		$text = $parts['text'] && $containerParts['text'] ? $containerParts['text'] : $parts['text'];

		if ($this->styler)
		{
			$html = $this->styler->styleHtml($html, $containerTemplate ? true : false, $language);
		}

		if (isset($templater->pageParams['headers']) && is_array($templater->pageParams['headers']))
		{
			$headers = $templater->pageParams['headers'];
		}
		else
		{
			$headers = [];
		}

		return [
			'subject' => $subject,
			'html' => $html,
			'text' => $text,
			'headers' => $headers,
		];
	}

	/**
	 * @param string $name
	 * @param array<string, mixed> $params
	 *
	 * @return string
	 */
	public function renderPartialMailTemplate($name, array $params, ?Language $language = null, ?User $toUser = null)
	{
		if (!$language)
		{
			$language = \XF::language();
		}

		$defaultParams = $this->getDefaultTemplateParams($language, $toUser);

		$templater = $this->templater;
		$templater->setLanguage($language);
		$templater->addDefaultParam('xf', $defaultParams);
		$templater->pageParams = [];

		return $templater->renderTemplate("email:$name", $params);
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function getDefaultTemplateParams(Language $language, ?User $toUser = null)
	{
		$app = \XF::app();

		return [
			'versionVisible' => preg_replace('/^(\d+)\.(\d+)\..+$/', '$1.$2', \XF::$version),
			'versionId' => \XF::$versionId,
			'version' => \XF::$version,
			'app' => $app,
			'time' => \XF::$time,
			'timeDetails' => $language->getDayStartTimestamps(),
			'debug' => \XF::$debugMode,
			'development' => \XF::$developmentMode,
			'designer' => $app->config('designer')['enabled'],
			'toUser' => $toUser,
			'language' => $language,
			'style' => $this->templater->getStyle(),
			'isRtl' => $language->isRtl(),
			'options' => $app->options(),
			'reactions' => $app->get('reactions'),
			'reactionsActive' => array_filter($app->get('reactions'), function (array $reaction)
			{
				return ($reaction['active'] === true);
			}),
			'addOns' => $app->container('addon.cache'),
			'simpleCache' => $app->simpleCache(),
			'contactUrl' => $app->container('contactUrl'),
			'privacyPolicyUrl' => $app->container('privacyPolicyUrl'),
			'tosUrl' => $app->container('tosUrl'),
			'homePageUrl' => $app->container('homePageUrl'),
			'helpPageCount' => $app->container('helpPageCount'),
		];
	}

	/**
	 * @param string $output
	 *
	 * @return array{subject: string, html: string, text: string}
	 */
	protected function pullComponentsFromTemplateOutput($output)
	{
		if (preg_match('#<mail:subject>(.*)</mail:subject>#siU', $output, $match))
		{
			$subject = trim(htmlspecialchars_decode($match[1], ENT_QUOTES));
			$output = preg_replace('#<mail:subject>.*</mail:subject>#siU', '', $output);
		}
		else
		{
			$subject = '';
		}

		if (preg_match('#<mail:text>(.*)</mail:text>#siU', $output, $match))
		{
			$text = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML401, "utf-8"));
			$output = preg_replace('#<mail:text>.*</mail:text>#siU', '', $output);
		}
		else
		{
			$text = '';
		}

		if (preg_match('#<mail:html>(.*)</mail:html>#siU', $output, $match))
		{
			$html = trim($match[1]);
		}
		else
		{
			$html = trim($output);
		}

		if (!$text && $html)
		{
			$text = $this->generateTextBody($html);
		}

		return [
			'subject' => $subject,
			'html' => $html,
			'text' => $text,
		];
	}

	/**
	 * @return SentMessage|false|null
	 */
	public function send(Message $email, ?AbstractTransport $transport = null)
	{
		$email = MessageConverter::toEmail($email);
		$originalEmail = $email;

		if ($this->signer)
		{
			$from = $email->getFrom();

			if (count($from) === 1)
			{
				$dkimOptions = \XF::options()->emailDkim;
				$fromAddress = $from[0]->getAddress();
				$fromDomain = substr(strrchr($fromAddress, '@') ?: '', 1);

				if ($dkimOptions['domain'] === $fromDomain)
				{
					$email = $this->signer->sign($email);
				}
			}
		}

		if (!$transport)
		{
			$transport = $this->defaultTransport;
		}

		$sent = false;

		try
		{
			$sent = $transport->send($email);

			if (!$sent)
			{
				throw new TransportException('Unable to send mail.');
			}
		}
		catch (\Throwable $e)
		{
			$toEmails = implode(', ', array_map(
				function (Address $address): string
				{
					return $address->getAddress();
				},
				$originalEmail->getTo()
			));
			$fromEmail = implode(', ', array_map(
				function (Address $address): string
				{
					return $address->getAddress();
				},
				$originalEmail->getFrom()
			));

			\XF::logException($e, false, "Email to {$toEmails} from {$fromEmail} failed:");
		}

		return $sent;
	}

	/**
	 * @return int|SentMessage|false|null
	 */
	public function queue(Message $email)
	{
		if (!$this->queue)
		{
			// Queue may be disabled in config.php so skip straight to send
			return $this->send($email);
		}
		return \XF::app()->jobManager()->enqueue(MailSend::class, ['email' => $email]);
	}

	/**
	 * @return AbstractTransport
	 */
	public function getDefaultTransport()
	{
		return $this->defaultTransport;
	}

	/**
	 * @return void
	 */
	public function setDefaultTransport(AbstractTransport $transport)
	{
		$this->defaultTransport = $transport;
	}

	/**
	 * @param string $type
	 * @param array<string, mixed> $config
	 *
	 * @return TransportInterface
	 */
	public static function getTransportFromOption($type, array $config)
	{
		switch ($type)
		{
			case 'smtp':
				$factory = new EsmtpTransportFactory();

				// older option values may have the port as a string
				$config['smtpPort'] = isset($config['smtpPort'])
					? (int) $config['smtpPort']
					: null;

				/** @var EsmtpTransport $transport */
				$transport = $factory->create(new Dsn(
					$config['smtpSsl'] ? 'smtps' : 'smtp',
					$config['smtpHost'],
					$config['smtpLoginUsername'] ?? null,
					$config['smtpLoginPassword'] ?? null,
					$config['smtpPort'] ?? null
				));

				// symfony forces ssl for port 465
				if (($config['smtpPort'] ?? null) === 465 && !$config['smtpSsl'])
				{
					/** @var SocketStream $stream */
					$stream = $transport->getStream();
					$stream->disableTls();
				}

				$hostname = gethostname();
				if ($hostname !== false && $hostname !== '')
				{
					$transport->setLocalDomain($hostname);
				}

				$pingThreshold = (int) \XF::app()->config('smtpKeepAlivePingThreshold');
				if ($pingThreshold > 0)
				{
					$transport->setPingThreshold($pingThreshold);
				}

				return $transport;

			case 'file':
				$transport = new FileTransport();
				if (!empty($config['path']))
				{
					$transport->setSavePath($config['path']);
				}

				return $transport;

			case 'sendmail':
			default:
				if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')
				{
					$iniSmtpHost = ini_get('SMTP');
					$iniSmtpPort = (int) ini_get('smtp_port');

					$factory = new EsmtpTransportFactory();
					/** @var EsmtpTransport $transport */
					$transport = $factory->create(new Dsn(
						'',
						$iniSmtpHost ?: 'localhost',
						null,
						null,
						$iniSmtpPort ?: 25
					));

					$hostname = gethostname();
					if ($hostname !== false && $hostname !== '')
					{
						$transport->setLocalDomain($hostname);
					}
				}
				else
				{
					$sendmailPath = \XF::app()->config('sendmailPath');
					if (!$sendmailPath)
					{
						$sendmailPath = ini_get('sendmail_path');
					}

					if ($sendmailPath && !preg_match('# -(t|bs)#', $sendmailPath))
					{
						// Symfony Mailer requires -t or -bs, so if there isn't one, add -t automatically to prevent errors
						$sendmailPath .= ' -t';
					}

					if (preg_match('/(.*)-f\s?[^@]+@[^\s]+(.*)$/', $sendmailPath, $matches))
					{
						// if the sendmail path already contains the -f parameter, Symfony Mailer won't override it in which
						// case, we should remove it by default so it can be set automatically to the appropriate value
						$sendmailPath = trim(rtrim($matches[1]) . $matches[2]);
					}

					if (!$sendmailPath)
					{
						$sendmailPath = '/usr/sbin/sendmail -t -i';
					}

					$transport = new SendmailTransport($sendmailPath);
				}

				return $transport;
		}
	}
}
