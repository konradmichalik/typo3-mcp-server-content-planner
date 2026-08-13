# Contributing

Thank you for considering a contribution to this extension.

## Requirements

- [DDEV](https://ddev.readthedocs.io/en/stable/)

## Development setup

```bash
ddev start
ddev composer install
ddev composer test
ddev composer cgl lint
```

## Manual backend testing

A live TYPO3 backend with both host extensions installed is useful for trying
the MCP tools by hand, beyond what the functional tests cover:

```bash
ddev install 13   # or 14, or "all"
ddev launch 13 /typo3
```

## Testing the MCP tools

The functional tests call the tool classes directly in PHP; they never
exercise the actual MCP stdio protocol (tool discovery, schema
serialization). `ddev mcp-smoke` calls every tool through the real
`typo3 mcp:server` command via the [MCP Inspector](https://github.com/modelcontextprotocol/inspector)
and prints a pass/fail summary — run it after installing a backend instance:

```bash
ddev mcp-smoke 13   # or 14
```

For ad-hoc, interactive inspection (browser UI or headless CLI calls):

```bash
ddev mcp-inspect 13                                   # interactive UI
ddev mcp-inspect 13 --cli --method tools/list         # headless
```

## Pull requests

- One logical change per pull request.
- Add or update a functional test for every behavior change.
- Run `composer cgl fix` before committing.
