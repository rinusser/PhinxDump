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

use RN\PhinxDump\InformationSchemaParser;
use RN\PhinxDump\Model;
use PHPUnit\Framework\TestCase;

/**
 * Tests for parsing information_schema data
 */
class InformationSchemaParserTest extends TestCase
{
  protected function _assertIsColumn($obj, $class, string $name, $default, bool $nullable, ?string $comment=NULL)
  {
    $this->assertInstanceOf($class,$obj);
    $this->assertEquals($name,$obj->name,'column name');
    $this->assertEquals($default,$obj->default,'column default value');
    $this->assertEquals($nullable,$obj->nullable,'column nullable');
    $this->assertSame($comment,$obj->comment,'column comment');
  }

  protected function _assertIsTable($obj, string $name, int $column_count, ?string $comment=NULL)
  {
    $this->assertInstanceOf(Model\Table::class,$obj);
    $this->assertEquals($name,$obj->name,'table name');
    $this->assertEquals($column_count,count($obj->columns),'table column count');
    $this->assertSame($comment,$obj->comment,'table comment');
  }

  protected function _parse(string $table, array $data, array $index_data=[], ?string $engine=NULL, ?string $collation=NULL, ?string $comment=NULL)
  {
    return InformationSchemaParser::parse(['table_name'=>$table,'table_comment'=>$comment,'engine'=>$engine,'table_collation'=>$collation],$data,$index_data);
  }

  protected function _nullStringIfNULL($value): string
  {
    return $value===NULL?'NULL':(string)$value;
  }

  protected function _assembleDataRow(string $name, $default, bool $nullable, string $data_type, ?int $char_len, ?int $precision, ?int $scale,
                                      string $column_type, $extra=NULL, ?string $encoding=NULL, ?string $collation=NULL): array
  {
    //COLUMN_NAME    | COLUMN_DEFAULT | IS_NULLABLE | DATA_TYPE  | CHARACTER_MAXIMUM_LENGTH | NUMERIC_PRECISION | NUMERIC_SCALE | COLUMN_TYPE | EXTRA
    //---------------+----------------+-------------+------------+--------------------------+-------------------+---------------+-------------+---------------
    //role_id        | NULL           | NO          | int        |                     NULL |                10 |             0 | int(11)     | auto_increment
    //role_name      | NULL           | YES         | varchar    |                       50 |              NULL |          NULL | varchar(50) |
    return ['column_name'=>$name,
            'column_default'=>$this->_nullStringIfNULL($default),
            'is_nullable'=>$nullable?'YES':'NO',
            'data_type'=>$data_type,
            'character_maximum_length'=>$this->_nullStringIfNULL($char_len),
            'numeric_precision'=>$this->_nullStringIfNULL($precision),
            'numeric_scale'=>$this->_nullStringIfNULL($scale),
            'column_type'=>$column_type,
            'character_set_name'=>$this->_nullStringIfNULL($encoding),
            'collation_name'=>$this->_nullStringIfNULL($collation),
            'extra'=>$extra];
  }


  /**
   * Test creating empty table models
   * Tables without columns are disallowed in MySQL but the parser should still work
   */
  public function testEmptyTable()
  {
    $this->_assertIsTable($this->_parse('asdf',[]),'asdf',0);
    $this->_assertIsTable($this->_parse('asdf',[],[],NULL,'tbl cmt'),'asdf',0,NULL,'tbl cmt');

    $table=$this->_parse('SomeName',[],[],'SomeEngine','SomeCollation');
    $this->_assertIsTable($table,'SomeName',0);
    $this->assertEquals('SomeEngine',$table->engine);
    $this->assertEquals('SomeCollation',$table->collation);
  }


  protected function _assertIsIntegerColumn($obj, string $name, $default, bool $nullable, bool $unsigned, string $size, $auto_increment=false)
  {
    $this->_assertIsColumn($obj,Model\IntegerColumn::class,$name,$default,$nullable);
    $this->assertEquals($unsigned,$obj->unsigned,'column unsigned');
    $this->assertEquals($size,$obj->size,'column size');
    $this->assertEquals($auto_increment,$obj->autoIncrement,'column auto increment');
  }

