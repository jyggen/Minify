<?php
/**
 * @package    Minify
 * @author     Jonas Stendahl
 * @license    MIT License
 * @copyright  2012 Jonas Stendahl
 * @link       http://www.jyggen.com
 */

// Include required files.
require_once 'classes/Minify.php';
require_once 'classes/File.php';
require_once 'classes/Cache.php';

// Setup autoloader.
try { spl_autoload_register('Minify\\Minify::autoloader', true, true); }
catch(LogicException $e) { die($e->getMessage()."\n"); }