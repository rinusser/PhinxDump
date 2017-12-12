<?php
declare(strict_types=1);
/**
 * Requires PHP version 7.0+.
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 */

namespace RN\PhinxDump;

use RN\PhinxDump\Model;

/**
 * This class takes various models and turns them into parts of migration classes.
 */
abstract class MigrationCodeGenerator
{
  public static $allowDoubleFallback=false;
  public static $allowEmptyMigration=false;

  protected static $_columnTypeMappers=[];


  protected static function _assembleColumnTypeMappers()
  {
    self::$_columnTypeMappers=[];
    self::$_columnTypeMappers[]=[Model\IntegerColumn::class,'integer'];

    self::$_columnTypeMappers[]=[Model\FloatColumn::class,function($column)
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
    }];

    self::$_columnTypeMappers[]=[Model\DecimalColumn::class,'decimal'];

    self::$_columnTypeMappers[]=[Model\CharColumn::class,function($column)
    {
      return $column->variable?'string':'char';
    }];

    self::$_columnTypeMappers[]=[Model\LOBColumn::class,function($column)
    {
      return $column->type==Model\LOBColumn::TYPE_TEXT?'text':'blob';
    }];

    self::$_columnTypeMappers[]=[Model\ListColumn::class,function($column)
    {
      return $column->multiple?'set':'enum';
    }];

