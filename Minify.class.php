<?php
/*
 * Minify Class by Jonas Stendahl
 * http://www.jyggen.com/
 *
 * CSS Compressor by Corey Hart
 * http://www.codenothing.com/
 *
 * Closure Compiler by Google
 * http://closure-compiler.appspot.com/
 *
 */

class Minify {
	
	static protected $opt           = array();
	static protected $files         = array();
	static protected $downloadQueue = array();
	static protected $debugLog      = array();
	static protected $cacheDir, $cssMode, $jsMode, $mincode, $outputDir;
	
	static public function loadConfig($path) {
		
		$path = __DIR__ . '/' . $path;
		
		if(!file_exists($path)) {
		
			trigger_error('Couldn\'t load configuration file from "' . $path . '"', E_USER_ERROR);
			exit(1);

		} else {
		
			require_once $path;
			self::$opt = array_merge(self::$opt, $options);
		
		}
	
	}
	
	static public function add($files) {
		
		if(is_array($files)) {
		
			foreach($files as $file) {
			
				self::add($file);
			
			}
		
		} else {
		
			self::$files[]['path'] = $files;
		
		}
		
	}

	static public function run() {
		
		self::loadDefaultOpts();	
		self::validateOutputDir();
		self::validateCacheDir();
		self::validateFiles();
		self::includeClasses();
		
		if(!empty(self::$downloadQueue))
			self::downloadFiles();
			
		self::detectMode();
		
		if(!self::evaluate()) {
		
			self::compressFiles();
			self::saveFiles();
			self::saveCacheFile();
		
		} else return;

	}
	
	static public function getLinks() {
		
		$links = '';
		
		if(self::$jsMode) {
			
			$file = self::$outputDir . self::$opt['minifyFile'] . '.js';
			$hash = hash_file(self::$opt['algorithm'], $file);
			$links .= '<script type="text/javascript" src="' . $file . '?' . $hash . '"></script>' . "\n";
			
		}
		
		if(self::$cssMode) {
			
			$file = self::$outputDir . self::$opt['minifyFile'] . '.css';
			$hash = hash_file(self::$opt['algorithm'], $file);
			$links .= '<link rel="stylesheet" type="text/css" media="screen" href="' . $file . '?' . $hash . '" />' . "\n";
		
		}
		
		return $links;
	
	}
	
	static public function printLinks() {
		
		echo self::getLinks();
	
	}
	
	static public function debug() {
		
		echo '<pre>';
		
		foreach(self::$debugLog as $log)
			echo $log;
		
		echo '</pre>';
		
	}
	
	static protected function log($data, $eol = TRUE, $tab = 0) {
		
		$msg = '';
		
		for($i = 0; $i < $tab; $i++) {$msg .= "\t"; }
		
		$msg .= $data;
		
		if($eol) { $msg .= "\n"; }

		self::$debugLog[] = $msg;
	
	}
	
	static protected function validateDir($dir) {

		if(!is_dir($dir)) {
		
			trigger_error('"' . $dir . '" is not a valid directory', E_USER_ERROR);
			exit(1);
		
		}
		
		if(!is_writable($dir)) {
		
			trigger_error('"' . $dir . '" is not writable', E_USER_ERROR);
			exit(1);

		}
			
		return true;
			
	}
	
	static protected function validateOpt($key) {
		
		if(!isset(self::$opt[$key]) OR empty(self::$opt[$key])) {
		
			trigger_error('Missing "' . $key . '" in configuration', E_USER_ERROR);
			exit(1);
		
		} else return true;
		
	}
	
	static protected function getExt($name) {
		
		$info = pathinfo($name);
		
		return $info['extension'];
		
	}
	
	static protected function isAllowedExt($name) {
		
		$ext = self::getExt($name);
		
		return (in_array($ext, self::$opt['allowedExts']));
		
	}
	
	static protected function loadDefaultOpts() {
		
		$defaultOpts = array(
			'algorithm'     => 'crc32b',
			'cacheFile'     => 'minify.sfv',
			'cacheDir'      => 'minify/cache/',
			'outputDir'     => 'assets/',
			'minifyDir'     => 'minify/',
			'debug'         => FALSE,
			'absoultePaths' => TRUE,
			'allowedExts'   => array('js', 'css'),
			'minifyFile'    => 'files.min',
			'useLocalJS'    => FALSE
		);
		
		self::$opt = self::$opt + $defaultOpts;

	}
	
	static protected function validateOutputDir() {
		
		self::validateOpt('outputDir');
		
		self::$outputDir = self::$opt['outputDir'];
		
		return self::validateDir(self::$outputDir);
		
	}
	
