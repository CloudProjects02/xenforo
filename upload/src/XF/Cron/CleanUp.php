<?php

namespace XF\Cron;

use XF\Repository\ActivityLogRepository;
use XF\Repository\AddOnRepository;
use XF\Repository\AdminLogRepository;
use XF\Repository\ApiRepository;
use XF\Repository\AttachmentRepository;
use XF\Repository\CaptchaQuestionRepository;
use XF\Repository\ChangeLogRepository;
use XF\Repository\CookieConsentRepository;
use XF\Repository\DraftRepository;
use XF\Repository\EditHistoryRepository;
use XF\Repository\ErrorLogRepository;
use XF\Repository\FileCheckRepository;
use XF\Repository\FindNewRepository;
use XF\Repository\ForumRepository;
use XF\Repository\ImageProxyRepository;
use XF\Repository\IpRepository;
use XF\Repository\LinkProxyRepository;
use XF\Repository\LoginAttemptRepository;
use XF\Repository\ModeratorLogRepository;
use XF\Repository\NewsFeedRepository;
use XF\Repository\OAuthRepository;
use XF\Repository\OembedRepository;
use XF\Repository\PreRegActionRepository;
use XF\Repository\SearchRepository;
use XF\Repository\SessionActivityRepository;
use XF\Repository\SpamRepository;
use XF\Repository\TagRepository;
use XF\Repository\TemplateRepository;
use XF\Repository\TfaAttemptRepository;
use XF\Repository\ThreadRedirectRepository;
use XF\Repository\ThreadReplyBanRepository;
use XF\Repository\ThreadRepository;
use XF\Repository\TrendingContentRepository;
use XF\Repository\UpgradeCheckRepository;
use XF\Repository\UserAlertRepository;
use XF\Repository\UserChangeTempRepository;
use XF\Repository\UserConfirmationRepository;
use XF\Repository\UserRememberRepository;
use XF\Repository\UserTfaTrustedRepository;
use XF\Repository\UserUpgradeRepository;
use XF\Service\FloodCheckService;
use XF\Session\StorageInterface;
use XF\Util\File;

class CleanUp
{
	/**
	 * Clean up tasks that should be done daily. This task cannot be relied on
	 * to run daily, consistently.
	 */
	public static function runDailyCleanUp()
	{
		$app = \XF::app();

		$threadRepo = $app->repository(ThreadRepository::class);
		$threadRepo->pruneThreadReadLogs();

		$forumRepo = $app->repository(ForumRepository::class);
		$forumRepo->pruneForumReadLogs();

		/** @var Template $templateRepo */
		$templateRepo = $app->repository(TemplateRepository::class);
		$templateRepo->pruneEditHistory();

		$ipRepo = $app->repository(IpRepository::class);
		$ipRepo->pruneIps();

		$draftRepo = $app->repository(DraftRepository::class);
		$draftRepo->pruneDrafts();

		$preRegActionRepo = $app->repository(PreRegActionRepository::class);
		$preRegActionRepo->pruneActions();

		$searchRepo = $app->repository(SearchRepository::class);
		$searchRepo->pruneSearches();

		$findNewRepo = $app->repository(FindNewRepository::class);
		$findNewRepo->pruneFindNewResults();

		$modLogRepo = $app->repository(ModeratorLogRepository::class);
		$modLogRepo->pruneModeratorLogs();

		$adminLogRepo = $app->repository(AdminLogRepository::class);
		$adminLogRepo->pruneAdminLogs();

		$cookieConsentRepo = $app->repository(CookieConsentRepository::class);
		$cookieConsentRepo->pruneCookieConsentLogs();

		$tagRepo = $app->repository(TagRepository::class);
		$tagRepo->pruneTagResultsCache();

		$tfaTrustRepo = $app->repository(UserTfaTrustedRepository::class);
		$tfaTrustRepo->pruneTrustedKeys();

		$editHistoryRepo = $app->repository(EditHistoryRepository::class);
		$editHistoryRepo->pruneEditHistory();

		$fileCheckRepo = $app->repository(FileCheckRepository::class);
		$fileCheckRepo->pruneFileChecks();

		$addOnRepo = $app->repository(AddOnRepository::class);
		$addOnRepo->cleanUpAddOnBatches();

		$upgradeCheckRepo = $app->repository(UpgradeCheckRepository::class);
		$upgradeCheckRepo->pruneUpgradeChecks();

		$oAuthRepo = $app->repository(OAuthRepository::class);
		$oAuthRepo->pruneExpiredCodes();
		$oAuthRepo->pruneAuthRequests();

		$trendingContentRepo = $app->repository(TrendingContentRepository::class);
		$trendingContentRepo->pruneResults();
	}

