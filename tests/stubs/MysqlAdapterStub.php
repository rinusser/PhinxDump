<?php
declare(strict_types=1);
/**
 * Requires PHP version 7.0+
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 */

namespace RN\PhinxDump\Tests\Stubs;

/**
 * Stub for Phinx's MysqlAdapter class to break that dependency.
 */
abstract class MysqlAdapter
{
  public const INT_TINY='atinyint';
  public const INT_SMALL='bsmallint';
  public const INT_MEDIUM='mmedint';
  public const INT_REGULAR='cint';
  public const INT_BIG='dbigint';

  public const TEXT_TINY='etinytext';
  public const TEXT_REGULAR='fregulartext';
  public const TEXT_MEDIUM='gmediumtext';
  public const TEXT_LONG='hlongtext';

  public const BLOB_TINY='itinyblob';
  public const BLOB_REGULAR='jregularblob';
  public const BLOB_MEDIUM='kmediumblob';
  public const BLOB_LONG='llongblob';
}
