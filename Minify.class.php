<?php
/**
 * Minify Class by Jonas Stendahl
 * http://www.jyggen.com/
 *
 * CSS Compressor by Corey Hart
 * http://www.codenothing.com/
 *
 * Closure Compiler by Google
 * http://closure-compiler.appspot.com/
 */

class MinifyException extends Exception {}

class Minify
{

	static protected $_opt           = array();
	static protected $_files         = array();
	static protected $_downloadQueue = array();
	static protected $_debugLog      = array();
	static protected $_cacheDir;
	static protected $_cssMode;
	static protected $_jsMode;
	static protected $_mincode;
	static protected $_outputDir;
	static protected $_publicDir;
	static protected $_benchmark;
	static protected $_memory;
	
	/**
	 * Load a configurations file.
	 *
	 * @param	string	path to config file
	 * @return	void
	 */
	static public function loadConfig($path)
	{

		$path = __DIR__.'/'.$path;

		if (file_exists($path) === false) {

			$msg = 'Couldn\'t load configuration file from %s.';
			$msg = sprintf($msg, $path);

			throw new Exception($msg);

		} else {

			include_once $path;
			self::$_opt = array_merge(self::$_opt, $options);

		}

	}

	/**
	 * Set an options value.
	 *
	 * @param	string	options key
	 * @param	string	options value
	 * @return	void
	 */
	static public function set($key, $value)
	{

		self::$_opt[$key] = $value;

	}
	
	/**
	 * Add file(s) to be minified.
	 *
	 * @param	mixed	URL or file path to file(s) to minified
	 * @return	void
	 */
	static public function add($files)
	{

		if (is_array($files) === true) {

			foreach ($files as $file) {

				self::add($file);

			}

		} else {

			self::$_files[]['path'] = $files;

		}

	}

	/**
	 * Run Minify!
	 *
	 * @return	void
	 */
	static public function run()
	{

		self::$_benchmark = microtime(true);
		self::$_memory    = memory_get_peak_usage();

		self::loadDefaultOpts();
		self::validateOutputDir();
		self::validateCacheDir();
		self::validatePublicDir();
		self::validateFiles();
		self::includeClasses();

		if (empty(self::$_downloadQueue) === false) {

			self::downloadFiles();

		}

		self::detectMode();

		if (self::evaluate() === false) {

			self::compressFiles();
			self::saveFiles();
			self::saveCacheFile();

		}

		$exec = round((microtime(true)-self::$_benchmark), 3);
		$mem  = round(((memory_get_peak_usage() - self::$_memory) / 1048576), 3);

		self::log(PHP_EOL.'Executed in '.$exec.' seconds using '.$mem.'MB memory.');

	}

	/**
	 * Generate HTML tag(s) to include the minified file(s).
	 *
	 * @return	string
	 */
	static public function getLinks($which='both')
	{

		$links = '';

		if (($which == 'both' || $which == 'js') && self::$_jsMode === true) {

			if (self::$_opt['publicDir'] !== null) {

				$file = self::$_opt['publicDir'].self::$_opt['minifyFile'].'.js';

			} else {

				$file = self::$_outputDir.self::$_opt['minifyFile'].'.js';

			}

			if(self::$_opt['useRewrite']) {

				$ident = date('Ymd', filemtime($file));

			} else {

				$ident = hash_file(self::$_opt['algorithm'], $file);

			}

			if (self::$_opt['absolutePaths'] === true && substr($file, 0, 1) !== '/') {

				$file = '/'.$file;

			}

			if(self::$_opt['useRewrite']) {
				
				$ext  = pathinfo($file, PATHINFO_EXTENSION);
				$file = substr($file, 0, -strlen($ext));
				$file = $file.$ident.'.'.$ext;
			
			} else {
			
				$file = $file.'?'.$ident;
			
			}

			$links .= sprintf(self::$_opt['htmlJS'], $file)."\n";

		}

		if (($which == 'both' || $which == 'css') && self::$_cssMode === true) {

			if (self::$_opt['publicDir'] !== null) {

				$file = self::$_opt['publicDir'].self::$_opt['minifyFile'].'.css';

			} else {

				$file = self::$_outputDir.self::$_opt['minifyFile'].'.css';

			}

			if(self::$_opt['useRewrite']) {

				$ident = date('Ymd', filemtime($file));

			} else {

				$ident = hash_file(self::$_opt['algorithm'], $file);

			}

			if (self::$_opt['absolutePaths'] === true && substr($file, 0, 1) !== '/') {

				$file = '/'.$file;

			}
			
			if(self::$_opt['useRewrite']) {
				
				$ext  = pathinfo($file, PATHINFO_EXTENSION);
				$file = substr($file, 0, -strlen($ext));
				$file = $file.$ident.'.'.$ext;
			
			} else {
			
				$file = $file.'?'.$ident;
			
			}

			$links .= sprintf(self::$_opt['htmlCSS'], $file)."\n";

		}

		return $links;

	}
	
