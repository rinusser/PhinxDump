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
 * Base class for models, provides safeguards against accessing invalid property names.
 * Properties are expected to be declared as protected and start with an underscore. Values can be accessed publicly without the leading underscore.
 */
abstract class AbstractModel
{
  private function _throwFit(string $field)
  {
    throw new \InvalidArgumentException('field '.get_called_class().'->'.$field.' does not exist');
  }

  private function _getInternalFieldName(string $field): string
  {
    $data=get_object_vars($this);
    $key='_'.$field;
    if(array_key_exists($key,$data))
      return $key;
    $this->_throwFit($field);
  }

  /**
   * Magic getter method, invoked when reading from $model->property
   * Forwards read access to $this->_property
   *
   * @param string $field the property name that was accessed
   * @return mixed the property's value
   * @throws \InvalidArgumentException if property name is invalid
   */
  public function __get(string $field)
  {
    return $this->{$this->_getInternalFieldName($field)};
  }

  /**
   * Magic setter method, invoked when writing to $model->property
   * Forwards write access to $this->_property
   *
   * @param string $field the property name that was accessed
   * @param mixed  $value the value to set
   * @return mixed the passed value
   * @throws \InvalidArgumentException if property name is invalid
   */
  public function __set(string $field, $value)
  {
    $this->{$this->_getInternalFieldName($field)}=$value;
    return $value;
  }
}
