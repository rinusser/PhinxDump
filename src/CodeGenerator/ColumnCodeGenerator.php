<?php
declare(strict_types=1);
/**
 * Requires PHP version 7.0+.
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 * @link https://github.com/rinusser/PhinxDump
 */

namespace RN\PhinxDump\CodeGenerator;

use RN\PhinxDump\Model;
use RN\PhinxDump\UnsupportedSchemaException;

/**
 * This class takes various models and turns them into parts of migration classes.
 */
abstract class ColumnCodeGenerator extends AbstractCodeGenerator
{
  protected static $_codeGeneratorsByClass=[];


  protected static function _assembleColumnCodeGenerators(): void
  {
    if(self::$_codeGeneratorsByClass)
      return;

    foreach(glob(__DIR__.'/Column/*.php') as $file)
    {
      $class=__NAMESPACE__.'\\Column\\'.substr(basename($file),0,-4);
      if((new \ReflectionClass($class))->isAbstract())
        continue;
      $instance=new $class();
      $handled_model=$instance->getHandledModelClass();
      if(isset(self::$_codeGeneratorsByClass[$handled_model]))
        throw new \LogicException('there\'s already a handler for model '.$handled_model);
      self::$_codeGeneratorsByClass[$handled_model]=$instance;
    }
  }

  protected static function _generator(Model\AbstractColumn $column): Column\AbstractColumnCodeGenerator
  {
    self::_assembleColumnCodeGenerators();
    foreach(self::$_codeGeneratorsByClass as $class=>$generator)
      if($column instanceof $class)
        return $generator;
    throw new UnsupportedSchemaException('column type '.get_class($column).' unsupported');
  }

  protected static function _getPhinxColumnOptions(Model\AbstractColumn $column): array
  {
    $rv=[];
    if($column->nullable)
      $rv['null']='true';
    if($column->comment!=NULL)
      $rv['comment']=trim(var_export($column->comment,true));
    if($column->default!==NULL)
      $rv['default']=trim(var_export($column->default,true));

    self::_generator($column)->updatePhinxOptions($column,$rv);

    return $rv;
  }

  /**
   * Turns a column model into a Phinx ->addColumn() function call
   *
   * @param AbstractColumn $column the column to generate code for
   * @return string the generated function call code
   */
  public static function generateAddColumnCode(Model\AbstractColumn $column): string
  {
    $name=$column->name;
    $type=self::_generator($column)->getPhinxType($column);
    $options=self::_getPhinxColumnOptions($column);
    $code="->addColumn('".$name."','".$type."'".($options?','.self::_generateArray($options,true,false):'').")";
    if($column->codeComment!==NULL)
      $code.=' //'.$column->codeComment;
    return $code;
  }
}
