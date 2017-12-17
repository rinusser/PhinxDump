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

use RN\PhinxDump\CodeGenerator\ColumnCodeGenerator;
use RN\PhinxDump\Model;
use RN\PhinxDump\Tests\Stubs\MysqlAdapter;
use RN\PhinxDump\Tests\CodeGeneratorTestCase;

/**
 * Tests for generating column code
 */
class ColumnCodeGeneratorTest extends CodeGeneratorTestCase
{
  protected function _assertAddColumnAndReturnOptions($name, $type, $code)
  {
    $message='column "'.$name.'"';
    $matches=[];
    //                                  ->addColumn('name','type',['limit'=>1,'null'=>true])
    $this->assertEquals(1,preg_match("/^->addColumn\('([^']+)','([^']+)'(,\[.*\])?\)$/",$code,$matches),$message);
    $this->assertEquals($name,$matches[1],$message);
    $this->assertEquals($type,$matches[2],$message);

    return isset($matches[3])?$this->_silentEval("use RN\PhinxDump\Tests\Stubs\MySQLAdapter;\nreturn ".ltrim($matches[3],',').';'):[];
  }

  protected function _removeDefaultIntegerSizeFromOptions(array &$options)
  {
    if(isset($options['limit'])&&$options['limit']==MysqlAdapter::INT_REGULAR)
      unset($options['limit']);
  }

  protected function _runAddColumnTest(Model\AbstractColumn $column, ?callable $option_mangler, string $expected_name, string $expected_type, $expected_options)
  {
    $options=$this->_assertAddColumnAndReturnOptions($expected_name,$expected_type,ColumnCodeGenerator::generateAddColumnCode($column));
    if($option_mangler)
      $option_mangler($options);
    $this->assertEquals($expected_options,$options,'column "'.$expected_name.'"');
    return $options;
  }


  protected function _runAddIntegerColumnTest(Model\IntegerColumn $column, string $expected_name, $expected_options)
  {
    $this->_runAddColumnTest($column,[$this,'_removeDefaultIntegerSizeFromOptions'],$expected_name,'integer',$expected_options);
  }

  /**
   * Make sure integer column code is generated correctly
   */
  public function testAddIntegerColumn()
  {
    //       name,def,  null  unsg  size                              autoi,expected_options
    $cases=[['i1',NULL, false,false,Model\IntegerColumn::SIZE_REGULAR,false,[]],
            ['i2',NULL, true, true, Model\IntegerColumn::SIZE_TINY,   true, ['null'=>true,'signed'=>false,'identity'=>true,'limit'=>MysqlAdapter::INT_TINY]],
            ['i3','-14',false,true, Model\IntegerColumn::SIZE_SMALL,  false,['signed'=>false,'default'=>-14,'limit'=>MysqlAdapter::INT_SMALL]],
            ['i4',NULL, false,false,Model\IntegerColumn::SIZE_BIG,    false,['limit'=>MysqlAdapter::INT_BIG]],
            ['i5',NULL, false,false,Model\IntegerColumn::SIZE_MEDIUM, false,['limit'=>MysqlAdapter::INT_MEDIUM]],
           ];

    foreach($cases as $case)
    {
      list($name,$default,$nullable,$unsigned,$size,$auto_increment,$expected_options)=$case;
      $column=new Model\IntegerColumn($name,$default,$nullable,$unsigned,$size,$auto_increment);
      $this->_runAddIntegerColumnTest($column,$name,$expected_options);
    }
  }

  /**
   * Make sure column comments are included in generated code
   */
  public function testAddColumnComment()
  {
    //       name, comment,  expected_options
    $cases=[['ic1',NULL,     []],
            ['ic2','sharona',['comment'=>'sharona']]];

    foreach($cases as $case)
    {
      list($name,$comment,$expected_options)=$case;
      $column=new Model\IntegerColumn($name,NULL,false,false,Model\IntegerColumn::SIZE_REGULAR,false);
      $column->comment=$comment;
      $this->_runAddIntegerColumnTest($column,$name,$expected_options);
    }
  }


  /**
   * Make sure floating point number columns are generated correctly
   * XXX "double" type not implemented as of Phinx 0.8.1, so that testcase is disabled
   */
  public function testAddFloatColumn()
  {
    //       name,def,   null, precision,                          expected_options
    $cases=[['f1',NULL,  false,Model\FloatColumn::PRECISION_SINGLE,[]],
            ['f2','-9.9',true, Model\FloatColumn::PRECISION_SINGLE,['null'=>true,'default'=>'-9.9']],
//            ['f3',NULL,  false,Model\FloatColumn::PRECISION_DOUBLE,[]],
           ];

    foreach($cases as $case)
    {
      list($name,$default,$nullable,$precision,$expected_options)=$case;
      $column=new Model\FloatColumn($name,$default,$nullable,$precision);
      $this->_runAddColumnTest($column,NULL,$name,$precision==Model\FloatColumn::PRECISION_DOUBLE?'double':'float',$expected_options);
    }
  }


