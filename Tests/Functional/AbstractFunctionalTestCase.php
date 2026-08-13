<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_mcp_server_content_planner" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\Typo3McpServerContentPlanner\Tests\Functional;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

/**
 * AbstractFunctionalTestCase.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
abstract class AbstractFunctionalTestCase extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'workspaces',
    ];

    protected array $testExtensionsToLoad = [
        'hn/typo3-mcp-server',
        'xima/xima-typo3-content-planner',
    ];

    /**
     * hn/typo3-mcp-server and xima/xima-typo3-content-planner still ship ext_emconf.php
     * without the composer.json "providesPackages" declaration TYPO3 core now expects,
     * triggering deprecation-108345 on every package load. Neither dependency is ours to
     * patch, so the known, upstream-only warning is filtered here instead of drowning out
     * deprecations our own code might trigger.
     */
    protected function setUp(): void
    {
        $previousHandler = set_error_handler(static function (int $errno, string $errstr) use (&$previousHandler): bool {
            if (\E_USER_DEPRECATED === $errno && str_contains($errstr, 'deprecation-108345')) {
                return true;
            }

            return null !== $previousHandler && false !== $previousHandler($errno, $errstr);
        });

        try {
            parent::setUp();
        } finally {
            restore_error_handler();
        }
    }

    protected function importFixture(string $fileName): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/'.$fileName);
    }

    protected function loginBackendUser(int $userUid = 1): BackendUserAuthentication
    {
        $this->importFixture('be_users.csv');

        return $this->switchToBackendUser($userUid);
    }

    /**
     * Logs in as one of be_users_restricted.csv's non-admin users (each paired with a
     * be_groups_restricted.csv group denying exactly one permission), to exercise a
     * PermissionUtility check's false branch. Separate from loginBackendUser() because
     * that one always (re-)imports be_users.csv, which fixture tests already relying
     * on the uid=1 admin user (via setUp()) would collide with on re-import.
     */
    protected function loginRestrictedBackendUser(int $userUid): BackendUserAuthentication
    {
        $this->importFixture('be_groups_restricted.csv');
        $this->importFixture('be_users_restricted.csv');

        return $this->switchToBackendUser($userUid);
    }

    private function switchToBackendUser(int $userUid): BackendUserAuthentication
    {
        // PermissionUtility memoizes getAllowedTablesForUser()/getAllowedStatusUidsForUser()
        // per-request, keyed only by column name - not by user. Switching the acting
        // backend user mid-test-run without resetting it would silently reuse whatever
        // the previously logged-in user's groups resolved to.
        PermissionUtility::resetCache();

        $backendUser = $this->setUpBackendUser($userUid);
        $GLOBALS['BE_USER'] = $backendUser;
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);

        return $backendUser;
    }
}
