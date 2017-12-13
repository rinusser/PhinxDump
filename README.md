# Synopsis

Creates Phinx database migration classes from live MySQL databases. Can be used either with a local PHP installation or
as a CLI-based Docker application.


# Requirements

### Standalone

* PHP 7.1+ with pdo\_mysql installed

### Docker

Required only if application should run as a Docker container:

* Docker (tested with 2.0.0)

### Tests

Requirements for executing tests:

* PHPUnit (tested with PHPUnit 6.0, earlier versions down to 5.4 are probably fine too)


# Installation

### Standalone

If all the requirements are installed, there's nothing to do but to download the code.

### Docker

    docker build -t <imagename> .


# Usage

Don't expect this application to handle all fringe cases correctly - it's just supposed to give you a head start.
You'll still have to check and potentially correct the generated files.

### Standalone

    php -f src/main.php <args...>

### Docker

Change the db/migrations path to whatever suits your needs:

    docker run --rm -u "$UID" -it -v "$PWD/db/migrations:/data" <imagename> -- <args...>

When accessing databases in docker containers by container names, make sure to run the application in the appropriate
docker network (`--net <name>`).

It's recommended to create a shell alias to keep command lines short (assuming image name "mysqlphinxdump"):

    alias mysqlphinxdump='docker run --rm -u "$UID" -it -v "$PWD/db/migrations:/data" \
      mysqlphinxdump --'

which can then be invoked easily:

     mysqlphinxdump <args...>

### Arguments

Regardless of whether you invoke this application directly with php or as a Docker container, it supports these command
line arguments (replace "phinxdump" with `php -f src/main.php` or your docker command line or alias):

    phinxdump [<options>] <database>

    Options:
     -h <hostname>
     -u <username>
     --allow-double-fallback <yes|no>
     --allow-empty-migration <yes|no>

The `-h <hostname>` argument can be used to access a particular database server (just like `mysql -h <hostname>`).

The `-u <username>` argument can be used to change the username to connect as (just like `mysql -u <username>`). By
default there will be an attempt to use the current user's username, or "root" if it can't be determined.

The `--allow-double-fallback <yes|no>` argument specifies whether it's OK to use `float` for any found `double` columns.
Phinx currently doesn't support the `double` datatype, in some cases the precision loss might be OK. By default this is
disabled, i.e. any found `double` columns will log an error and abort the application.

The `--allow-empty-migration <yes|no>` argument specifies whether empty schemas should still create a migration class.
By default this is disabled, i.e. dumping empty schemas will log an error and will NOT write a migration file.

The `database` argument is mandatory and contains the MySQL database name to dump.


# Limitations

Currently does not support (because Phinx doesn't either):
* `BIT` data type: should currently fail if encountered
* `VIEW`s: there will be a comment line for each skipped view
* `DOUBLE` data type: single precision (float) type works fine, double precision isn't supported (there is the --allow-double-fallback parameter though, it converts doubles to floats)
* arbitrary compound partial indices, e.g. `CREATE INDEX idx1 (col1(5),col2(6))`: will fail if encountered (compound indices with just last column partial are OK, as are single partial column indices)
* display widths, e.g. `int(4)`: they're useless anyway outside SQL developer tools

Currently does not support (and won't until there's actual need to):
* anything other than MySQL sources: this app uses the ANSI "information\_schema" to fetch the database structure (with a few MySQL-specifics like `auto_increment` and `unsigned`), so other database backends supporting information\_schema could probably be added relatively easily
* sources other than live databases: the code could be extended to parse database dumps (.sql files) directly, but those are vendor-specific and much more difficult to parse reliably. It's straightforward to just import a dump file into a database server and dump that database directly.


# Tests

Contains straightforward PHPUnit tests. Start them in the project's root directory with:

    phpunit

The code styling conforms to the PHP\_CodeSniffer standard implemented in https://github.com/rinusser/CodeSnifferUtils.


# Legal

## Copyright

Copyright (C) 2017 Richard Nusser

## License

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License, LICENSE.md,
along with this program. If not, see <http://www.gnu.org/licenses/>.
