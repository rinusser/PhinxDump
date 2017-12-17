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
 * *INT column code generator
 */
class IntegerCodeGenerator extends AbstractColumnCodeGenerator
{
  /**
   * Gets called to determine what model class is being handled
   *
   * @return string the model class with full namespace
   */
  public function getHandledModelClass(): string
  {
    return Model\IntegerColumn::class;
  }

  /**
   * Gets called to determine what Phinx column type should be generated
   *
   * @param AbstractColumn $column the column being processed
   * @return string the Phinx column type, e.g. 'integer'
   */
  public function getPhinxType(Model\AbstractColumn $column): string
  {
    return 'integer';
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
    $options['limit']=$this->_getMySQLIntegerLimit($column);
    if($column->unsigned)
      $options['signed']='false';
    if($column->autoIncrement)
      $options['identity']='true';
  }

  protected function _getMySQLIntegerLimit(Model\IntegerColumn $column): string
  {
    $sizes=[Model\IntegerColumn::SIZE_TINY=>'TINY',
            Model\IntegerColumn::SIZE_SMALL=>'SMALL',
            Model\IntegerColumn::SIZE_MEDIUM=>'MEDIUM',
            Model\IntegerColumn::SIZE_REGULAR=>'REGULAR',
            Model\IntegerColumn::SIZE_BIG=>'BIG'];
    return 'MysqlAdapter::INT_'.self::_findValueByKey($column->size,$sizes);
  }
}
