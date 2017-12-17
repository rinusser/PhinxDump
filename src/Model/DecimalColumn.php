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
 * Model for DECIMAL columns
 */
class DecimalColumn extends AbstractColumn
{
  protected $_precision;
  protected $_scale;
  protected $_unsigned;

  /**
   * Decimal model constructor.
   * Keep in mind that $precision must be >= $scale. If you e.g. set $precision=7 and $scale=3, you'll get 4 digits to the left of, and 3 digits to the right
   * of, the decimal point - allowing you to store values from -9999.999 (or 0 if unsigned) to +9999.999 in this column.
   *
   * @param string      $name      see parent
   * @param string|NULL $default   see parent
   * @param bool        $nullable  see parent
   * @param int         $precision the number of significant digits to store
   * @param int         $scale     the number of decimal digits
   * @param bool        $unsigned  whether to allow (false) or disallow (true) negative values
   */
  public function __construct(string $name, ?string $default, bool $nullable, int $precision, int $scale, bool $unsigned)
  {
    parent::__construct($name,$default,$nullable);
    $this->_precision=$precision;
    $this->_scale=$scale;
    $this->_unsigned=$unsigned;
  }
}