	static protected function validateCacheDir() {

		self::validateOpt('cacheDir');
		
		self::$cacheDir = __DIR__ . '/' . self::$opt['cacheDir'];
		
		return self::validateDir(self::$cacheDir);
		
	}
	
	static protected function includeClasses() {
		
		require_once self::$opt['minifyDir'] . 'CSSCompression.php';
		require_once self::$opt['minifyDir'] . 'curl.class.php';
		
	}
	
	static protected function validateFiles() {
		
		self::log("\n" . 'validateFiles():');
		
		foreach(self::$files as $key => $file) {
			
			if(!self::isAllowedExt($file['path'])) {
				
				unset(self::$files[$key]);
				trigger_error('skipping ' . basename($file['path']) . ' due to invalid file', E_USER_NOTICE);
				
			} else {

				self::$files[$key]['ext'] = self::getExt($file['path']);
				
				if(preg_match('/((http|ftp|https):\/\/[\w\-_]+(\.[\w\-_]+)+([\w\-\.,@?^=%&amp;:\/~\+#]*[\w\-\@?^=%&amp;\/~\+#])?)/siU', $file['path'], $match)) {
					
					$src_path   = $file['path'];
					$cache_path = self::$cacheDir . md5($file['path']);

					if(file_exists($cache_path)) {
	
						self::$files[$key]['data'] = file_get_contents($cache_path);
						self::$files[$key]['path'] = $cache_path;
						self::$files[$key]['hash'] = hash(self::$opt['algorithm'], self::$files[$key]['data']);
						self::log('Cache   : ' . basename($file['path']), TRUE, 1);

					} else {
						
						self::$downloadQueue[$key] = $src_path;
						self::log('Download: ' . basename($file['path']), TRUE, 1);
						
					}
					
				} else {

					if(file_exists($file['path'])) {
					
						self::$files[$key]['data'] = file_get_contents($file['path']);
						self::$files[$key]['hash'] = hash(self::$opt['algorithm'], self::$files[$key]['data']);
						self::log('Found   : ' . basename($file['path']), TRUE, 1);

					} else {
						
						unset(self::$files[$key]);
						self::log('Invalid : ' . basename($file['path']), TRUE, 1);
						trigger_error('skipping ' . basename($file['path']) . ' due to invalid file', E_USER_NOTICE);

					}

				}
			
			}
			
		}

	}
	
	static protected function downloadFiles() {

		foreach(self::$downloadQueue as $key => $file) {
			
			unset(self::$downloadQueue[$key]);
			$urls[$key] = $file;
			
		}

		$curl   = new CURLRequest();  
		$return = $curl->getThreaded($urls, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true
		), 25);

