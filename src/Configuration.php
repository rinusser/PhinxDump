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
 * This class holds the configuration data to dump a database schema.
 * Note that authentication data (user/password, auth sockets etc.) intentionally is omitted here.
 */
class Configuration extends Model\AbstractModel
{
  protected $_hostname;
  protected $_database;
  protected $_skipTables;
  protected $_datadir;


  /**
   * Constructor for Configuration class
   *
   * @param string $hostname    the hostname to connect to
   * @param string $database    the schema/database name to dump
   * @param array  $skip_tables (optional) the list of tables to skip - if set, make sure to include your configured phinxlog table
   * @param string $datadir     (optional) the data directory to write migration files to
   */
  public function __construct(string $hostname, string $database, array $skip_tables=['phinxlog'], string $datadir='/data')
  {
    $this->_hostname=$hostname;
    $this->_database=$database;
    $this->_skipTables=$skip_tables;
    $this->_datadir=rtrim($datadir,'/').'/';
  }
}
