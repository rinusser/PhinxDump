<?php
declare(strict_types=1);
/**
 * Requires PHP version 7.0+.
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 */

namespace RN\PhinxDump;

/**
 * Command-line interface to application.
 * Turns command line arguments into internal configuration and starts runner.
 */
class CLI
{
  private $_defaultServer='127.0.0.1';
  private $_defaultUsername;


  /**
   * The CLI constructor, initializes required values
   */
  public function __construct()
  {
    $this->_defaultUsername=empty($_SERVER['HOME'])?'root':basename($_SERVER['HOME']);
  }


  private function _getPassword(): string
  {
    $prev_stty=exec('stty -g');
    exec('stty -echo');
    echo 'Password: ';
    $password=trim(fgets(STDIN));
    echo "\n";
    exec('stty '.$prev_stty);
    return $password;
  }

  private function _getArgumentOrDefault(array &$arguments, string $switch, string $default): string
  {
    foreach($arguments as $tk=>$tv)
    {
      if($tv==$switch && isset($arguments[$tk+1]))
      {
        $value=$arguments[$tk+1];
        unset($arguments[$tk]);
        unset($arguments[$tk+1]);
        return $value;
      }
    }
    return $default;
  }

  private function _parseBooleanParameter(string $value)
  {
    switch(strtolower($value))
    {
      case 'yes':
      case 'true':
      case 'on':
      case '1':
        return true;
      case 'no':
      case 'false':
      case 'off':
      case '0':
        return false;
    }
    return $value;
  }

  /**
   * Parses command line arguments, fetches whatever else is required and instantiates/starts the runner
   *
   * @return int the return value for the calling shell environment
   */
  public function handle(): int
  {
    $args=empty($_SERVER['argv'])?[]:array_slice($_SERVER['argv'],1);
    $server=$this->_getArgumentOrDefault($args,'-h',$this->_defaultServer);
    $username=$this->_getArgumentOrDefault($args,'-u',$this->_defaultUsername);
    $allow_double_fallback=$this->_getArgumentOrDefault($args,'--allow-double-fallback','no');
    $allow_double_fallback=$this->_parseBooleanParameter($allow_double_fallback);

    $allow_empty_migration=$this->_parseBooleanParameter($this->_getArgumentOrDefault($args,'--allow-empty-migration','no'));

    if(count($args)!=1 || !is_bool($allow_double_fallback) || !is_bool($allow_empty_migration))
    {
      echo "Syntax: ",$_SERVER['argv'][0]," [-h <hostname>] [-u <username>] [--allow-double-fallback <yes|no>] [--allow-empty-migration <yes|no>] database\n";
      return 1;
    }
    $database=array_pop($args);

    echo "Server:   ",$server,"\n";
    echo "Database: ",$database,"\n";
    echo "Username: ",$username,"\n";
    $password=$this->_getPassword();
    echo "\n";

    $config=new Configuration($server,$database);
    try
    {
      $source=new Source\MySQLSource($config,$username,$password);
    }
    catch(\PDOException $e)
    {
      Logger::getInstance()->error($e->getMessage());
      return 2;
    }
    $runner=new Runner($config,$source);
    MigrationCodeGenerator::$allowDoubleFallback=$allow_double_fallback;
    MigrationCodeGenerator::$allowEmptyMigration=$allow_empty_migration;

    try
    {
      $runner->run();
      return 0;
    }
    catch(UnsupportedSchemaException $e)
    {
      Logger::getInstance()->error($e->getMessage());
      return 50;
    }
  }
}
