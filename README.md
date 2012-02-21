# Minify! #

## Description ##
  Minify optimize, compress and combine your css and javascript files on the fly!

  Even though Minify is called every request you won't notice it. The script in demo/ takes about 0.009 seconds to verify and check 26 different files with a memory usage of 1.248MB (and remember that this is with 26 files!).

## Usage ##
	<?php
	Minify::add('https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.js');
	Minify::add('script.js');
	Minify::add('reset.css');
	Minify::add('design.css');
	Minify::run();
  Minify::printLinks();
	?>

## Know Issues ##
* Minify won't recompress when options are changed.
* If compressCode is false Minify will still use a file's compressed code if cached (and vise versa).
* Under rare circumstances Minify may fail to recompress (fixed?).
* Changing useLocalJS to true will crash Minify (due to localJS not being implemented yet).

## Features and Ideas ##
* HTML compression.
  * This would require Minify to catch and supress any output by PHP and compress it during shutdown.
  * A more basic implementation would be to pass any HTML code to a method within Minify. 
* Implement CLI-based JS compression.
  * Options switch currently implemented as useLocalJS (see known issues).
* Unit Testing
  * The current demo is the only way to test if Minify actually works after code changes (except for a few sites hooked up directly to the trunk).
  * I'd be able to automatically try all different kinds of circumstances where a recompress should happen.
    * File change (obviously).
    * Missing minified file.
    * Truncated checksum file.
    * Missing checksum file.
    * Changed options.
    * Missing files.
    * Added files.
* Refactor Code
  * Lots of methods do more stuff than they should.
  * evaluate's cyclomatic complexity is way above normal.
* Toggle file combination.
  * combineFiles switch to toggle if all code should be combined into a single file or if they should be separated.