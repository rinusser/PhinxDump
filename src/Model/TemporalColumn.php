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
 * Model for temporal column types (DATE, TIME, DATETIME, TIMESTAMP)
 */
class TemporalColumn extends AbstractColumn
{
  public const TYPE_DATE='date';
  public const TYPE_TIME='time';
  public const TYPE_DATETIME='datetime';
  public const TYPE_TIMESTAMP='timestamp';


  protected $_type;
  protected $_onUpdateCurrentTimestamp;


  /**
   * Model constructor
   *
   * @param string      $name             see parent
   * @param string|NULL $default          see parent
   * @param bool        $nullable         see parent
   * @param string      $type             one of this class's TYPE_* constants indicating the type of column
   * @param bool        $update_timestamp (only for TYPE_TIMESTAMP) whether to add "on update CURRENT_TIMESTAMP" (keep MySQL restrictions in mind)
   */
  public function __construct(string $name, ?string $default, bool $nullable, string $type, bool $update_timestamp)
  {
    parent::__construct($name,$default,$nullable);
    $this->_type=$type;
    if($update_timestamp && $type!==self::TYPE_TIMESTAMP)
      throw new \InvalidArgumentException('on update CURRENT_TIMESTAMP is only valid for timestamp columns, got type "'.$type.'" instead');
    $this->_onUpdateCurrentTimestamp=$update_timestamp;
  }
}
