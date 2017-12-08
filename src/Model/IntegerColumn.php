<?php
declare(strict_types=1);
/**
 * Requires PHP version 7.1+
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 */

namespace RN\PhinxDump\Model;

/**
 * Model for TINYINT, SMALLINT, INT and BIGINT columns
 */
class IntegerColumn extends AbstractColumn
{
  public const SIZE_TINY='tiny';
  public const SIZE_SMALL='small';
  public const SIZE_MEDIUM='medium';
  public const SIZE_REGULAR='regular';
  public const SIZE_BIG='big';


  protected $_unsigned;
  protected $_size;
  protected $_autoIncrement;


  /**
   * Model constructor
   *
   * @param string      $name           see parent
   * @param string|NULL $default        see parent
   * @param bool        $nullable       see parent
   * @param bool        $unsigned       whether to allow (false) or disallow (true) negative values
   * @param string      $size           one of this class's SIZE_* constants indicating the column's storage size
   * @param bool        $auto_increment whether this column should be auto-incremented (mind the MySQL limitations on this, it's usually just the primary key)
   */
  public function __construct(string $name, ?string $default, bool $nullable, bool $unsigned, string $size, bool $auto_increment=false)
  {
    parent::__construct($name,$default,$nullable);
    $this->_unsigned=$unsigned;
    $this->_size=$size;
    $this->_autoIncrement=$auto_increment;
  }
}