		foreach($return as $key => $data) {

			if($data['info']['http_code'] != 200) {

				unset(self::$files[$key]);
				trigger_error('skipping ' . basename($data['info']['url']) . ' due to download error (' . $data['info']['http_code'] . ')', E_USER_NOTICE);
			
			} else {
				
				$path = self::$cacheDir . md5($data['info']['url']);
				
				self::$files[$key]['data'] = $data['content'];
				self::$files[$key]['path'] = $path;
				self::$files[$key]['hash'] = hash(self::$opt['algorithm'], $data['content']);

				file_put_contents($path, $data['content']);
				
			}
			
		}
		
	}
	
	static protected function detectMode() {
	
		self::$jsMode = FALSE;
		self::$cssMode = FALSE;
		
		foreach(self::$files as $file) {
		
			switch($file['ext']) {
				case 'js':
					self::$jsMode = TRUE;
					break;
				case 'css':
					self::$cssMode = TRUE;
					break;
			}
			
			if(self::$jsMode !== FALSE && self::$cssMode !== FALSE)
				break;
		
		}
	
	}
	
	static protected function evaluate() {
		
		self::log("\n" . 'evaluate():');
		
		self::log('file_exists ' . self::$outputDir . self::$opt['cacheFile'], FALSE, 1);
		if(!file_exists(self::$outputDir . self::$opt['cacheFile'])) {
			
			self::log(' ... FAIL!');
			return false;
			
		}
		self::log(' ... OK!');
		
		
		if(self::$jsMode === TRUE) {
			
			self::log('file_exists ' . self::$outputDir . self::$opt['minifyFile'] . '.js', FALSE, 1);
			if(!file_exists(self::$outputDir . self::$opt['minifyFile'] . '.js')) {
			
				self::log(' ... FAIL!');
				return false;
			}
			self::log(' ... OK!');

		}
		
		if(self::$cssMode === TRUE) {
			
			self::log('file_exists ' . self::$outputDir . self::$opt['minifyFile'] . '.css', FALSE, 1);
			if(!file_exists(self::$outputDir . self::$opt['minifyFile'] . '.css')) {
				
				self::log(' ... FAIL!');
				return false;
			
			}
			self::log(' ... OK!');
			
		}
		
        if($cache = file_get_contents(self::$outputDir . self::$opt['cacheFile'])) {

			$cache  = explode("\n", $cache);
			$hashes = array();

			foreach($cache as $line) {

				list($file, $hash) = explode(' ', $line);
				$hashes[$file]     = $hash;

			}

			foreach(self::$files as $k => $file) {
				
				self::log('check ' . basename($file['path']), FALSE, 1);
				
				if(!array_key_exists($file['path'], $hashes)) {
					
					self::log(' ... FAIL!');
					return false;
					
				}
				
				if($file['hash'] != $hashes[$file['path']]) {
					
					self::log(' ... FAIL!');
					return false;
				
				}
				
				self::log(' ... OK!');
				unset($hashes[$file['path']]);

			}
			
			if(!empty($hashes)) {
				
				foreach($hashes as $hash)
					self::log('check ' . $hash . ' ... NOT FOUND!', TRUE, 1);
				
				return false;
				
			}
			
		} else return false;
		
	}
	
	static protected function compressFiles() {
		
		@ini_set(max_execution_time, 120);

		self::$mincode['js']  = '';
		self::$mincode['css'] = '';
		
		$curl = new CURLRequest();
		
		foreach(self::$files as $file) {
		
			$code  = $file['data'];
			$hash  = md5($code);
			$cache = self::$cacheDir . $hash;
			
			if(file_exists($cache)) {
			
				self::$mincode[$file['ext']] .= file_get_contents($cache);
			
			} else {

				if($file['ext'] == 'js') {
					
					if(!self::$opt['useLocalJS']) {
					
						if((strlen($code) / 1000) / 1000 > 1) {
							
							trigger_error(basename($file['path']) . ' is bigger than 1000kB, split the code into multiple files or enable local compression for javascript', E_USER_ERROR);
							exit(1);
						
						}
						
						$postfields = array(
							'js_code'           => $code,
							'compilation_level' => 'SIMPLE_OPTIMIZATIONS',
							'output_format'     => 'json'
						);
							
						$postfields = http_build_query($postfields) . '&output_info=errors&output_info=compiled_code';

						$return = $curl->get('http://closure-compiler.appspot.com/compile', array(
							CURLOPT_RETURNTRANSFER => true, 
							CURLOPT_POSTFIELDS     => $postfields,
							CURLOPT_POST           => true
						));
							
						$data = json_decode($return['content'], true);
							
						if(isset($data['errors'])) {
						
						trigger_error('Web Service returned "' . $data['errors'][0]['error'] . '"', E_USER_ERROR);
								exit(1);
						
						} elseif(isset($data['serverErrors'])) {
						
							trigger_error('Web Service returned "' . $data['serverErrors'][0]['error'] . '"', E_USER_ERROR);
							exit(1);
						
						} elseif(isset($data['compiledCode'])) {
							
								self::$mincode[$file['ext']] .= $data['compiledCode'];
								file_put_contents($cache, $data['compiledCode']);
						
						} else {
	
							trigger_error('An unknown error has occured', E_USER_ERROR);
							exit(1);
						
						}
							
					}
				
				} elseif($file['ext'] == 'css') {
					
					$code = trim(CSSCompression::express($code, 'small'));
					
					self::$mincode[$file['ext']] .= $code;
					
					file_put_contents($cache, $code);
				
				}
				
			}
		
		}
		
	}
	
	static protected function saveFiles() {
		
		if(self::$jsMode) {
		
			file_put_contents(self::$outputDir . self::$opt['minifyFile'] . '.js', self::$mincode['js']);
			chmod(self::$outputDir . self::$opt['minifyFile'] . '.js', 0755);
			
		}
		
		if(self::$cssMode) {
		
			file_put_contents(self::$outputDir . self::$opt['minifyFile'] . '.css', self::$mincode['css']);
			chmod(self::$outputDir . self::$opt['minifyFile'] . '.css', 0755);
			
		}
		
	}
	
	static protected function saveCacheFile() {
        
        $cache = '';

        foreach(self::$files as $file)
			$cache .= $file['path'] . ' ' . $file['hash'] . "\n";
        
        file_put_contents(self::$outputDir . self::$opt['cacheFile'], trim($cache));

    }
	
}