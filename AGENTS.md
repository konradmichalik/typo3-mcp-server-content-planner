# AGENTS.md

## Project overview

TYPO3 extension `typo3_mcp_server_content_planner` (`konradmichalik/typo3-mcp-server-content-planner`). It bridges `hn/typo3-mcp-server` and `xima/xima-typo3-content-planner` and exposes Content Planner status, assignee and comment workflows as MCP tools. It ships no own database schema, backend module or configuration. The tools register automatically once both host extensions are installed.

- PHP: `~8.2 || ~8.3 || ~8.4 || ~8.5`
- TYPO3: `^13.4 || ^14.3`
- Requires `hn/typo3-mcp-server` (`^0.5 || ^0.6`), `logiscape/mcp-sdk-php` and `xima/xima-typo3-content-planner` (`^2.4`)
- Namespace: `KonradMichalik\Typo3McpServerContentPlanner`

## Structure

- `Classes/MCP/Tool/` one class per MCP tool, all extending `AbstractPlannerTool`
  - `GetContentPlannerInfoTool`, `ListContentPlannerStatusesTool`, `SetContentPlannerStatusTool`, `AddContentPlannerCommentTool`, `UpdateContentPlannerCommentTool`
- `Configuration/Services.yaml` service registration
- `Tests/Functional/` functional tests (`AbstractFunctionalTestCase`, `MCP/`, `Fixtures/`)
- `Tests/CGL/` separate Composer project with linters, PHPStan and Rector
- `Tests/Acceptance/` acceptance fixtures

## Development commands

Development runs in DDEV.

- `ddev start` and `ddev composer install` set up the project
- `ddev install 13` (or `14`, `all`) sets up a TYPO3 test instance, `ddev launch 13 /typo3` opens the backend
- `ddev composer cgl lint` runs all linters, `ddev composer cgl fix` fixes them
- `ddev composer cgl sca` runs static analysis
- `ddev composer cgl migration` runs Rector
- `ddev mcp-smoke 13` calls every tool through the real `typo3 mcp:server` command via the MCP Inspector (run after installing an instance)
- `ddev mcp-inspect 13` opens the MCP Inspector, `--cli --method tools/list` runs it headless

## Testing

- Only functional tests exist: `phpunit.functional.xml`, tests in `Tests/Functional/`
- `composer test` (alias of `composer test:functional`) runs without coverage
- `composer test:coverage` runs with `XDEBUG_MODE=coverage`
- The functional tests call the tool classes directly and do not exercise the MCP stdio protocol, use `ddev mcp-smoke` for that
- Add or update a functional test for every behavior change
- CI (reusable workflow `tests.yml`) runs PHP 8.2 to 8.5 against TYPO3 13.4 and 14.3

## Code style and static analysis

- PHP CS Fixer, config in `Tests/CGL/.php-cs-fixer.php`
- PHPStan level 6, config in `Tests/CGL/phpstan.neon`
- Rector, config in `Tests/CGL/rector.php`
- `composer normalize`, EditorConfig lint and `composer-dependency-analyser` are part of the CGL project (`analyze`, `lint`)
- The `cgl` GitHub workflow runs the checks on every push

## Git workflow

- One logical change per pull request
- Commit format: `<type>: <description>` with type `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `perf` or `ci`
- One commit per logical change, single line message
- No co-author trailers
- Never skip hooks (`--no-verify`)
