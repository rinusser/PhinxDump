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
 * *TEXT/*BLOB column code generator
 */
class LOBCodeGenerator extends AbstractColumnCodeGenerator
{
  /**
   * Gets called to determine what model class is being handled
   *
   * @return string the model class with full namespace
   */
  public function getHandledModelClass(): string
  {
    return Model\LOBColumn::class;
  }

  /**
   * Gets called to determine what Phinx column type should be generated
   *
   * @param AbstractColumn $column the column being processed
   * @return string the Phinx column type, e.g. 'integer'
   */
  public function getPhinxType(Model\AbstractColumn $column): string
  {
    return $column->type==Model\LOBColumn::TYPE_TEXT?'text':'blob';
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
    $options['limit']=$this->_getMySQLLOBLimit($column);
  }


  protected function _getMySQLLOBLimit(Model\LOBColumn $column): string
  {
    $sizes=[Model\LOBColumn::SIZE_TINY=>'TINY',
            Model\LOBColumn::SIZE_REGULAR=>'REGULAR',
            Model\LOBColumn::SIZE_MEDIUM=>'MEDIUM',
            Model\LOBColumn::SIZE_LONG=>'LONG'];
    return 'MysqlAdapter::'.($column->type==Model\LOBColumn::TYPE_TEXT?'TEXT':'BLOB').'_'.self::_findValueByKey($column->size,$sizes);
  }
}
