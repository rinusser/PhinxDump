<?php
declare(strict_types=1);
/**
 * Requires PHP version 7.0+
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 */

namespace RN\PhinxDump;

/**
 * Typed exception to abort entire process but still indicate we don't need too much debug info about this error
 */
class UnsupportedSchemaException extends \Exception
{
}
