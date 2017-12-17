<?php
declare(strict_types=1);
/**
 * Application logger. Not PSR-3 compliant, but very similar.
 *
 * Requires PHP version 7.0+
 * @author Richard Nusser
 * @copyright 2017 Richard Nusser
 * @license GPLv3 (see http://www.gnu.org/licenses/)
 * @link https://github.com/rinusser/PhinxDump
 */

namespace RN\PhinxDump;

/**
 * This is the application's logging facility. Do not instantiate it directly, call ::getInstance() instead.
 */
class Logger
{
  public const ERROR='error';
  public const WARNING='warning';


  public static $outputEnabled=true;
  public static $colorsEnabled=true;

  private static $_colorMapping=[self::ERROR=>196,
                                 self::WARNING=>202];
  private static $_instance;


  /**
   * Singleton pattern: get your Logger instance here.
   *
   * @return Logger
   */
  public static function getInstance(): Logger
  {
    if(!self::$_instance)
      self::$_instance=new self();
    return self::$_instance;
  }

  private function __construct()
  {
  }

  /**
   * Log error: anything that stops the application from working
   *
   * @param string $message the log message
   * @param array  $context (optional) the log context
   * @return NULL see log()
   */
  public function error(string $message, array $context=[])
  {
    return $this->log(self::ERROR,$message,$context);
  }

  /**
   * Log warning: anything that might have an effect or might not, usually requires looking into
   *
   * @param string $message the log message
   * @param array  $context (optional) the log context
   * @return NULL see log()
   */
  public function warn(string $message, array $context=[])
  {
    return $this->log(self::WARNING,$message,$context);
  }

  /**
   * General logging function
   *
   * @param string $level   one of the class constants
   * @param string $message the log message
   * @param array  $context (unused) (optional) the log context
   * @return NULL don't rely on this, other implementations might return something
   */
  public function log(string $level, string $message, array $context=[])
  {
    if(!self::$outputEnabled)
      return;

    if(self::$colorsEnabled)
      echo "\033[38;5;",self::$_colorMapping[$level],'m';

    echo '[',strtoupper($level),'] ',$message,"\n";

    if(self::$colorsEnabled)
      echo "\033[0m";
  }
}
