<?php
declare(strict_types=1);
/**
 * Bootstrap tests
 *
 * Requires PHP Version 7.0+.
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 * @link https://github.com/rinusser/PhinxDump
 */

namespace RN\PhinxDump\Tests;

require_once(__DIR__.'/../src/init.php');

spl_autoload_register(function($class) {
  $app_namespace='RN\\PhinxDump\\Tests';
  $base_directory=__DIR__.'/';
  $prefix_length=strlen($app_namespace);
  if(substr($class,0,$prefix_length)!=$app_namespace)
    return;
  $file=$base_directory.str_replace('\\','/',substr($class,$prefix_length)).'.php';
  if(file_exists($file))
    require_once($file);
});
