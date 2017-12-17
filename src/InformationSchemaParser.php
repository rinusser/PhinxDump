<?php
declare(strict_types=1);
/**
 * Requires PHP version 7.0+.
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 * @link https://github.com/rinusser/PhinxDump
 */

namespace RN\PhinxDump;

use RN\PhinxDump\Model;

/**
 * This class takes a table's information_schema data (as PHP arrays) and turns it into a Model\Table entity.
 */
abstract class InformationSchemaParser
{
  public static $preserveMyISAM=false;


  protected static function _returnValueByType(array $data, array $types)
  {
    foreach($types as $type=>$value)
      if($type==$data['data_type'])
        return $value;
    return NULL;
  }

  protected static function _findIntegerSize(array $data)
  {
    $sizes=['tinyint'  =>Model\IntegerColumn::SIZE_TINY,
            'smallint' =>Model\IntegerColumn::SIZE_SMALL,
            'mediumint'=>Model\IntegerColumn::SIZE_MEDIUM,
            'int'      =>Model\IntegerColumn::SIZE_REGULAR,
            'bigint'   =>Model\IntegerColumn::SIZE_BIG];
    return self::_returnValueByType($data,$sizes);
  }

  protected static function _findFloatPrecision($data)
  {
    $types=['float' =>Model\FloatColumn::PRECISION_SINGLE,
            'double'=>Model\FloatColumn::PRECISION_DOUBLE];
    return self::_returnValueByType($data,$types);
  }

  protected static function _findDecimalPrecisionAndScale($data)
  {
    if($data['data_type']!='decimal')
      return [NULL,NULL];
    $matches=[];
    if(!preg_match('/^decimal\(([0-9]+),([0-9]+)\)/i',$data['column_type'],$matches))
      throw new \LogicException('cannot parse column type "'.$data['column_type'].'"');
    return [(int)$matches[1],(int)$matches[2]];
  }

  protected static function _findCharType(array $data)
  {
    $types=['char'=>false,
            'varchar'=>true];
    return self::_returnValueByType($data,$types);
  }

  protected static function _findLOBTypeAndSize(array $data): array
  {
    $textsizes=['tinytext'  =>Model\LOBColumn::SIZE_TINY,
                'text'      =>Model\LOBColumn::SIZE_REGULAR,
                'mediumtext'=>Model\LOBColumn::SIZE_MEDIUM,
                'longtext'  =>Model\LOBColumn::SIZE_LONG];
    $size=self::_returnValueByType($data,$textsizes);
    if($size)
      return [Model\LOBColumn::TYPE_TEXT,$size];

    $blobsizes=['tinyblob'  =>Model\LOBColumn::SIZE_TINY,
                'blob'      =>Model\LOBColumn::SIZE_REGULAR,
                'mediumblob'=>Model\LOBColumn::SIZE_MEDIUM,
                'longblob'  =>Model\LOBColumn::SIZE_LONG];
    $size=self::_returnValueByType($data,$blobsizes);
    if($size)
      return [Model\LOBColumn::TYPE_BLOB,$size];

    return [NULL,NULL];
  }

  protected static function _findListType(array $data)
  {
    $types=['enum'=>false,
            'set'=>true];
    return self::_returnValueByType($data,$types);
  }

  protected static function _parseListValues(array $data): array
  {
    $rv=[];
    $matches=[];
    if(!preg_match('/^(enum|set)\(((\'[^\']*\',?)*)\)$/',$data['column_type'],$matches))
      throw new \LogicException('cannot parse enum/set values from "'.$data['column_type'].'"');
    foreach(explode(',',$matches[2]) as $item)
      $rv[]=trim($item,'\'');
    return $rv;
  }

  protected static function _findTemporalType(array $data)
  {
    $types=['date'     =>Model\TemporalColumn::TYPE_DATE,
            'time'     =>Model\TemporalColumn::TYPE_TIME,
            'datetime' =>Model\TemporalColumn::TYPE_DATETIME,
            'timestamp'=>Model\TemporalColumn::TYPE_TIMESTAMP];
    return self::_returnValueByType($data,$types);
  }

