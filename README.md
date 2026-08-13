<div align="center">

![Extension icon](Resources/Public/Icons/Extension.png)

# TYPO3 extension `typo3_mcp_server_content_planner`

![TYPO3](https://img.shields.io/badge/TYPO3-13.4%20%7C%2014.3-orange.svg)
[![CGL](https://img.shields.io/github/actions/workflow/status/konradmichalik/typo3-mcp-server-content-planner/cgl.yml?label=cgl&logo=github)](https://github.com/konradmichalik/typo3-mcp-server-content-planner/actions/workflows/cgl.yml)
[![Tests](https://img.shields.io/github/actions/workflow/status/konradmichalik/typo3-mcp-server-content-planner/tests.yml?label=tests&logo=github)](https://github.com/konradmichalik/typo3-mcp-server-content-planner/actions/workflows/tests.yml)
[![License](https://poser.pugx.org/konradmichalik/typo3-mcp-server-content-planner/license)](LICENSE.md)

</div>

This TYPO3 extension bridges [`hn/typo3-mcp-server`](https://github.com/hauptsacheNet/typo3-mcp-server)
and [`xima/xima-typo3-content-planner`](https://github.com/xima-media/xima-typo3-content-planner):
it exposes Content Planner's status, assignee and comment workflow as MCP tools, so an AI
assistant connected to your TYPO3 backend can read and leave editorial status updates and
comments on records.

> [!NOTE]
> Ideal for AI-assisted editorial workflows, e.g. having an assistant flag pages that need
> review, or reply to open to-dos left by an editor.

It ships no own database schema, backend module or configuration: just four MCP tools that
register automatically once both host extensions are installed.

## ✨ Features

**List statuses**: all Content Planner statuses configured on the installation
* Exposed via `ListContentPlannerStatuses`

**Read status & comments**: status, assignee and the full comment thread of any record
* Exposed via `GetContentPlannerInfo`

**Set status & assignee**: update a record's status and, optionally, reassign it
* Exposed via `SetContentPlannerStatus`
* Defaults the assignee to the currently authenticated backend user

**Leave comments & to-dos**: add a comment, an unchecked to-do checklist, or a threaded reply
* Exposed via `AddContentPlannerComment`
* Reply to an existing comment via `parentCommentUid`

**Always live**: every write lands on the live workspace immediately, never staged behind a publish

**Permission-aware**: every tool respects the acting backend user's Content Planner and record permissions

## 🔥 Installation

### Requirements

* TYPO3 13.4 LTS & 14.3+
* PHP 8.2 – 8.5
* [`hn/typo3-mcp-server`](https://github.com/hauptsacheNet/typo3-mcp-server) ^0.5
* [`xima/xima-typo3-content-planner`](https://github.com/xima-media/xima-typo3-content-planner) ^2.4

### Composer

```bash
composer require konradmichalik/typo3-mcp-server-content-planner
```

No further setup is required: the four tools register automatically as soon as both host
extensions are installed and configured.

## 💡 Usage

| Tool | Type | Description |
|---|---|---|
| `ListContentPlannerStatuses` | read | List all available statuses. |
| `GetContentPlannerInfo` | read | Get status, assignee and comments (with threads) of a record. |
| `SetContentPlannerStatus` | write | Set a record's status and optionally reassign it (defaults to the current backend user). |
| `AddContentPlannerComment` | write | Leave a comment (optionally with to-dos), or a threaded reply via `parentCommentUid`, on a record. |

> [!NOTE]
> All write tools apply immediately to the live workspace; there is no draft/staging step
> to publish afterwards.

### Example prompts

> Show me all pages with the status "Needs Review" and summarize any open comments.

> Set the status of the "Pricing" page to "Done", assign it to me, and reply to Jane's
> comment to let her know it's ready.

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## 💎 Credits

Thanks to [Marco Pfeiffer](https://github.com/hauptsacheNet) for building and open-sourcing
`typo3-mcp-server`.

## 📜 License

[GPL-2.0-or-later](LICENSE.md)
