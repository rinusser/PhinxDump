<?php
declare(strict_types=1);
/**
 * Requires PHP version 7.1+
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 */

namespace RN\PhinxDump\Tests;

use PHPUnit\Framework\TestCase;
use RN\PhinxDump\MigrationCodeGenerator;
use RN\PhinxDump\Model;
use RN\PhinxDump\Logger;
use RN\PhinxDump\Tests\Stubs\MysqlAdapter;
use RN\PhinxDump\UnsupportedSchemaException;

/**
 * Tests for generating migration code
 */
class MigrationCodeGeneratorTest extends TestCase
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
    MigrationCodeGenerator::$allowDoubleFallback=false;
    MigrationCodeGenerator::$allowEmptyMigration=false;
  }


  /**
   * Test Phinx table creation code generation
   */
  public function testTableGeneration()
  {
    $columns=[new Model\CharColumn('c1',NULL,false,true,10)];

    $indices=[new Model\Index('idx1',false,['c1','c2']),
              new Model\Index('idx2',true,['c3'])];

    $table=new Model\Table('tblname',$columns,NULL,$indices);
    $code=MigrationCodeGenerator::generateTableCode($table);

    $matches=[];
    preg_match_all('/->(table|addColumn|addIndex|create)\(/',$code,$matches);
    $this->assertEquals(['table','addColumn','addIndex','addIndex','create'],$matches[1]);
  }

  protected function _assertTrimmedRowAtOffsetIs(int $offset, string $expected, string $actual)
  {
    $rows=explode("\n",$actual);
    $row=isset($rows[$offset])?trim($rows[$offset]):NULL;
    $this->assertEquals($expected,$row);
  }

  /**
   * Make sure primary keys (or lack thereof) result in correctly generated code
   */
  public function testPrimaryKey()
  {
    $table=new Model\Table('nokey',[],NULL,[]);
    $code=MigrationCodeGenerator::generateTableCode($table);
    $this->_assertTrimmedRowAtOffsetIs(0,"\$this->table('nokey',['id'=>false])",$code);

    $index=new Model\Index('idx1',false,['c1']);
    $unique=new Model\Index('idx2',true,['c4','c3']);
    $simple_key=new Model\Index('PRIMARY',true,['my_id']);
    $table=new Model\Table('simplekey',[],$simple_key,[$index,$unique]);
    $code=MigrationCodeGenerator::generateTableCode($table);
    $this->_assertTrimmedRowAtOffsetIs(0,"\$this->table('simplekey',['id'=>false,'primary_key'=>['my_id']])",$code);

    $unique->name='PRIMARY';
    $table=new Model\Table('compoundkey',[],$unique);
    $code=MigrationCodeGenerator::generateTableCode($table);
    $this->_assertTrimmedRowAtOffsetIs(0,"\$this->table('compoundkey',['id'=>false,'primary_key'=>['c4','c3']])",$code);
  }

  /**
   * Make sure table comments are included in generated code
   */
  public function testTableComment()
  {
    $table=new Model\Table('cmt',[],NULL,[],NULL,'my comment');
    $code=MigrationCodeGenerator::generateTableCode($table);
    $this->_assertTrimmedRowAtOffsetIs(0,"\$this->table('cmt',['id'=>false,'comment'=>'my comment'])",$code);
  }

  /**
   * Make sure table code comments are generated
   */
  public function testTableCodeComment()
  {
    $table=new Model\Table('codecomment',[]);
    $comment='some comment';
    $code=MigrationCodeGenerator::generateTableCode($table);
    $this->assertNotContains($comment,$code);
    $table->codeComment=$comment;
    $code=MigrationCodeGenerator::generateTableCode($table);
    $lines=explode("\n",$code);
    $this->assertTrue((bool)preg_match('#//'.$comment.'$#',$lines[0]),'code comment should be at end of first line');
  }

  /**
   * Make sure a table's explicit storage engine is included in generated code
   */
  public function testTableEngine()
  {
    foreach(['CSV','InnoDB','MyISAM'] as $engine)
    {
      $table=new Model\Table('eng',[],NULL,[],$engine);
      $code=MigrationCodeGenerator::generateTableCode($table);
      $this->_assertTrimmedRowAtOffsetIs(0,"\$this->table('eng',['id'=>false,'engine'=>'$engine'])",$code);
    }
  }

  protected function _silentEval($code)
  {
    //@codingStandardsIgnoreLine //suppress eval() warning
    return eval($code);
  }

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
    $options=$this->_assertAddColumnAndReturnOptions($expected_name,$expected_type,MigrationCodeGenerator::generateAddColumnCode($column));
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

      $code=MigrationCodeGenerator::generateAddColumnCode($column);
      $has_column_comment=strpos($code,"'comment'=>'$name column comment'")!==false;
      $this->assertEquals($with_column_comment,$has_column_comment,$name.': column comment existence should match');
      $matches=[];
      $has_code_comment=preg_match('#//(.*)$#',$code,$matches);
      $this->assertEquals($with_code_comment,$has_code_comment,$name.': code comment existence should match');
      if($has_code_comment)
        $this->assertEquals($name.' code comment',$matches[1],'code comment contents');
    }
  }

  protected function _runAddIndexTest(Model\Index $index, string $expected_name, bool $expected_unique, array $expected_columns, $expected_limit='')
  {
    $matches=[];
    $code=MigrationCodeGenerator::generateAddIndexCode($index);
    //                                  ->addIndex(['user_login'],['unique'=>false,'name'=>'user'])
    $this->assertEquals(1,preg_match("/^->addIndex\((\[[^\[]+\]),\[('unique'=>(true|false),)?('limit'=>([0-9]+),)?'name'=>'([^']+)'\]\)$/",$code,$matches));
    $columns=$this->_silentEval('return '.$matches[1].';');
    $this->assertSame($expected_columns,$columns,'index columns');
    $this->assertEquals($expected_unique?'true':'false',$matches[3],'index uniqueness');
    $this->assertEquals($expected_limit,$matches[5],'index limit');
    $this->assertEquals($expected_name,$matches[6],'index name');
  }

  /**
   * Make sure indices are created correctly
   */
  public function testAddIndex()
  {
    //       name,     uniq, columns,    subparts,subparts_text
    $cases=[['regular',false,['c1','c2'],[],      NULL],
            ['unique', true, ['c3'],     [NULL],  NULL],
            ['subpart',true, ['c3','c1'],[NULL,5],5],
           ];

    foreach($cases as $case)
    {
      list($name,$unique,$columns,$subparts,$subparts_text)=$case;
      $index=new Model\Index($name,$unique,$columns,$subparts);
      $this->_runAddIndexTest($index,$name,$unique,$columns,$subparts_text);
    }
  }

  /**
   * Make sure the general Phinx constraints on compound index subparts are enforced
   * Phinx requires that if the index spans multiple columns only the last column may have a subpart
   */
  public function testAddIndexBlocksUnsupportedCompoundSubparts()
  {
    //       name,     uniq, columns,    subparts,reason
    $cases=[['notlast',false,['c1','c2'],[2,NULL],'if subpart is set, it must be last'],
            ['multi',  true, ['c3','c1'],[5,1],   'one subpart column support at most'],
           ];

    foreach($cases as $case)
    {
      list($name,$unique,$columns,$subparts,$reason)=$case;
      $index=new Model\Index($name,$unique,$columns,$subparts);
      try
      {
        $code=MigrationCodeGenerator::generateAddIndexCode($index);
        $this->fail('should have failed ('.$reason.')');
      }
      catch(UnsupportedSchemaException $e)
      {
        $this->assertTrue(true);
      }
    }
  }


  /**
   * Make sure migration classes are generated correctly
   */
  public function testClassGeneration()
  {
    //       name,    code blocks,              comment lines,              allow empty?
    $cases=[['empty1',[],                       [],                         false],
            ['empty2',[],                       [],                         true],
            ['filled',['//code1xx','//code2xy'],['comment1xz','comment2ya'],false]];
    $classname_prefix='TmpMigration'.date('YmdHis').'_';
    foreach($cases as $ti=>$case)
    {
      list($casename,$code_blocks,$comment_lines,$allow_empty)=$case;
      MigrationCodeGenerator::$allowEmptyMigration=$allow_empty;
      $classname=$classname_prefix.$ti;
      $classname_ns=$classname;
      if(class_exists($classname_ns))
        $this->fail('internal error: class "'.$classname_ns.'" already exists');
      $code=MigrationCodeGenerator::generateClassCode($classname,$code_blocks,$comment_lines);
      if(!$code_blocks && !$allow_empty)
      {
        $this->assertEmpty($code,'migration code should be empty');
        continue;
      }

      $this->assertStringStartsWith("<?php\n",$code);
      foreach($code_blocks as $block)
        $this->assertContains($block,$code,'testcase '.$casename);
      foreach($comment_lines as $line)
        $this->assertContains($line,$code,'testcase '.$casename);
      $this->_silentEval(substr($code,5));
      $this->assertTrue(class_exists($classname_ns),'testcase '.$casename.': class "'.$classname_ns.'" should exist after eval()');
    }
  }
}
