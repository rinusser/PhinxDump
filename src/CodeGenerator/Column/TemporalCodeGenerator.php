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

/**
 * DATE/TIME/DATETIME/TIMESTAMP column code generator
 */
class TemporalCodeGenerator extends AbstractColumnCodeGenerator
{
  /**
   * Gets called to determine what model class is being handled
   *
   * @return string the model class with full namespace
   */
  public function getHandledModelClass(): string
  {
    return Model\TemporalColumn::class;
  }

  /**
   * Gets called to determine what Phinx column type should be generated
   *
   * @param AbstractColumn $column the column being processed
   * @return string the Phinx column type, e.g. 'integer'
   */
  public function getPhinxType(Model\AbstractColumn $column): string
  {
    switch($column->type)
    {
      case Model\TemporalColumn::TYPE_DATE:
        return 'date';
      case Model\TemporalColumn::TYPE_TIME:
        return 'time';
      case Model\TemporalColumn::TYPE_DATETIME:
        return 'datetime';
      case Model\TemporalColumn::TYPE_TIMESTAMP:
        return 'timestamp';
      default:
        throw new \LogicException('no known Phinx type for temporal column type "'.$column->type.'"');
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
    if($column->type===Model\TemporalColumn::TYPE_TIMESTAMP)
    {
      $options['update']=$column->onUpdateCurrentTimestamp?'"CURRENT_TIMESTAMP"':'NULL';
      if($column->default===NULL)
        $options['default']='NULL';
    }
  }
}
