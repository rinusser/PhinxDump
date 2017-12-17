<?php
declare(strict_types=1);
/**
 * Requires PHP version 7.0+
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 * @link https://github.com/rinusser/PhinxDump
 */

namespace RN\PhinxDump\Model;

/**
 * Index model; holds information about one index (even if it spans multiple columns).
 */
class Index extends AbstractModel
{
  protected $_name;
  protected $_unique;
  protected $_columns;
  protected $_columnSubparts;


  /**
   * Constructor for index model
   *
   * For [VAR]CHAR columns indexing subparts is supported. Use NULL values to indicate the entire column should be used. For example:
   *
   *   $column=['col1','col2'],
   *   $column_subparts=[NULL,5]
   *
   * results in this index specification:
   *
   *   col1,col2(5)
   *
   * @param string $name            the index's name
   * @param bool   $unique          whether the index should enforce unique values (true, i.e. UNIQUE) or not (false, i.e. INDEX)
   * @param array  $columns         the list of column names to index
   * @param array  $column_subparts (optional) the list of column subparts - must either be empty or have count($column) entries
   */
  public function __construct(string $name, bool $unique, array $columns, array $column_subparts=[])
  {
    $this->_name=$name;
    $this->_unique=$unique;
    $this->_columns=$columns;
    $this->_columnSubparts=$column_subparts;
  }
}
