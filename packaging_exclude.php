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

return [
    'directories' => [
        '.build',
        '.ddev',
        '.git',
        '.github',
        'public',
        'tests',
        'var',
        'vendor',
    ],
    'files' => [
        'DS_Store',
        'CODEOWNERS',
        'composer.lock',
        'CONTRIBUTING.md',
        '.editorconfig',
        'editorconfig',
        '.gitattributes',
        'gitattributes',
        '.gitignore',
        'gitignore',
        'packaging_exclude.php',
        'phpunit.functional.xml',
        'renovate.json',
        'SECURITY.md',
        'version-bumper.yaml',
    ],
];