  /**
   * Test parsing integer columns, including storage size and unsigned columns
   */
  public function testIntegers()
  {
    //                             name,          def, null, type,       len, pr,sc,type
    $data=[$this->_assembleDataRow('int',         NULL,false,'int',      NULL,10,0,'int(11)'),
           $this->_assembleDataRow('optional int',NULL,true, 'int',      NULL,10,0,'int(11)'),
           $this->_assembleDataRow('a tiny int',  0,   false,'tinyint',  NULL,3, 0,'tinyint(1)'),
           $this->_assembleDataRow('small',       NULL,false,'smallint', NULL,7, 0,'smallint(1)'),
           $this->_assembleDataRow('biiig magic', NULL,false,'bigint',   NULL,19,0,'bigint(20)'),
           $this->_assembleDataRow('unsigned',    NULL,false,'int',      NULL,10,0,'int(10) unsigned'),
           $this->_assembleDataRow('unsigned',    NULL,false,'int',      NULL,10,0,'int(10) unsigned','auto_increment'),
           $this->_assembleDataRow('medint',      NULL,false,'mediumint',NULL,7, 0,'mediumint(9)'),
          ];

    $table=$this->_parse('ints',$data);
    $this->_assertIsTable($table,'ints',8);
    $cols=$table->columns;

    //                            obj,     name,           def, null, unsg, size
    $this->_assertIsIntegerColumn($cols[0],'int',          NULL,false,false,Model\IntegerColumn::SIZE_REGULAR);
    $this->_assertIsIntegerColumn($cols[1],'optional int', NULL,true, false,Model\IntegerColumn::SIZE_REGULAR);
    $this->_assertIsIntegerColumn($cols[2],'a tiny int',   0,   false,false,Model\IntegerColumn::SIZE_TINY);
    $this->_assertIsIntegerColumn($cols[3],'small',        NULL,false,false,Model\IntegerColumn::SIZE_SMALL);
    $this->_assertIsIntegerColumn($cols[4],'biiig magic',  NULL,false,false,Model\IntegerColumn::SIZE_BIG);
    $this->_assertIsIntegerColumn($cols[5],'unsigned',     NULL,false,true, Model\IntegerColumn::SIZE_REGULAR);
    $this->_assertIsIntegerColumn($cols[6],'unsigned',     NULL,false,true, Model\IntegerColumn::SIZE_REGULAR,true);
    $this->_assertIsIntegerColumn($cols[7],'medint',       NULL,false,false,Model\IntegerColumn::SIZE_MEDIUM);
  }


  protected function _assertIsFloatColumn($obj, string $name, $default, bool $nullable, bool $double_precision)
  {
    $this->_assertIsColumn($obj,Model\FloatColumn::class,$name,$default,$nullable);
    $this->assertEquals($double_precision?Model\FloatColumn::PRECISION_DOUBLE:Model\FloatColumn::PRECISION_SINGLE,$obj->precision,'float precision');
  }

  /**
   * Test parsing float/double columns
   * DOUBLE currently can't be created by Phinx, until then the parser should generate the model correctly though
   */
  public function testFloats()
  {
    //                             name,def,  null, type,    len, pr,scal,type
    $data=[$this->_assembleDataRow('f1',NULL, false,'float', NULL,12,NULL,'float'),
           $this->_assembleDataRow('f2','1.1',true, 'double',NULL,22,NULL,'double'),
           $this->_assembleDataRow('f3',NULL, true, 'float', NULL,12,NULL,'float')];

    $table=$this->_parse('floats',$data);
    $this->_assertIsTable($table,'floats',3);
    $cols=$table->columns;

    //                          obj,     name,def,  null, double_precision
    $this->_assertIsFloatColumn($cols[0],'f1',NULL, false,false);
    $this->_assertIsFloatColumn($cols[1],'f2','1.1',true, true);
    $this->_assertIsFloatColumn($cols[2],'f3',NULL, true, false);
  }


  protected function _assertIsDecimalColumn($obj, string $name, $default, bool $nullable, int $precision, int $scale, bool $unsigned)
  {
    $this->_assertIsColumn($obj,Model\DecimalColumn::class,$name,$default,$nullable);
    $this->assertEquals($precision,$obj->precision,$name.': decimal column precision');
    $this->assertEquals($scale,$obj->scale,$name.': decimal column scale');
    $this->assertEquals($unsigned,$obj->unsigned,$name.': decimal column unsigned');
  }