  protected static function _parseColumn(array $data): Model\AbstractColumn
  {
    //COLUMN_NAME          | COLUMN_DEFAULT | IS_NULLABLE | DATA_TYPE  | CHARACTER_MAXIMUM_LENGTH | NUMERIC_PRECISION | NUMERIC_SCALE | COLUMN_TYPE
    //---------------------+----------------+-------------+------------+--------------------------+-------------------+---------------+------------
    //role_id              | NULL           | NO          | int        |                     NULL |                10 |             0 | int(11)
    //role_name            | NULL           | YES         | varchar    |                       50 |              NULL |          NULL | varchar(50)

    $name=$data['column_name'];
    $default=$data['column_default']!=='NULL'?$data['column_default']:NULL;
    $nullable=$data['is_nullable']==='YES';
    $unsigned=stripos($data['column_type'],' unsigned')!==false;

    $int_size=self::_findIntegerSize($data);
    if($int_size)
    {
      $autoincrement=!empty($data['extra'])?in_array('auto_increment',explode(' ',$data['extra'])):false;
      return new Model\IntegerColumn($name,$default,$nullable,$unsigned,$int_size,$autoincrement);
    }

    $float_precision=self::_findFloatPrecision($data);
    if($float_precision!==NULL)
      return new Model\FloatColumn($name,$default,$nullable,$float_precision);

    list($decimal_precision,$decimal_scale)=self::_findDecimalPrecisionAndScale($data);
    if($decimal_precision!==NULL)
      return new Model\DecimalColumn($name,$default,$nullable,$decimal_precision,$decimal_scale,$unsigned);

    $char_type=self::_findCharType($data);
    if($char_type!==NULL)
      return new Model\CharColumn($name,$default,$nullable,$char_type,(int)$data['character_maximum_length']);

    list($lob_type,$lob_size)=self::_findLOBTypeAndSize($data);
    if($lob_type!==NULL)
      return new Model\LOBColumn($name,$default,$nullable,$lob_type,$lob_size);

    $list_type=self::_findListType($data);
    if($list_type!==NULL)
      return new Model\ListColumn($name,$default,$nullable,$list_type,self::_parseListValues($data));

    $temporal_type=self::_findTemporalType($data);
    if($temporal_type!==NULL)
    {
      $on_update_current_timestamp=!empty($data['extra'])?stripos(strtolower($data['extra']),'on update current_timestamp')!==false:false;
      return new Model\TemporalColumn($name,$default,$nullable,$temporal_type,$on_update_current_timestamp);
    }

    throw new UnsupportedSchemaException('unhandled column type: data_type='.$data['data_type'].', column_type='.$data['column_type']);
  }

  protected static function _parseIndices(array $data): array
  {
    $rv=[];
    $latest_name=NULL;
    foreach($data as $row)
    {
      if($row['seq_in_index']>1)
      {
        $columns=$rv[$latest_name]->columns;
        $columns[]=$row['column_name'];
        $rv[$latest_name]->columns=$columns;

        $column_subparts=$rv[$latest_name]->columnSubparts;
        $column_subparts[]=$row['sub_part'];
        $rv[$latest_name]->columnSubparts=$column_subparts;
      }
      else
      {
        $latest_name=$row['index_name'];
        $rv[$latest_name]=new Model\Index($row['index_name'],$row['non_unique']==0,[$row['column_name']],[$row['sub_part']]);
      }
    }
    return $rv;
  }

  /**
   * Takes necessary information schema data in arrays and turns it into a Table model
   *
   * @param array $table_data data row for a table
   * @param array $columns    the data rows for the table's columns
   * @param array $index_data the data rows for the table's indices
   * @return Table the assembled table model
   */
  public static function parse(array $table_data, array $columns, array $index_data): Model\Table
  {
    $table=new Model\Table($table_data['table_name'],array_map('self::_parseColumn',$columns));
    $indices=self::_parseIndices($index_data);
    if(isset($indices['PRIMARY']))
    {
      $table->primaryKey=$indices['PRIMARY'];
      unset($indices['PRIMARY']);
    }
    $table->indices=$indices;
    $engine=$table_data['engine'];
    if(!self::$preserveMyISAM && $engine!==NULL && strtolower($engine)==='myisam')
    {
      Logger::getInstance()->warn('found MyISAM table, will be created with target server\'s default storage engine');
      $table->codeComment='was MyISAM in original table';
      $engine=NULL;
    }
    $table->engine=$engine;
    $table->comment=$table_data['table_comment'];
    return $table;
  }
}
