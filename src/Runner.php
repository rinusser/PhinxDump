<?php
declare(strict_types=1);
/**
 * Requires PHP version 7.0+.
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 */

namespace RN\PhinxDump;

use RN\PhinxDump\Source\AbstractSource;

/**
 * Controller for application.
 * Reads data from source, parses data into model, turns model into code and writes code to file.
 */
class Runner extends Model\AbstractModel
{
  protected $_config;
  protected $_source;
  protected $_startTime;

  /**
   * Constructor for Runner class
   *
   * @param Configuration  $config the configuration data to use
   * @param AbstractSource $source the data source to read table/column/index data from
   */
  public function __construct(Configuration $config, AbstractSource $source)
  {
    $this->_config=$config;
    $this->_source=$source;
    $this->_startTime=NULL;
  }

  /**
   * Main worker method, handles entire dump process
   *
   * @return NULL
   */
  public function run(): void
  {
    $this->_startTime=new \DateTime('now');
    $code_blocks=[];
    foreach($this->_source->fetchTableData() as $table_row)
      $code_blocks[]=$this->_handleTable($table_row);

    $name_parts=$this->_getMigrationNameParts();
    $classname=implode('',$name_parts);
    $filename=$this->_startTime->format('YmdHis').'_'.strtolower(implode('_',$name_parts)).'.php';

    $file_contents=$this->_assembleClassCode($classname,$code_blocks);
    if($file_contents)
      $this->_attemptFileWrite($filename,$file_contents);
  }


  protected function _attemptFileWrite(string $filename, string $file_contents): void
  {
    if(file_put_contents($this->_config->datadir.$filename,$file_contents))
      echo "created $filename\n";
    else
      echo "failed writing $filename\n";
  }

  protected function _handleTable(array $table_row): string
  {
    $table=$table_row['table_name'];
    if(in_array($table,$this->_config->skipTables))
      return '//table "'.$table.'" skipped by configuration';
    if($table_row['table_type']!=='BASE TABLE')
    {
      Logger::getInstance()->warn("skipping object '$table': type '$table_row[table_type]' unsupported");
      return '//object "'.$table.'" skipped: type "'.$table_row['table_type'].'" unsupported';
    }
    $column_rows=$this->_source->fetchColumnDataForTable($table);
    $index_rows=$this->_source->fetchIndexDataForTable($table);
    $table_model=InformationSchemaParser::parse($table_row,$column_rows,$index_rows);
    return MigrationCodeGenerator::generateTableCode($table_model);
  }

  protected function _getMigrationNameParts(): array
  {
    $parts=['Reverse','Engineered'];
    foreach(explode('_',$this->_config->database) as $part)
      $parts[]=ucfirst($part);
    return $parts;
  }

  protected function _assembleClassCode(string $classname, array $code_blocks): string
  {
    $comment_lines=['Hostname:    '.$this->_config->hostname,
                    'Database:    '.$this->_config->database,
                    'Started at:  '.$this->_startTime->format('Y-m-d H:i:s T')];
    return MigrationCodeGenerator::generateClassCode($classname,$code_blocks,$comment_lines);
  }
}