	/**
	 * Output HTML tag(s) to include the minified file(s).
	 *
	 * @return	void
	 */
	static public function printLinks()
	{

		echo self::getLinks();

	}
	
	/**
	 * Print debug information.
	 *
	 * @return	void
	 */
	static public function debug()
	{

		echo '<pre>';

		foreach (self::$_debugLog as $log) {

			echo $log;

		}

		echo '</pre>';

	}
	
	/**
	 * Insert a string into the debug log.
	 *
	 * @param	string	message to log
	 * @param	boolean	if a EOL character should be appended
	 * @param	integer	indent length
	 * @return	void
	 */
	static protected function log($data, $eol=true, $tab=0)
	{

		$msg = '';

		for ($i = 0; $i < $tab; $i++) {

			$msg .= "\t";

		}

		$msg .= $data;

		if ($eol === true) {

			$msg .= PHP_EOL;

		}

		self::$_debugLog[] = $msg;

	}
	
	/**
	 * Validate that a directory exists and is writable. Will try to
	 * create it otherwise.
	 *
	 * @param	string	path to directory
	 * @return boolean
	 */
	static protected function validateDir($dir)
	{

		if (is_dir($dir) === false && mkdir($dir, 0777, true) === false) {

			$msg = '"%s" is not a valid directory.';
			$msg = sprintf($msg, $dir);

			throw new MinifyException($msg);

		}

		if (is_writable($dir) === false) {

			$msg = '"%s" is not writable.';
			$msg = sprintf($msg, $dir);

			throw new MinifyException($msg);

		}

		return true;

	}
	
	/**
	 * Validate that an options key is properly set.
	 *
	 * @param	string	options key to validate
	 * @return	boolean
	 */
	static protected function validateOpt($key)
	{

		if (isset(self::$_opt[$key]) === false
			|| empty(self::$_opt[$key]) === true
		) {

			$msg = 'Missing "%s" in configuration.';
			$msg = sprintf($msg, $key);

			throw new Exception($msg);

		} else {

			return true;

		}

	}
	
	/**
	 * Return the extension of a filename.
	 *
	 * @param	string	filename
	 * @return	string
	 */
	static protected function getExt($name)
	{

		$info = pathinfo($name);

		return $info['extension'];

	}

	/**
	 * Check if the filename's extension is allowed.
	 *
	 * @param	string	filename
	 * @return	boolean
	 */
	static protected function isAllowedExt($name)
	{

		$ext = self::getExt($name);
		$ok  = (in_array($ext, self::$_opt['allowedExts']));

		return $ok;

	}

	/**
	 * Merge the default options with any user changes.
	 *
	 * @return	void
	 */
	static protected function loadDefaultOpts()
	{

		$defaultOpts = array(
						'algorithm'     => 'crc32b',
						'cacheFile'     => 'minify.sfv',
						'cacheDir'      => __DIR__.'/minify/cache/',
						'outputDir'     => 'assets/',
						'publicDir'     => null,
						'minifyDir'     => 'minify/',
						'absolutePaths' => true,
						'allowedExts'   => array(
											'js',
											'css',
										   ),
						'minifyFile'    => 'compressed',
						'useLocalJS'    => false,
						'htmlCSS'       => '<link rel="stylesheet" media="screen" href="%s">',
						'htmlJS'        => '<script src="%s"></script>',
						'compressCode'  => true,
						'cssLevel'      => 'sane',
						'useRewrite'    => false
					   );

		self::$_opt = (self::$_opt + $defaultOpts);

	}

	/**
	 * Validate that the output directory exists and is writable.
	 *
	 * @return	boolean
	 */
	static protected function validateOutputDir()
	{

		self::validateOpt('outputDir');

		self::$_outputDir = self::$_opt['outputDir'];
		$isValid          = self::validateDir(self::$_outputDir);

		return $isValid;

	}

	/**
	 * Validate that the public directory exists and is writable. Also
	 * adds / to non-absolute paths if absolutePaths is set to true.
	 *
	 * @return	void
	 */
	static protected function validatePublicDir()
	{
		
		if (self::$_opt['publicDir'] === null) {
		
			self::$_publicDir = self::$_outputDir;
		
		} else {
			
			self::$_publicDir = self::$_opt['publicDir'];

		}

		$char = substr(self::$_publicDir, 0, 1);

		if (self::$_opt['absolutePaths'] === true && $char !== '/') {

			self::$_publicDir = '/'.self::$_publicDir;

		}


	}