  /**
   * Make sure decimal column code is generated correctly
   */
  public function testDecimalColumn()
  {
    //       name,  def,  null, pr,sc,unsgn,expected_options
    $cases=[['dec1',NULL, true,  1, 0,false,['precision'=>1,'scale'=>0,'null'=>true]],
            ['dec2','0.9',false,20,20,true, ['precision'=>20,'scale'=>20,'signed'=>false,'default'=>'0.9']]];

    foreach($cases as $case)
    {
      list($name,$default,$nullable,$precision,$scale,$unsigned,$expected_options)=$case;
      $column=new Model\DecimalColumn($name,$default,$nullable,$precision,$scale,$unsigned);
      $this->_runAddColumnTest($column,NULL,$name,'decimal',$expected_options);
    }
  }


  protected function _runAddCharColumnTest(Model\CharColumn $column, string $expected_name, bool $variable, $expected_options)
  {
    $this->_runAddColumnTest($column,NULL,$expected_name,$variable?'string':'char',$expected_options);
  }

  /**
   * Make sure character column code is generated correctly
   */
  public function testAddCharColumn()
  {
    //       name,def,   null  varbl,len,expected_options
    $cases=[['c1',NULL,  false,false,123,['limit'=>123]],
            ['c2','asdf',false,true, 12, ['limit'=>12,'default'=>'asdf']],
            ['c3',NULL,  true, false,2,  ['limit'=>2,'null'=>true]]];

    foreach($cases as $case)
    {
      list($name,$default,$nullable,$variable,$length,$expected_options)=$case;
      $column=new Model\CharColumn($name,$default,$nullable,$variable,$length);
      $this->_runAddCharColumnTest($column,$name,$variable,$expected_options);
    }
  }


  protected function _removeDefaultLimitFromOptions(array &$options, $limit)
  {
    if(isset($options['limit'])&&$options['limit']==$limit)
      unset($options['limit']);
  }

  protected function _removeDefaultBLOBSizeFromOptions(array &$options)
  {
    $this->_removeDefaultLimitFromOptions($options,MysqlAdapter::BLOB_REGULAR);
  }

  protected function _removeDefaultTextSizeFromOptions(array &$options)
  {
    $this->_removeDefaultLimitFromOptions($options,MysqlAdapter::TEXT_REGULAR);
  }

  protected function _runAddLOBColumnTest(Model\LOBColumn $column, string $expected_name, $type, $expected_options)
  {
    $mangler=[$this,$type==Model\LOBColumn::TYPE_TEXT?'_removeDefaultTextSizeFromOptions':'_removeDefaultBLOBSizeFromOptions'];
    $this->_runAddColumnTest($column,$mangler,$expected_name,$type,$expected_options);
  }

  /**
   * Make sure TEXT/BLOB columns are generated correctly
   */
  public function testAddLOBColumn()
  {
    //       name,  def,  null  type,                      size                          expected_options
    $cases=[['lob1',NULL, false,Model\LOBColumn::TYPE_TEXT,Model\LOBColumn::SIZE_LONG,   ['limit'=>MysqlAdapter::TEXT_LONG]],
            ['lob2',NULL, true, Model\LOBColumn::TYPE_TEXT,Model\LOBColumn::SIZE_MEDIUM, ['limit'=>MysqlAdapter::TEXT_MEDIUM,'null'=>true]],
            ['lob3','-14',false,Model\LOBColumn::TYPE_TEXT,Model\LOBColumn::SIZE_REGULAR,['default'=>'-14']],
            ['lob4',NULL, false,Model\LOBColumn::TYPE_TEXT,Model\LOBColumn::SIZE_TINY,   ['limit'=>MysqlAdapter::TEXT_TINY]],
            ['lob5',NULL, false,Model\LOBColumn::TYPE_BLOB,Model\LOBColumn::SIZE_LONG,   ['limit'=>MysqlAdapter::BLOB_LONG]],
            ['lob6',NULL, true, Model\LOBColumn::TYPE_BLOB,Model\LOBColumn::SIZE_MEDIUM, ['limit'=>MysqlAdapter::BLOB_MEDIUM,'null'=>true]],
            ['lob7','-14',false,Model\LOBColumn::TYPE_BLOB,Model\LOBColumn::SIZE_REGULAR,['default'=>'-14']],
            ['lob8',NULL, false,Model\LOBColumn::TYPE_BLOB,Model\LOBColumn::SIZE_TINY,   ['limit'=>MysqlAdapter::BLOB_TINY]]];

    foreach($cases as $case)
    {
      list($name,$default,$nullable,$type,$size,$expected_options)=$case;
      $column=new Model\LOBColumn($name,$default,$nullable,$type,$size);
      $this->_runAddLOBColumnTest($column,$name,$type,$expected_options);
    }
  }


