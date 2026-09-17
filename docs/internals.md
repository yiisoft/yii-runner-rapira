# Internals

## Testing

The package is tested with [Testo](https://php-testo.github.io/). To run all suites:

```shell
./vendor/bin/testo
```

Tests are split into suites (see `testo.php`): `Unit` isolates a single class with doubles, `Feature`
runs the runner against a fake Rapira runtime, `Acceptance` starts the real `rapira` binary in every
mode and sends HTTP requests to it. The binary is downloaded into `runtime/bin` on demand. Run one
suite with:

```shell
./vendor/bin/testo --suite=Feature
```

## Mutation testing

The package tests are checked with [Infection](https://infection.github.io/) mutation framework. To run it:

```shell
./vendor/bin/infection
```

The `Acceptance` suite is excluded from the run through `testFrameworkOptions` in `infection.json.dist`:
it boots a real `rapira` server, which every mutant would pay for again.

## Static analysis

The code is statically analyzed with [Psalm](https://psalm.dev/). To run static analysis:

```shell
./vendor/bin/psalm
```

## Code style

Use [Rector](https://github.com/rectorphp/rector) to make codebase follow some specific rules or
use either newest or any specific version of PHP:

```shell
./vendor/bin/rector
```

## Dependencies

Use [Composer Dependency Analyser](https://github.com/shipmonk-rnd/composer-dependency-analyser) to detect unknown,
shadow, and unused [Composer](https://getcomposer.org) dependencies:

```shell
./vendor/bin/composer-dependency-analyser
```
