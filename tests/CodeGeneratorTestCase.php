<?php
declare(strict_types=1);
/**
 * Requires PHP version 7.1+
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 * @link https://github.com/rinusser/PhinxDump
 */

namespace RN\PhinxDump\Tests;

use PHPUnit\Framework\TestCase;
use RN\PhinxDump\MigrationCodeGenerator;
use RN\PhinxDump\CodeGenerator\Column\FloatCodeGenerator;
use RN\PhinxDump\Logger;

/**
 * Base test for testing generated migration code
 */
abstract class CodeGeneratorTestCase extends TestCase
{
  /**
   * Load Phinx stubs for testing, since Phinx isn't installed in the phpunit environment
   */
  public static function setUpBeforeClass()
  {
    require_once(__DIR__.'/stubs/MysqlAdapterStub.php');
    require_once(__DIR__.'/stubs/PhinxMigrationStub.php');
    Logger::$outputEnabled=false;
  }

  /**
   * Reset code generator defaults
   */
  public function setUp()
  {
    FloatCodeGenerator::$allowDoubleFallback=false;
    MigrationCodeGenerator::$allowEmptyMigration=false;
  }

  /**
   * Assertion for checking single lines in multiline text
   *
   * @param int    $offset   the line number (starting at 0)
   * @param string $expected the expected string
   * @param string $actual   the multiline string
   * @return void
   */
  protected function _assertTrimmedRowAtOffsetIs(int $offset, string $expected, string $actual)
  {
    $rows=explode("\n",$actual);
    $row=isset($rows[$offset])?trim($rows[$offset]):NULL;
    $this->assertEquals($expected,$row);
  }

  /**
   * Helper function for silently eval()'ing code.
   * eval() is OK to use here since we're testing a code generator anyway.
   *
   * @param mixed $code the PHP code to eval
   * @return mixed eval()'d results
   */
  protected function _silentEval($code)
  {
    //@codingStandardsIgnoreLine //suppress eval() warning
    return eval($code);
  }
}
