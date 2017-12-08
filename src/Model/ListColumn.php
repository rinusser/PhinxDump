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
 * Model for SET and ENUM columns
 */
class ListColumn extends AbstractColumn
{
  protected $_multiple;
  protected $_values;


  /**
   * Model constructor
   *
   * @param string      $name     see parent
   * @param string|NULL $default  see parent
   * @param bool        $nullable see parent
   * @param bool        $multiple whether to allow multiple values at the same time (i.e. SET) or not (i.e. ENUM)
   * @param array       $values   the list of allowed values (mind MySQL restrictions on this, e.g. don't use commas in values)
   */
  public function __construct(string $name, ?string $default, bool $nullable, bool $multiple, array $values)
  {
    parent::__construct($name,$default,$nullable);
    $this->_multiple=$multiple;
    $this->_values=$values;
  }
}
