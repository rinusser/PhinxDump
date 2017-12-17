<?php
declare(strict_types=1);
/**
 * Requires PHP version 7.0+.
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 * @link https://github.com/rinusser/PhinxDump
 */

namespace RN\PhinxDump\CodeGenerator\Column;

use RN\PhinxDump\Model;
use RN\PhinxDump\UnsupportedSchemaException;
use RN\PhinxDump\Logger;

/**
 * FLOAT/DOUBLE column code generator
 */
class FloatCodeGenerator extends AbstractColumnCodeGenerator
{
  public static $allowDoubleFallback=false;


  /**
   * Gets called to determine what model class is being handled
   *
   * @return string the model class with full namespace
   */
  public function getHandledModelClass(): string
  {
    return Model\FloatColumn::class;
  }

  /**
   * Gets called to determine what Phinx column type should be generated
   *
   * @param AbstractColumn $column the column being processed
   * @return string the Phinx column type, e.g. 'integer'
   */
  public function getPhinxType(Model\AbstractColumn $column): string
  {
    switch($column->precision)
    {
      case Model\FloatColumn::PRECISION_SINGLE:
        return 'float';
      case Model\FloatColumn::PRECISION_DOUBLE:
        if(!self::$allowDoubleFallback)
          throw new UnsupportedSchemaException('double precision floats currently (as of 0.8.1) not implemented in Phinx');
        $column->codeComment='XXX was MySQL type DOUBLE, falled back to single precision';
        Logger::getInstance()->warn("column '$column->name' was type DOUBLE but Phinx doesn't support that, used FLOAT instead");
        return 'float';
      default:
        throw new \LogicException('unhandled precision "'.$column->precision.'"');
    }
  }

  /**
   * Gets called to update Phinx column options
   *
   * @param AbstractColumn $column  the column being processed
   * @param array          $options the options to update, as reference!
   * @return void
   */
  public function updatePhinxOptions(Model\AbstractColumn $column, array &$options): void
  {
  }
}
