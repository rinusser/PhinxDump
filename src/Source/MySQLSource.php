<?php
declare(strict_types=1);
/**
 * Requires PHP version 7.0+
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 */

namespace RN\PhinxDump\Source;

use PDO;
use RN\PhinxDump\Configuration;

/**
 * Data source for MySQL databases
 * Reads data from information_schema tables. Currently uses username/password authentication only, doesn't support MySQL's auth sockets.
 */
class MySQLSource extends AbstractSource
{
  private $_pdo;

  /**
   * Constructor for the MySQL data source.
   * Authentication data is not persisted on purpose: it's unavailable after connecting the PDO.
   *
   * @param Configuration $config   the configuration data to use
   * @param string        $username the username to authenticate with
   * @param string        $password the password to authenticate with
   */
  public function __construct(Configuration $config, string $username, string $password)
  {
    parent::__construct($config);
    $this->_pdo=$this->_createPDOConnection($config,$username,$password);
  }

  /**
   * Reads table data from information_schema
   *
   * @return array table data
   */
  public function fetchTableData(): array
  {
    return $this->_pdo->query('SELECT table_name,table_type,table_comment
                               FROM information_schema.tables
                               WHERE table_schema='.$this->_pdo->quote($this->_config->database))->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Reads column data from information_schema
   *
   * @param string $table the table to read column data from
   * @return array column data rows
   */
  public function fetchColumnDataForTable(string $table): array
  {
    return $this->_pdo->query('SELECT column_name,column_default,is_nullable,data_type,character_maximum_length,numeric_precision,
                                      numeric_scale,column_type,extra
                               FROM information_schema.columns
                               WHERE table_schema='.$this->_pdo->quote($this->_config->database).' AND table_name='.$this->_pdo->quote($table).'
                               ORDER BY ordinal_position')->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Reads index data from information_schema
   *
   * @param string $table the table to read index data from
   * @return array index data rows
   */
  public function fetchIndexDataForTable(string $table): array
  {
    return $this->_pdo->query('SELECT index_name,non_unique,seq_in_index,column_name,sub_part
                               FROM information_schema.statistics
                               WHERE table_schema='.$this->_pdo->quote($this->_config->database).' AND table_name='.$this->_pdo->quote($table).'
                               ORDER BY index_name,seq_in_index')->fetchAll(PDO::FETCH_ASSOC);
  }

  private function _createPDOConnection(Configuration $config, string $username, string $password): PDO
  {
    $dsn='mysql:host='.$config->hostname.';dbname='.$config->database;
    $options=[PDO::MYSQL_ATTR_INIT_COMMAND=>'SET NAMES utf8'];
    $pdo=new PDO($dsn,$username,$password,$options);
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    return $pdo;
  }
}
