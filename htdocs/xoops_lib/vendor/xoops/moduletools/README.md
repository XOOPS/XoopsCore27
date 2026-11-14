# XOOPS ModuleTools

ModuleTools is the XOOPS Core-bundled successor to the mTools helper-host module. It
provides reusable services under `Xoops\ModuleTools\` without requiring an
installed or active module.

The package registers lazy aliases for the stable `XoopsModules\Mtools\*` API,
allowing existing modules to run on XOOPS 2.8 without an mTools database row.
The separately distributed mTools module remains the provider for XOOPS 2.7.x.

Experimental `mTools\Lab\*`, module administration pages, theme assets, and the
legacy `mtools_setup` table are intentionally not part of this library.

## Documentation

- [Getting started](docs/getting-started.md) — using the library from a module
- [Migrating a module](docs/migrating-a-module.md) — replacing copied `Common` classes / the mTools module
- [Compatibility policy](docs/compatibility-policy.md) — what stays, for how long

## Requirements

- PHP 8.4+, XOOPS 2.8.0+ (`xoops/xmf` ^1.3, `xoops/helpers` ^1.0). Smarty is Core's; the
  library ships no templates and only drives `XoopsTpl` from PHP.

## Development

```bash
composer install
composer qa      # phpcs → phpstan (level 6, generated XOOPS stubs) → rector dry-run → package checks → phpunit
composer test    # package checks + phpunit only
```

The consumer-module tests (`*ConsumerTest`) and the XMF 1.5 pagination parity test skip
automatically unless the package sits inside a XOOPS tree (`xoops_lib/vendor/xoops/moduletools`).
`stubs/xoops-core.stub` is generated from a real XOOPS 2.8 core so PHPStan can analyse the
library standalone; regenerate it when the core API the library touches changes.