  /**
   * Test parsing decimal columns, especially the precision/scale settings and the unsigned flag
   */
  public function testDecimal()
  {
    //                             name,  def,  null, type,     len, pr,sc,type
    $data=[$this->_assembleDataRow('dec1',NULL, false,'decimal',NULL, 1, 0,'decimal(1,0)'),
           $this->_assembleDataRow('dec2','0.2',true, 'decimal',NULL,20,20,'decimal(20,20)'),
           $this->_assembleDataRow('dec3',NULL, true, 'decimal',NULL, 7, 3,'decimal(7,3) unsigned')];

    $table=$this->_parse('decimals',$data);
    $this->_assertIsTable($table,'decimals',3);
    $cols=$table->columns;

    //                            obj,     name,  def,  null, pr,sc,unsigned
    $this->_assertIsDecimalColumn($cols[0],'dec1',NULL, false, 1, 0,false);
    $this->_assertIsDecimalColumn($cols[1],'dec2','0.2',true, 20,20,false);
    $this->_assertIsDecimalColumn($cols[2],'dec3',NULL, true,  7, 3,true);
  }


  protected function _assertIsCharColumn($obj, string $name, $default, bool $nullable, bool $variable, int $length, ?string $encoding, ?string $collation)
  {
    $this->_assertIsColumn($obj,Model\CharColumn::class,$name,$default,$nullable);
    $this->assertEquals($variable,$obj->variable,'column variable length');
    $this->assertEquals($length,$obj->length,'column length');
    $this->assertEquals($encoding,$obj->encoding,'column encoding');
    $this->assertEquals($collation,$obj->collation,'column collation');
  }

  /**
   * Test parsing character columns, especially char/varchar distinction and maximum length
   */
  public function testChars()
  {
    //                              name,     def, null, type,      len, prec,scal,type,         extr,enc, coll
    $data=[$this->_assembleDataRow('fixed',   'en',false,'char',    2,   NULL,NULL,'char(2)',     NULL),
           $this->_assembleDataRow('variable',NULL,true, 'varchar', 300, NULL,NULL,'varchar(300)',NULL,'ee','cc')];

    $table=$this->_parse('chars',$data);
    $this->_assertIsTable($table,'chars',2);
    $cols=$table->columns;

    //                         obj,     name,      def, null, var,  len,enc, coll
    $this->_assertIsCharColumn($cols[0],'fixed',   'en',false,false,2,  NULL,NULL);
    $this->_assertIsCharColumn($cols[1],'variable',NULL,true, true, 300,'ee','cc');
  }


  protected function _assertIsLOBColumn($obj, string $name, bool $nullable, string $type, string $size)
  {
    $this->_assertIsColumn($obj,Model\LOBColumn::class,$name,NULL,$nullable);
    $this->assertEquals($type,$obj->type,'column type');
    $this->assertEquals($size,$obj->size,'column size');
  }

  /**
   * Test parsing BLOB/CLOB ("TEXT" in MySQL) columns, especially storage sizes
   */
  public function testLOBs()
  {
    //                             name,   def, null, type,           length, prec,scal,type
    $data=[$this->_assembleDataRow('tinyt',NULL,false,'tinytext',         255,NULL,NULL,'tinytext'),
           $this->_assembleDataRow('txt',  NULL,true, 'text',           65535,NULL,NULL,'text'),
           $this->_assembleDataRow('med',  NULL,false,'mediumtext',  16777215,NULL,NULL,'mediumtext'),
           $this->_assembleDataRow('loong',NULL,true, 'longtext',  4294967295,NULL,NULL,'longtext'),
           $this->_assembleDataRow('tinyb',NULL,false,'tinyblob',         255,NULL,NULL,'tinytext'),
           $this->_assembleDataRow('blb',  NULL,true, 'blob',           65535,NULL,NULL,'blob'),
           $this->_assembleDataRow('medb', NULL,false,'mediumblob',  16777215,NULL,NULL,'mediumblob'),
           $this->_assembleDataRow('longb',NULL,true, 'longblob',  4294967295,NULL,NULL,'longblob'),
           ];

    $table=$this->_parse('LOBs',$data);
    $this->_assertIsTable($table,'LOBs',8);
    $cols=$table->columns;

    //                        obj,     name,   null, type,                      size
    $this->_assertIsLOBColumn($cols[0],'tinyt',false,Model\LOBColumn::TYPE_TEXT,Model\LOBColumn::SIZE_TINY);
    $this->_assertIsLOBColumn($cols[1],'txt',  true, Model\LOBColumn::TYPE_TEXT,Model\LOBColumn::SIZE_REGULAR);
    $this->_assertIsLOBColumn($cols[2],'med',  false,Model\LOBColumn::TYPE_TEXT,Model\LOBColumn::SIZE_MEDIUM);
    $this->_assertIsLOBColumn($cols[3],'loong',true, Model\LOBColumn::TYPE_TEXT,Model\LOBColumn::SIZE_LONG);
    $this->_assertIsLOBColumn($cols[4],'tinyb',false,Model\LOBColumn::TYPE_BLOB,Model\LOBColumn::SIZE_TINY);
    $this->_assertIsLOBColumn($cols[5],'blb',  true, Model\LOBColumn::TYPE_BLOB,Model\LOBColumn::SIZE_REGULAR);
    $this->_assertIsLOBColumn($cols[6],'medb', false,Model\LOBColumn::TYPE_BLOB,Model\LOBColumn::SIZE_MEDIUM);
    $this->_assertIsLOBColumn($cols[7],'longb',true, Model\LOBColumn::TYPE_BLOB,Model\LOBColumn::SIZE_LONG);
  }