  protected function _runAddListColumnTest(Model\ListColumn $column, string $expected_name, bool $multiple, array $values, $expected_options)
  {
    $expected_options['values']=$values;
    $options=$this->_runAddColumnTest($column,NULL,$expected_name,$multiple?'set':'enum',$expected_options);
    $this->assertSame($values,$options['values']);
  }

  /**
   * Make sure code for set/list columns is generated correctly
   */
  public function testAddListColumn()
  {
    //       name,def,  null, mult, values,       expected_options
    $cases=[['l1',NULL, false,false,[''],         []],
            ['l2',NULL, true, false,['','XX'],    ['null'=>true]],
            ['l2',NULL, false,false,['Z',''],     []],
            ['l3','A,B',true, true, ['A','B','C'],['default'=>'A,B','null'=>true]]];

    foreach($cases as $case)
    {
      list($name,$default,$nullable,$multiple,$values,$expected_options)=$case;
      $column=new Model\ListColumn($name,$default,$nullable,$multiple,$values);
      $this->_runAddListColumnTest($column,$name,$multiple,$values,$expected_options);
    }
  }


  /**
   * Make sure code for temporal column types is generated correctly
   */
  public function testAddTemporalColumn()
  {
    //       name,  default,            null, type,       onupd,expected_options
    $cases=[['d',   NULL,               false,'date',     false,[]],
            ['t1',  NULL,               true, 'time',     false,['null'=>true]],
            ['t2',  '12:34:56',         false,'time',     false,['default'=>'12:34:56']],
            ['dt',  NULL,               false,'datetime', false,[]],
            ['ts1', NULL,               false,'timestamp',false,['default'=>NULL,               'update'=>NULL]],
            ['ts2', 'CURRENT_TIMESTAMP',false,'timestamp',false,['default'=>'CURRENT_TIMESTAMP','update'=>NULL]],
            ['ts3', NULL,               true, 'timestamp',false,['null'=>true,'default'=>NULL,  'update'=>NULL]],
            ['ts4', NULL,               false,'timestamp',true, ['default'=>NULL,               'update'=>'CURRENT_TIMESTAMP']],
            ['ts5', 'CURRENT_TIMESTAMP',false,'timestamp',true, ['default'=>'CURRENT_TIMESTAMP','update'=>'CURRENT_TIMESTAMP']],
            ['ts6', NULL,               true, 'timestamp',true, ['null'=>true,'default'=>NULL,  'update'=>'CURRENT_TIMESTAMP']],
           ];

    foreach($cases as $case)
    {
      list($name,$default,$nullable,$type,$on_update,$expected_options)=$case;
      $column=new Model\TemporalColumn($name,$default,$nullable,$type,$on_update);
      $this->_runAddColumnTest($column,NULL,$name,$type,$expected_options);
    }
  }


  /**
   * Make sure code comments for columns are generated
   */
  public function testColumnCodeComments()
  {
    //       name,    col?,   code?
    $cases=[['com_nn',false,  false],
            ['com_yn',true,   false],
            ['com_ny',false,  true],
            ['com_yy',true,   true],
           ];
    foreach($cases as $case)
    {
      list($name,$with_column_comment,$with_code_comment)=$case;
      $column=new Model\FloatColumn($name,NULL,false,Model\FloatColumn::PRECISION_SINGLE);
      $column->comment=$with_column_comment?$name.' column comment':NULL;
      $column->codeComment=$with_code_comment?$name.' code comment':NULL;

      $code=ColumnCodeGenerator::generateAddColumnCode($column);
      $has_column_comment=strpos($code,"'comment'=>'$name column comment'")!==false;
      $this->assertEquals($with_column_comment,$has_column_comment,$name.': column comment existence should match');
      $matches=[];
      $has_code_comment=preg_match('#//(.*)$#',$code,$matches);
      $this->assertEquals($with_code_comment,$has_code_comment,$name.': code comment existence should match');
      if($has_code_comment)
        $this->assertEquals($name.' code comment',$matches[1],'code comment contents');
    }
  }
}
