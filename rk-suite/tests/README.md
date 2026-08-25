# RK Suite — unit tests

Fast, dependency-free unit tests for the security-critical pure logic. They stub
the few WordPress functions the units touch, so no WP install or DB is needed.

## Run

```bash
composer install
composer test        # or: vendor/bin/phpunit
```

## Coverage

| Test | Guards against |
|---|---|
| `CsvSafeTest` | CSV / spreadsheet formula injection in Forms export |
| `HostGuardTest` | SSRF via media sideload to private/loopback/link-local hosts |
| `SchemaEscapeTest` | `</script>` breakout in JSON-LD schema output |
| `ContactConfigTest` | Contact Form mail-relay abuse (recipient not client-controlled) |

These cover the highest-risk paths. They are unit tests, not integration tests —
runtime behaviour (real Elementor render, real DB import) still wants the manual
staging checklist in `RK-Suite-QA-and-Test-Checklist.md`.