  /**
   * Test parsing TEXT columns with encoding
   */
  public function testLOBEncoding()
  {
    //                                                  nam,def, null, type,  len,  prec,scal,type,  extr,enc,coll
    $table=$this->_parse('enc',[$this->_assembleDataRow('a',NULL,false,'text',65535,NULL,NULL,'text',NULL,'e','c')]);
    $this->_assertIsTable($table,'enc',1);
    $this->assertEquals('e',$table->columns[0]->encoding, 'TEXT column encoding');
    $this->assertEquals('c',$table->columns[0]->collation,'TEXT column collation');
  }


  protected function _assertIsListColumn($obj, string $name, $default, bool $nullable, bool $multiple, array $values)
  {
    $this->_assertIsColumn($obj,Model\ListColumn::class,$name,$default,$nullable);
    $this->assertEquals($multiple,$obj->multiple);
    $this->assertEquals($values,$obj->values);
  }

  /**
   * Test parsing set/enum columns, especially set/enum distinction and multiple value defaults for sets
   */
  public function testLists()
  {
    $data=[];
    //                              name,       default,       null, type, len,prec,scal,type
    $data[]=$this->_assembleDataRow('tea set',  NULL,          false,'set', 16,NULL,NULL,"set('cup','saucer','spoon')");
    $data[]=$this->_assembleDataRow('opt set',  'saucer,spoon',true, 'set', 16,NULL,NULL,"set('cup','saucer','spoon')");
    $data[]=$this->_assembleDataRow('edit enum',NULL,          false,'enum',12,NULL,NULL,"enum('VIM','EMACS','EDLIN')");
    $data[]=$this->_assembleDataRow('empty1',   NULL,          true, 'set',  0,NULL,NULL,"set('')");
    $data[]=$this->_assembleDataRow('empty2',   NULL,          true, 'set',  5,NULL,NULL,"set('asdf','')");
    $data[]=$this->_assembleDataRow('empty3',   NULL,          true, 'set',  7,NULL,NULL,"set('','2','fdsa')");

    $table=$this->_parse('lists',$data);
    $this->_assertIsTable($table,'lists',6);
    $cols=$table->columns;

    //                         obj,     name,       default,       null, multi,values
    $this->_assertIsListColumn($cols[0],'tea set',  NULL,          false,true, ['cup','saucer','spoon']);
    $this->_assertIsListColumn($cols[1],'opt set',  'saucer,spoon',true, true, ['cup','saucer','spoon']);
    $this->_assertIsListColumn($cols[2],'edit enum',NULL,          false,false,['VIM','EMACS','EDLIN']);
    $this->_assertIsListColumn($cols[3],'empty1',   NULL,          true, true, ['']);
    $this->_assertIsListColumn($cols[4],'empty2',   NULL,          true, true, ['asdf','']);
    $this->_assertIsListColumn($cols[5],'empty3',   NULL,          true, true, ['','2','fdsa']);
  }


  protected function _assertIsTemporalColumn($obj, string $name, $default, bool $nullable, string $type, bool $on_update)
  {
    $this->_assertIsColumn($obj,Model\TemporalColumn::class,$name,$default,$nullable);
    $this->assertEquals($type,$obj->type);
    $this->assertEquals($on_update,$obj->onUpdateCurrentTimestamp);
  }

