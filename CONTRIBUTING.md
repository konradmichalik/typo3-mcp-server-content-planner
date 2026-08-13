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

## Pull requests

- One logical change per pull request.
- Add or update a functional test for every behavior change.
- Run `composer cgl fix` before committing.
