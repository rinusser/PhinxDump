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
 * Base class for column models.
 * Contains fields common to every column type.
 */
abstract class AbstractColumn extends AbstractModel
{
  protected $_name;
  protected $_default;
  protected $_nullable;
  protected $_comment;
  protected $_codeComment;


  /**
   * The basic constructor for all column models.
   *
   * @param string      $name     the column's name
   * @param string|NULL $default  the default value, of any
   * @param bool        $nullable whether this column should allow NULL values
   * @param string|NULL $comment  the column's comment text, if any
   */
  public function __construct(string $name, ?string $default, bool $nullable, ?string $comment=NULL)
  {
    $this->_name=$name;
    $this->_default=$default;
    $this->_nullable=$nullable;
    $this->_comment=$comment;
  }
}