  /**
   * Test parsing date/time columns, especially CURRENT_TIMESTAMP default/trigger for timestamp
   */
  public function testTemporals()
  {
    $data=[];
    //                              name, def,                null, type,       len, prec,scal,type,       extra
    $data[]=$this->_assembleDataRow('d',  NULL,               false,'date',     NULL,NULL,NULL,'date',     NULL);
    $data[]=$this->_assembleDataRow('t',  NULL,               true, 'time',     NULL,NULL,NULL,'time',     NULL);
    $data[]=$this->_assembleDataRow('dt', 'CURRENT_TIMESTAMP',false,'datetime', NULL,NULL,NULL,'datetime', NULL);
    $data[]=$this->_assembleDataRow('ts1',NULL,               true, 'timestamp',NULL,NULL,NULL,'timestamp',NULL);
    $data[]=$this->_assembleDataRow('ts2','CURRENT_TIMESTAMP',true, 'timestamp',NULL,NULL,NULL,'timestamp',NULL);
    $data[]=$this->_assembleDataRow('ts3','CURRENT_TIMESTAMP',false,'timestamp',NULL,NULL,NULL,'timestamp',NULL);
    $data[]=$this->_assembleDataRow('ts4',NULL,               true, 'timestamp',NULL,NULL,NULL,'timestamp','on update CURRENT_TIMESTAMP');
    $data[]=$this->_assembleDataRow('ts5','CURRENT_TIMESTAMP',true, 'timestamp',NULL,NULL,NULL,'timestamp','on update CURRENT_TIMESTAMP');
    $data[]=$this->_assembleDataRow('ts6','CURRENT_TIMESTAMP',false,'timestamp',NULL,NULL,NULL,'timestamp','on update CURRENT_TIMESTAMP');

    $table=$this->_parse('temporals',$data);
    $this->_assertIsTable($table,'temporals',9);
    $cols=$table->columns;

    //                             obj,     name, default,            null, type,       onupdt
    $this->_assertIsTemporalColumn($cols[0],'d',  NULL,               false,'date',     false);
    $this->_assertIsTemporalColumn($cols[1],'t',  NULL,               true, 'time',     false);
    $this->_assertIsTemporalColumn($cols[2],'dt', 'CURRENT_TIMESTAMP',false,'datetime', false);
    $this->_assertIsTemporalColumn($cols[3],'ts1',NULL,               true, 'timestamp',false);
    $this->_assertIsTemporalColumn($cols[4],'ts2','CURRENT_TIMESTAMP',true, 'timestamp',false);
    $this->_assertIsTemporalColumn($cols[5],'ts3','CURRENT_TIMESTAMP',false,'timestamp',false);
    $this->_assertIsTemporalColumn($cols[6],'ts4',NULL,               true, 'timestamp',true);
    $this->_assertIsTemporalColumn($cols[7],'ts5','CURRENT_TIMESTAMP',true, 'timestamp',true);
    $this->_assertIsTemporalColumn($cols[8],'ts6','CURRENT_TIMESTAMP',false,'timestamp',true);
  }


  protected function _assertIsIndex($obj, string $name, bool $unique, array $column_names, array $column_subparts)
  {
    $this->assertInstanceOf(Model\Index::class,$obj);
    $this->assertEquals($name,$obj->name,'index name');
    $this->assertEquals($unique,$obj->unique,'index uniqueness');
    $this->assertEquals($column_names,$obj->columns,'index columns');
    $this->assertEquals($column_subparts,$obj->columnSubparts,'index subparts');
  }

  /**
   * Test parsing indices
   */
  public function testIndices()
  {
    $index_cols=['index_name','non_unique','seq_in_index','column_name','sub_part'];
    $index_data=[['PRIMARY',  0,           1,             'c1',         NULL],
                 ['uniq',     0,           1,             'c2',         NULL],
                 ['uniq',     0,           2,             'c3',         NULL],
                 ['idx',      1,           1,             'c4',         NULL],
                 ['sub',      0,           1,             'c1',         5],
                 ['sub',      0,           2,             'c2',         NULL],
                 ['sub',      0,           3,             'c3',         15],
                ];

    $mapper=function($row) use ($index_cols) {
      return array_combine($index_cols,$row);
    };

    $table=$this->_parse('indices',[],array_map($mapper,$index_data));
    $this->_assertIsTable($table,'indices',0);
    $this->assertEquals(3,count($table->indices));

    //                    obj,                    name,     unique,columns,        subparts
    $this->_assertIsIndex($table->primaryKey,     'PRIMARY',true, ['c1'],          [NULL]);
    $this->_assertIsIndex($table->indices['uniq'],'uniq',   true, ['c2','c3'],     [NULL,NULL]);
    $this->_assertIsIndex($table->indices['idx'], 'idx',    false,['c4'],          [NULL]);
    $this->_assertIsIndex($table->indices['sub'], 'sub',    true, ['c1','c2','c3'],[5,NULL,15]);
  }
}
