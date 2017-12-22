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

use RN\PhinxDump\MigrationCodeGenerator;
use RN\PhinxDump\Model;
use RN\PhinxDump\UnsupportedSchemaException;

/**
 * Tests for generating migration code
 */
class MigrationCodeGeneratorTest extends CodeGeneratorTestCase
{
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
    $table=new Model\Table('cmt',[],NULL,[],NULL,NULL,'my comment');
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

  /**
   * Make sure a table's explicit collation is included in generated code
   */
  public function testTableCollation()
  {
    foreach(['utf8_unicode_ci','latin1_general_cs'] as $collation)
    {
      $table=new Model\Table('coll',[],NULL,[],NULL,$collation);
      $code=MigrationCodeGenerator::generateTableCode($table);
      $this->_assertTrimmedRowAtOffsetIs(0,"\$this->table('coll',['id'=>false,'collation'=>'$collation'])",$code);
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
        MigrationCodeGenerator::generateAddIndexCode($index);
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