    self::$_columnTypeMappers[]=[Model\TemporalColumn::class,function($column)
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
    }];
  }

  protected static function _getPhinxColumnType(Model\AbstractColumn $column): string
  {
    foreach(self::$_columnTypeMappers as list($class,$mapper))
      if($column instanceof $class)
        return is_callable($mapper)?$mapper($column):$mapper;

    throw new UnsupportedSchemaException('no known Phinx type for column type "'.get_class($column).'"');
  }

  protected static function _findValueByKey(string $key, array $values): string
  {
    foreach($values as $tk=>$tv)
      if($tk==$key)
        return $tv;
    throw new \LogicException('no value found for key "'.$key.'"');
  }

  protected static function _getMySQLLOBLimit(Model\LOBColumn $column): string
  {
    $sizes=[Model\LOBColumn::SIZE_TINY=>'TINY',
            Model\LOBColumn::SIZE_REGULAR=>'REGULAR',
            Model\LOBColumn::SIZE_MEDIUM=>'MEDIUM',
            Model\LOBColumn::SIZE_LONG=>'LONG'];
    return 'MysqlAdapter::'.($column->type==Model\LOBColumn::TYPE_TEXT?'TEXT':'BLOB').'_'.self::_findValueByKey($column->size,$sizes);
  }

  protected static function _getMySQLIntegerLimit(Model\IntegerColumn $column): string
  {
    $sizes=[Model\IntegerColumn::SIZE_TINY=>'TINY',
            Model\IntegerColumn::SIZE_SMALL=>'SMALL',
            Model\IntegerColumn::SIZE_MEDIUM=>'MEDIUM',
            Model\IntegerColumn::SIZE_REGULAR=>'REGULAR',
            Model\IntegerColumn::SIZE_BIG=>'BIG'];
    return 'MysqlAdapter::INT_'.self::_findValueByKey($column->size,$sizes);
  }

  protected static function _getPhinxColumnOptions(Model\AbstractColumn $column): array
  {
    $rv=[];
    if($column->nullable)
      $rv['null']='true';
    if($column->comment!=NULL)
      $rv['comment']=trim(var_export($column->comment,true));

    if($column instanceof Model\CharColumn)
      $rv['limit']=$column->length;
    elseif($column instanceof Model\LOBColumn)
      $rv['limit']=self::_getMySQLLOBLimit($column);
    elseif($column instanceof Model\IntegerColumn)
    {
      $rv['limit']=self::_getMySQLIntegerLimit($column);
      if($column->unsigned)
        $rv['signed']='false';
      if($column->autoIncrement)
        $rv['identity']='true';
    }
    elseif($column instanceof Model\ListColumn)
      $rv['values']=self::_generateArray($column->values,false);
    elseif($column instanceof Model\DecimalColumn)
    {
      $rv['precision']=$column->precision;
      $rv['scale']=$column->scale;
      if($column->unsigned)
        $rv['signed']='false';
    }
    elseif($column instanceof Model\TemporalColumn && $column->type===Model\TemporalColumn::TYPE_TIMESTAMP)
      $rv['update']=$column->onUpdateCurrentTimestamp?'"CURRENT_TIMESTAMP"':'NULL';

    if($column->default!==NULL || $column instanceof Model\TemporalColumn && $column->type===Model\TemporalColumn::TYPE_TIMESTAMP)
      $rv['default']=trim(var_export($column->default,true));

    return $rv;
  }

  protected static function _generateArray(array $array, bool $with_keys=false, bool $escape_values=true): string
  {
    $rvs=[];
    foreach($array as $tk=>$tv)
    {
      $entry=$with_keys?trim(var_export($tk,true)).'=>':'';
      $entry.=$escape_values?trim(var_export($tv,true)):$tv;
      $rvs[]=$entry;
    }
    return '['.implode(',',$rvs).']';
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
    $type=self::_getPhinxColumnType($column);
    $options=self::_getPhinxColumnOptions($column);
    $code="->addColumn('".$name."','".$type."'".($options?','.self::_generateArray($options,true,false):'').")";
    if($column->codeComment!==NULL)
      $code.=' //'.$column->codeComment;
    return $code;
  }

  /**
   * Turns an index model into a Phinx ->addIndex() function call
   *
   * @param Index $index the index to generate code for
   * @return string the generated function call code
   */
  public static function generateAddIndexCode(Model\Index $index): string
  {
    $limits=array_filter($index->columnSubparts);
    $count=count($limits);
    $limit_string='';
    if($count>1)
      throw new UnsupportedSchemaException("index '$index->name': multiple column subparts currently not supported as of Phinx 0.8");
    elseif($count==1)
    {
      if(array_keys($limits)[0]!=count($index->columnSubparts)-1)
        throw new UnsupportedSchemaException("index '$index->name': column subpart only supported on last column as of Phinx 0.8");
      $limit_string=",'limit'=>".array_pop($limits);
    }
    return "->addIndex(".self::_generateArray($index->columns).",['unique'=>".($index->unique?'true':'false').$limit_string.",'name'=>'".$index->name."'])";
  }

  /**
   * Turns a table model into a Phinx $this->table() ... ->create() call
   *
   * @param Table  $table  the table to generate code for
   * @param string $indent (optional) the indenting to use for chained function calls
   * @return string the generated code to create the entire table (includes all columns and indices)
   */
  public static function generateTableCode(Model\Table $table, string $indent='    '): string
  {
    self::_assembleColumnTypeMappers();
    $options=['id'=>'false'];
    if($table->primaryKey)
      $options['primary_key']=self::_generateArray($table->primaryKey->columns);
    if($table->comment)
      $options['comment']=trim(var_export($table->comment,true));
    $rvs=['$this->table(\''.$table->name."',".self::_generateArray($options,true,false).')'];
    foreach($table->columns as $column)
      $rvs[]=self::generateAddColumnCode($column);
    foreach($table->indices as $index)
      $rvs[]=self::generateAddIndexCode($index);
    $rvs[]='->create();';
    return implode("\n".$indent."     ",$rvs);
  }

  /**
   * Generates a migration file from blocks of migration parts (e.g. table creations)
   *
   * @param string $classname                      the class's name
   * @param array  $code_blocks                    the list of code blocks (e.g. generated by generateTableCode()) to include in the migration
   * @param array  $additional_class_comment_lines (optional) additional lines of class comments
   * @return string the generated code, ready for writing to a .php file
   */
  public static function generateClassCode(string $classname, array $code_blocks, array $additional_class_comment_lines=[]): string
  {
    $logger=Logger::getInstance();
    if(!$code_blocks)
    {
      if(!self::$allowEmptyMigration)
      {
        $logger->error('database is empty, didn\'t create a migration class');
        return '';
      }
      else
      {
        $logger->warn('database is empty, created migration class anyway');
        $code_blocks=['//original database was empty'];
      }
    }

    $template='<?php
declare(strict_types=1);
/**
 * Database migration generated by reverse-engineering live MySQL database
 *
[[COMMENTS]]
 *
 * Requires PHP version 7.0+
 * @codingStandardsIgnoreRule RN.Classes.ClassDeclaration
 */

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

/**
 * Phinx database migration class
 */
class [[CLASSNAME]] extends AbstractMigration
{
  /**
   * Perform migration (gets called by Phinx)
   *
   * @return void
   */
  public function change()
  {
    [[CODE]]
  }
}
';
    $replacements=['[[COMMENTS]]'=>' * '.implode("\n * ",$additional_class_comment_lines),
                   '[[CLASSNAME]]'=>$classname,
                   '[[CODE]]'=>implode("\n\n    ",$code_blocks)];
    return str_replace(array_keys($replacements),array_values($replacements),$template);
  }
}
