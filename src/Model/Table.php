<?php
declare(strict_types=1);
/**
 * Requires PHP version 7.1+
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 * @link https://github.com/rinusser/PhinxDump
 */

namespace RN\PhinxDump\Model;

/**
 * Table model. Holds all the information required to recreate a database table.
 */
class Table extends AbstractModel
{
  protected $_name;
  protected $_columns;
  protected $_primaryKey;
  protected $_indices;
  protected $_engine;
  protected $_collation;
  protected $_comment;
  protected $_codeComment;


  /**
   * Constructor for Table model
   *
   * @param string      $name        the table name
   * @param array       $columns     (optional) the table's columns - keep in mind MySQL requires at least 1 column per table
   * @param Index|NULL  $primary_key (optional) the table's primary key, or NULL if there isn't any
   * @param array       $indices     (optional) the table's list of (non-primary-key) indices, if any
   * @param string|NULL $engine      (optional) the table's storage engine, or NULL for default
   * @param string|NULL $collation   (optional) the table's default collation, or NULL for default
   * @param string|NULL $comment     (optional) the table's comment, if any
   */
  public function __construct(string $name, array $columns=[], ?Index $primary_key=NULL, array $indices=[], ?string $engine=NULL,
                              ?string $collation=NULL, ?string $comment=NULL)
  {
    $this->_name=$name;
    $this->_columns=$columns;
    $this->_primaryKey=$primary_key;
    $this->_indices=$indices;
    $this->_engine=$engine;
    $this->_collation=$collation;
    $this->_comment=$comment;
  }
}
