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
 * Model for CHAR and VARCHAR columns
 */
class CharColumn extends AbstractColumn
{
  protected $_variable;
  protected $_length;


  /**
   * The constructor for character column types
   * Keep in mind that for large texts (>64k bytes) there's TEXT columns instead
   *
   * @param string      $name     see parent
   * @param string|NULL $default  see parent
   * @param bool        $nullable see parent
   * @param bool        $variable whether this column contains variable length content (VARCHAR) or not (CHAR)
   * @param int         $length   the maximum number of characters this column can hold
   */
  public function __construct(string $name, ?string $default, bool $nullable, bool $variable, int $length)
  {
    parent::__construct($name,$default,$nullable);
    $this->_variable=$variable;
    $this->_length=$length;
  }
}
