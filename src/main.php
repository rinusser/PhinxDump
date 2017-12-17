<?php
declare(strict_types=1);
/**
 * Main entry point for application, called from shell.
 *
 * Requires PHP version 7.0+.
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 * @link https://github.com/rinusser/PhinxDump
 */

namespace RN\PhinxDump;

require('init.php');
(new CLI())->handle();
