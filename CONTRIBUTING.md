# General

This application is in essence a wrapper around Phinx. Occasionally PhinxDump lacks support for a particular feature
because Phinx in turn doesn't support the feature yet.

Instead of adding a feature here (e.g. hacking together a way to create SQL VIEWs) please consider contributing the
feature to Phinx directly.


# Submitting GitHub Issues

### Bugs

Any PhinxDump errors containing "unhandled" or "no known" not listed in README.md under "Limitations" are probably bugs
to be fixed.

Please check the issues list for open entries similar to your bug - if there aren't any please supply:
* the error(s) you're getting
* the relevant parts of the dumped database schema
* the command line (excluding username/password) that resulted in the error

### New Features

I try to avoid implementing features missing from Phinx in this application, e.g. unsupported column types.

Beyond that, please describe:
* the proposed new feature
* what you'd use it for, or why you think it's missing


# Working on Code

### Scope

The upcoming/planned work is managed in the [Issues list](https://github.com/rinusser/PhinxDump/issues). Each
implemented/fixed GitHub issue corresponds to one commit in `master`, with the issue number (prefixed with `PD-`) at the
start of the commit message.

### Code Style

The source code is validated with PHP\_CodeSniffer using [my ruleset](https://github.com/rinusser/CodeSnifferUtils).

### Tests

Most of the major features should be covered by tests. The tests are lightweight and finish quickly, so feel free to add
tests for interesting fringe cases.

### Validation

Each commit into the `master` branch conforms to the phpcs standard and passes all tests. Personally I use this command
line to validate the current version (`phpcs` is an alias, see CodeSnifferUtils's
[README.md](https://github.com/rinusser/CodeSnifferUtils/blob/master/README.md)):

    phpunit; phpcs

### Documentation

Limitations should be documented in README.md, unless there's a GitHub issue describing a new feature to lift the
limitation.

Command line arguments should be explained succinctly in the help screen (e.g. `phinxdump -h`) and exhaustivly in
README.md.

### Licensing

Please note that the code is currently licensed under GPLv3 - any contributions are expected to share this license.

I may change the license to a less restrictive open source license at a later date.