	/**
	 * Clean up tasks that should be done hourly. This task cannot be relied on
	 * to run every hour, consistently.
	 */
	public static function runHourlyCleanUp()
	{
		$app = \XF::app();

		/** @var StorageInterface $publicSessionStorage */
		$publicSessionStorage = $app->container('session.public.storage');
		$publicSessionStorage->deleteExpiredSessions();

		/** @var StorageInterface $adminSessionStorage */
		$adminSessionStorage = $app->container('session.admin.storage');
		$adminSessionStorage->deleteExpiredSessions();

		$activityRepo = $app->repository(SessionActivityRepository::class);
		$activityRepo->updateUserLastActivityFromSession();
		$activityRepo->pruneExpiredActivityRecords();

		$rememberRepo = $app->repository(UserRememberRepository::class);
		$rememberRepo->pruneExpiredRememberRecords();

		$captchaQuestion = $app->repository(CaptchaQuestionRepository::class);
		$captchaQuestion->cleanUpCaptchaLog();

		$loginRepo = $app->repository(LoginAttemptRepository::class);
		$loginRepo->cleanUpLoginAttempts();

		$tfaAttemptRepo = $app->repository(TfaAttemptRepository::class);
		$tfaAttemptRepo->cleanUpTfaAttempts();

		$userConfirmationRepo = $app->repository(UserConfirmationRepository::class);
		$userConfirmationRepo->cleanUpUserConfirmationRecords();

		$attachmentRepo = $app->repository(AttachmentRepository::class);
		$attachmentRepo->deleteUnassociatedAttachments();
		$attachmentRepo->deleteUnusedAttachmentData();

		$apiRepo = $app->repository(ApiRepository::class);
		$apiRepo->pruneAttachmentKeys();
		$apiRepo->pruneLoginTokens();

		$alertRepo = $app->repository(UserAlertRepository::class);

		$iterations = 0;
		do
		{
			$deleted = $alertRepo->pruneViewedAlerts();
			$iterations++;
		}
		while ($deleted >= 1000 && $iterations < 50);

		$iterations = 0;
		do
		{
			$deleted = $alertRepo->pruneUnviewedAlerts();
			$iterations++;
		}
		while ($deleted >= 1000 && $iterations < 50);

		$redirectRepo = $app->repository(ThreadRedirectRepository::class);
		$redirectRepo->pruneThreadRedirects();

		$floodChecker = $app->service(FloodCheckService::class);
		$floodChecker->pruneFloodCheckData();

		$spamRepo = $app->repository(SpamRepository::class);
		$spamRepo->cleanUpRegistrationResultCache();
		$spamRepo->cleanupContentSpamCheck();
		$spamRepo->cleanupSpamTriggerLog();

		$imageProxyRepo = $app->repository(ImageProxyRepository::class);
		$imageProxyRepo->pruneImageCache();
		$imageProxyRepo->pruneImageProxyLogs();
		$imageProxyRepo->pruneImageReferrerLogs();

		$oembedRepo = $app->repository(OembedRepository::class);
		$oembedRepo->pruneOembedCache();
		$oembedRepo->pruneOembedLogs();
		$oembedRepo->pruneOembedReferrerLogs();

		$linkProxyRepo = $app->repository(LinkProxyRepository::class);
		$linkProxyRepo->pruneLinkProxyLogs();
		$linkProxyRepo->pruneLinkReferrerLogs();

		$threadReplyBanRepo = $app->repository(ThreadReplyBanRepository::class);
		$threadReplyBanRepo->cleanUpExpiredBans();

		$newsFeedRepo = $app->repository(NewsFeedRepository::class);
		$newsFeedRepo->cleanUpNewsFeedItems();

		$activityLogRepo = $app->repository(ActivityLogRepository::class);
		$activityLogRepo->pruneLogs();

		$changeLogRepo = $app->repository(ChangeLogRepository::class);
		$changeLogRepo->pruneChangeLogs();

		$errorLogRepo = $app->repository(ErrorLogRepository::class);
		$errorLogRepo->pruneErrorLogs();

		File::cleanUpPersistentTempFiles();
	}

	/**
	 * Downgrades expired user upgrades.
	 */
	public static function runUserDowngrade()
	{
		$userUpgradeRepo = \XF::repository(UserUpgradeRepository::class);
		$userUpgradeRepo->downgradeExpiredUpgrades();
	}

	/**
	 * Expire temporary user changes.
	 */
	public static function expireTempUserChanges()
	{
		$userChangeRepo = \XF::repository(UserChangeTempRepository::class);
		$userChangeRepo->removeExpiredChanges();
	}
}
