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
 * Model for FLOAT and DOUBLE columns
 * Note that DOUBLE columns currently aren't supported by Phinx
 */
class FloatColumn extends AbstractColumn
{
  public const PRECISION_DOUBLE='double';
  public const PRECISION_SINGLE='single';


  protected $_precision;


  /**
   * Model constructor for floating point columns
   *
   * @param string      $name      see parent
   * @param string|NULL $default   see parent
   * @param bool        $nullable  see parent
   * @param bool        $precision whether to use double precision (currently unsupported by Phinx anyway)
   */
  public function __construct(string $name, ?string $default, bool $nullable, string $precision)
  {
    parent::__construct($name,$default,$nullable);
    $this->_precision=$precision;
  }
}