	/**
	 * Validate that the cache directory exists and is writable.
	 *
	 * @return boolean
	 */
	static protected function validateCacheDir()
	{

		self::validateOpt('cacheDir');

		self::$_cacheDir = self::$_opt['cacheDir'];
		$isValid         = self::validateDir(self::$_cacheDir);

		return $isValid;

	}

	/**
	 * Include third-party classes and files.
	 *
	 * @return void
	 */
	static protected function includeClasses()
	{

		$dir = self::$_opt['minifyDir'];

		include_once $dir.'CSSCompression.php';
		include_once $dir.'curl.class.php';

	}

	/**
	 * Validate that every file added to Minify is valid and any
	 * remote file to the download queue if the source isn't cached.
	 *
	 * @return void
	 */
	static protected function validateFiles()
	{

		self::log(PHP_EOL.'validateFiles():');

		foreach (self::$_files as $k => $file) {

			$key =& self::$_files[$k];

			if (self::isAllowedExt($file['path']) === false) {

				unset($key);

				$file = basename($file['path']);
				$msg  = 'Skipping %s due to invalid file.';
				$msg  = sprintf($msg, $file);

				throw new Exception($msg);

			} else {

				$key['ext'] = self::getExt($file['path']);

				$regexp = '/((http|ftp|https):\/\/[\w\-_]+
						(\.[\w\-_]+)+([\w\-\.,@?^=%&amp;:
						\/~\+#]*[\w\-\@?^=%&amp;\/~\+#])?)/siU';

				$regexp = preg_replace('/\s+/', '', $regexp);

				if (preg_match($regexp, $file['path'], $match) !== 0) {

					$srcPath   = $file['path'];
					$cachePath = self::$_cacheDir.md5($file['path']);

					if (file_exists($cachePath) === true) {

						$key['data'] = file_get_contents($cachePath);
						$key['path'] = $cachePath;
						$key['hash'] = hash(self::$_opt['algorithm'], $key['data']);
						self::log('Cache   : '.basename($file['path']), true, 1);

					} else {

						self::$_downloadQueue[$k] = $srcPath;
						self::log('Download: '.basename($file['path']), true, 1);

					}

				} else {

					if (file_exists($file['path']) === true) {

						$key['data'] = file_get_contents($file['path']);
						$key['hash'] = hash(self::$_opt['algorithm'], $key['data']);
						self::log('Found   : '.basename($file['path']), true, 1);

					} else {

						unset($key);
						self::log('Invalid : '.basename($file['path']), true, 1);

					}

				}//end if

			}//end if

		}//end foreach
		
	}

	static protected function downloadFiles()
	{

		foreach (self::$_downloadQueue as $key => $file) {

			unset(self::$_downloadQueue[$key]);
			$urls[$key] = $file;

		}

		$curl   = new CURLRequest();
		$return = $curl->getThreaded(
			$urls,
			array(
			 CURLOPT_RETURNTRANSFER => true,
			 CURLOPT_FOLLOWLOCATION => true,
			),
			25
		);
		
		foreach ($return as $key => $data) {
			
			if ($data['info']['http_code'] !== 200) {

				unset(self::$_files[$key]);

				$file = basename($data['info']['url']);
				$code = $data['info']['http_code'];

				$msg = 'Skipping %s due to download error (%u).';
				$msg = sprint($msg, $file, $code);

				throw new Exception($msg);

			} else {
				
				$path =  self::$_cacheDir.md5($data['info']['url']);
				$k    =& self::$_files[$key];

				$k['data'] = $data['content'];
				$k['path'] = $path;
				$k['hash'] = hash(self::$_opt['algorithm'], $data['content']);

				file_put_contents($path, $data['content']);

			}//end if

		}//end foreach

	}

	static protected function detectMode()
	{

		self::$_jsMode  = false;
		self::$_cssMode = false;
		
		foreach (self::$_files as $file) {

			switch($file['ext']) {
				case 'js':
					self::$_jsMode = true;
					break;
				case 'css':
					self::$_cssMode = true;
					break;
			}

			if (self::$_jsMode !== false && self::$_cssMode !== false) {

				break;

			}

		}

	}

	static protected function validateCache()
	{
			
			$cache = file_get_contents(self::$_outputDir.self::$_opt['cacheFile']);

			if ($cache !== false) {

				$cache  = explode(PHP_EOL, $cache);
				$hashes = array();

				foreach ($cache as $line) {

					list($file, $hash) = explode(' ', $line);
					$hashes[$file]     = $hash;

				}

				foreach (self::$_files as $k => $file) {

					self::log('check '.basename($file['path']), false, 1);

					if (array_key_exists($file['path'], $hashes) === false) {

						self::log(' ... FAIL!');
						return false;

					} else if ($file['hash'] !== $hashes[$file['path']]) {

						self::log(' ... FAIL!');
						return false;

					} else {

						self::log(' ... OK!');
						unset($hashes[$file['path']]);

					}

				}//end foreach

				if (empty($hashes) === false) {

					return false;

				} else {
					
					return true;

				}

			} else {

				return false;

			}//end if

	}

	static protected function evaluate()
	{

		self::log(PHP_EOL.'evaluate():');

		$file = self::$_outputDir.self::$_opt['cacheFile'];

		self::log('file_exists '.$file, false, 1);

		if (file_exists($file) === false) {

			self::log(' ... FAIL!');
			return false;

		} else {

			self::log(' ... OK!');

		}

		if (self::$_jsMode === true) {

			$file = self::$_outputDir.self::$_opt['minifyFile'].'.js';

			self::log('file_exists '.$file, false, 1);

			if (file_exists($file) === false) {

				self::log(' ... FAIL!');
				return false;

			} else {

				self::log(' ... OK!');

			}

		}

		if (self::$_cssMode === true) {

			$file = self::$_outputDir.self::$_opt['minifyFile'].'.css';

			self::log('file_exists '.$file, false, 1);

			if (file_exists($file) === false) {

				self::log(' ... FAIL!');
				return false;

			} else {

				self::log(' ... OK!');

			}

		}

		$valid = self::validateCache();
		return $valid;

	}

	static protected function compressFiles()
	{

		ini_set('max_execution_time', 120);

		self::$_mincode['js']  = '';
		self::$_mincode['css'] = '';

		$curl = new CURLRequest();
		$css  = new CSSCompression();
		
		$css->option('readability', CSSCompression::READ_NONE);
		$css->option('mode', self::$_opt['cssLevel']);

		foreach (self::$_files as $file) {

			$code  = $file['data'];
			$hash  = md5($code);
			$cache = self::$_cacheDir.$hash;

			if (file_exists($cache) === true) {

				self::$_mincode[$file['ext']] .= file_get_contents($cache);

			} else {

				if (self::$_opt['compressCode'] === false) {

					self::$_mincode[$file['ext']] .= $code;

				} else {

					if ($file['ext'] === 'js') {

						if (self::$_opt['useLocalJS'] === false) {

							if (((strlen($code) / 1000) / 1000) > 1) {

								$file = basename($file['path']);

								$msg  = '%s is bigger than 1000kB,';
								$msg .= ' split the code into multiple files or';
								$msg .= ' enable local compression for javascript.';
								$msg  = sprintf($msg, $file);

								throw new Exception($msg);

							}

							$post = array(
									 'js_code'           => $code,
									 'compilation_level' => 'SIMPLE_OPTIMIZATIONS',
									 'output_format'     => 'json',
									);

							// Workaround to allow multiple output_info in query.
							$post  = http_build_query($post);
							$post .= '&output_info=errors&output_info=compiled_code';

							$return = $curl->get(
								'http://closure-compiler.appspot.com/compile',
								array(
								 CURLOPT_RETURNTRANSFER => true,
								 CURLOPT_POSTFIELDS     => $post,
								 CURLOPT_POST           => true,
								)
							);

							$data = json_decode($return['content'], true);

							if (isset($data['errors']) === true
								|| isset($data['serverErrors']) === true
							) {

								$error = $data['errors'][0]['error'];
								$file  = basename($file['path']);
								$line  = $data['errors'][0]['lineno'];

								$msg = 'Web Service returned %s in %s on line %u.';
								$msg = sprintf($msg, $error, $file, $line);

								throw new Exception($msg);

							} else if (isset($data['compiledCode']) === true) {
								
								$code = $data['compiledCode'];

								self::$_mincode[$file['ext']] .= $code;
								file_put_contents($cache, $code);

							} else {

								throw new Exception('An unknown error has occured.');

							}//end if

						}//end if

					} else if ($file['ext'] === 'css') {
						

						$code = trim($css->compress($code));

						self::$_mincode[$file['ext']] .= $code;

						file_put_contents($cache, $code);

					}//end if

				}//end if

			}//end if

		}//end foreach

	}

	static protected function saveFiles()
	{

		if (self::$_jsMode === true) {

			$name = self::$_outputDir.self::$_opt['minifyFile'].'.js';

			file_put_contents($name, self::$_mincode['js']);
			chmod(self::$_outputDir.self::$_opt['minifyFile'].'.js', 0775);

		}

		if (self::$_cssMode === true) {

			$name = self::$_outputDir.self::$_opt['minifyFile'].'.css';

			file_put_contents($name, self::$_mincode['css']);
			chmod(self::$_outputDir.self::$_opt['minifyFile'].'.css', 0775);

		}

	}

	static protected function saveCacheFile()
	{

		$cache = '';

		foreach (self::$_files as $file) {

			$cache .= $file['path'].' '.$file['hash'].PHP_EOL;

		}

		file_put_contents(self::$_outputDir.self::$_opt['cacheFile'], trim($cache));

	}

}