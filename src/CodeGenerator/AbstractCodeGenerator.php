<?php
declare(strict_types=1);
/**
 * Requires PHP version 7.0+.
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 * @link https://github.com/rinusser/PhinxDump
 */

namespace RN\PhinxDump\CodeGenerator;

/**
 * Base class for PHP code generators.
 */
abstract class AbstractCodeGenerator
{
  protected static function _findValueByKey(string $key, array $values): string
  {
    foreach($values as $tk=>$tv)
      if($tk==$key)
        return $tv;
    throw new \LogicException('no value found for key "'.$key.'"');
  }

  protected static function _generateArray(array $array, bool $with_keys=false, bool $escape_values=true): string
  {
    $rvs=[];
    foreach($array as $tk=>$tv)
    {
      $entry=$with_keys?trim(var_export($tk,true)).'=>':'';
      $entry.=$escape_values?trim(var_export($tv,true)):$tv;
      $rvs[]=$entry;
    }
    return '['.implode(',',$rvs).']';
  }
}
