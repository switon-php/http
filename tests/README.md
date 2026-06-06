# Switon HTTP Package Tests

Run from the package root:

```bash
vendor/bin/phpunit --configuration tests/phpunit.xml.dist
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --testsuite=unit
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --testsuite=integration
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --filter testMethodName
```

`tests/Unit/` holds isolated package tests. `tests/Integration/` holds app-style integration coverage.

Install dev dependencies first so `vendor/bin/phpunit` is available.
