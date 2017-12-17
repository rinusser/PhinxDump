<?php
declare(strict_types=1);
/**
 * Requires PHP version 7.0+
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 * @link https://github.com/rinusser/PhinxDump
 */

namespace RN\PhinxDump\Source;

use RN\PhinxDump\Model;
use RN\PhinxDump\Configuration;

/**
 * Base class for schema data sources
 *
 * Although data sources can read whatever data they wish, the internal representation consists of information_schema table entries
 */
abstract class AbstractSource extends Model\AbstractModel
{
  protected $_config;

  /**
   * Base constructor for data sources
   *
   * @param Configuration $config the configuration settings to use
   */
  public function __construct(Configuration $config)
  {
    $this->_config=$config;
  }

  /**
   * This method should return data of all tables in the configured schema
   *
   * @return array list of information_schema.TABLES entries
   */
  abstract public function fetchTableData(): array;

  /**
   * This method should return column data for the configured table
   *
   * @param string $table the table name to get data for
   * @return array list of information_schema.COLUMNS entries
   */
  abstract public function fetchColumnDataForTable(string $table): array;

  /**
   * This method should return index data for the configured table
   *
   * @param string $table the table name to get data for
   * @return array list of information_schema.STATISTICS entries
   */
  abstract public function fetchIndexDataForTable(string $table): array;
}
