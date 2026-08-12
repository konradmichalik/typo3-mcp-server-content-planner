# TYPO3 MCP Server Content Planner Bridge

[![Tests](https://img.shields.io/github/actions/workflow/status/konradmichalik/typo3-mcp-server-content-planner/tests.yml?label=tests&logo=github)](https://github.com/konradmichalik/typo3-mcp-server-content-planner/actions/workflows/tests.yml)
[![CGL](https://img.shields.io/github/actions/workflow/status/konradmichalik/typo3-mcp-server-content-planner/cgl.yml?label=cgl&logo=github)](https://github.com/konradmichalik/typo3-mcp-server-content-planner/actions/workflows/cgl.yml)
![TYPO3](https://img.shields.io/badge/TYPO3-13.4%20%7C%2014.3-orange.svg)
[![License](https://poser.pugx.org/konradmichalik/typo3-mcp-server-content-planner/license)](LICENSE.md)

Bridges [typo3-mcp-server](https://github.com/hauptsacheNet/typo3-mcp-server) and
[xima-typo3-content-planner](https://github.com/xima-media/xima-typo3-content-planner):
exposes Content Planner's status, assignee and comment workflow as MCP tools, so an AI
assistant connected to your TYPO3 backend can read and leave editorial status updates and
comments on records — immediately visible in the backend, not staged behind a workspace
publish.

## Installation

```bash
composer require konradmichalik/typo3-mcp-server-content-planner
```

Requires `hn/typo3-mcp-server` and `xima/xima-typo3-content-planner` to be installed and
set up. Currently tracks `xima/xima-typo3-content-planner: dev-main` pending a tagged
release containing [#339](https://github.com/xima-media/xima-typo3-content-planner/pull/339).

## Tools

| Tool | Type | Description |
|---|---|---|
| `ListContentPlannerStatuses` | read | List all available statuses. |
| `GetContentPlannerInfo` | read | Get status, assignee and comments (with threads) of a record. |
| `SetContentPlannerStatus` | write | Set a record's status and optionally its assignee. |
| `AddContentPlannerComment` | write | Leave a comment (optionally with to-dos), or a threaded reply via `parentCommentUid`, on a record. |

## Development

```bash
composer install
composer test
composer cgl lint
```

## License

GPL-2.0-or-later
