<?php
/*
 * Minify Class by Jonas Stendahl
 * http://www.jyggen.com/
 *
 * CSS Compressor by Corey Hart
 * http://www.codenothing.com/
 */

class minify {

    /* minify vars */
    protected $options;
    protected $posteriority;
    protected $priority;
    protected $ignore;
    protected $allowed_types;
    protected $files;
    protected $hashes;
    protected $code;
    protected $merge_path;
    protected $path_pattern;
	protected $remote_files;
    protected $all_files;

    public $links = array();

    /* debug vars */
    private $start;
    private $stop;
    private $debug_output = '';

	/* when Minify is initiated */
    public function __construct() {
        
        $this->start = microtime(true);
        
        /* Reset everything to their default values */
        $this->reset();

	}

	/* when Minify is closed */
	public function __destruct() {

		/* if debug is enabled output lots of data collected during runtime */
		if($this->options['debug'] === true) {
			
			print '<style type="text/css">td { padding:0 35px 0 0; }</style>';
			print '<pre>' . $this->debug_output;
		
			$this->stop = microtime(true);
            $res = $this->stop - $this->start;
			            
            print "\n" . 'Execution Time: ' . round($res, 6);
			print "\n" . 'Memory Usage (Avg): ' . round((memory_get_usage(true)/1000)/1000, 2) . ' MB';
			print "\n" . 'Memory Usage (Peak): ' . round((memory_get_peak_usage(true)/1000)/1000, 2) . ' MB';
			print '</pre>';
			
		}
	
	}
	
	private function include_classes() {
		
		require_once $this->options['min_dir'] . 'CSSCompression.class.php';
		require_once $this->options['min_dir'] . 'curl.class.php';
		
	}
	
	/* Minify! */
	public function run() {

        /* check if options->type is an allowed file type */
        if(!in_array($this->options['type'], $this->allowed_types))
			die(trigger_error($this->options['type'] . ' is not a valid file type', E_USER_ERROR));

		$this->debug('Running Minify! in ' . strtoupper($this->options['type']) . ' mode with debugging enabled.', true);
		
		/* print options if we're in debug mode */
		$this->debug('Options:', true);
		$this->debug($this->options, false, true);
		
		/* generate merge_path */
		$this->get_merge_path();	
		$this->debug('Merge Path: ' . $this->merge_path, true);
		
		/* validate permissions */
		$this->validate_permissions();
		
		/* validate files */
		$this->validate_files();
		
		/* include required classes */
		$this->include_classes();
		
		/* if we haven't cached a remote file, retrieve it! */
		if(!empty($this->remote_files)) {
			$this->download_files();
		}

		/* if the .sfv-file doesn't exist OR if the merge file doesn't exist OR if the files don't match */
		if(!file_exists($this->options['directory'] . $this->options['cache']) ||
		   !file_exists($this->merge_path) ||
		   !$this->compare()) {

			$this->compress();
			$this->save();
			$this->generate_hash_file();
			$this->debug('Status: Something is wrong!', true);

		/* roll with our existing files */
		} else {

			$this->debug('Status: Everything seems OK!', true);
		
		}
		
		/* fetch the html code for all files */
		$this->get_links();

		$this->debug('--------------------------------------', true);

    }
    
	private function validate_permissions() {

		if(!@is_writable($this->options['directory']) && !@chmod($this->options['directory'], 777))
			die(trigger_error($this->options['directory'] . ' is not writable', E_USER_ERROR));
			
		if(!@is_writable($this->options['min_dir'].$this->options['cache_dir']) && !@chmod($this->options['min_dir'].$this->options['cache_dir'], 777))
			die(trigger_error($this->options['min_dir'].$this->options['cache_dir'] . ' is not writable', E_USER_ERROR));

	}
	
    /* set option $name to $value */
    public function set($name, $value) {
		$this->options[$name] = $value;
  
    }
	
	/* shortcut to set type */
	public function setType($value) {
		$this->set('type', $value);
	}
	
	/* shortcut to set directory */
	public function setDirectory($value) {
		$this->set('directory', $value);
	}
	
	public function addFile($files) {
		if(is_array($files)) {
			foreach($files as $file) {
				$this->files[]['path'] = $file;
			}
		} else {
			$this->files[]['path'] = $files;
		}
	}
	
	private function download_files() {
		
		if(!is_dir($this->options['min_dir'].$this->options['cache_dir'])) {
			if(!mkdir($this->options['min_dir'].$this->options['cache_dir'])) {
				die(trigger_error('Could not create cache dir.', E_USER_ERROR));
			}
		}
		
		$opts = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true
		);

		foreach($this->remote_files as $key => $file)
			$urls[$key] = $file;

		$curl   = new CURLRequest();  
		$return = $curl->getThreaded($urls, $opts, 10);
		
		$this->debug('Downloading Files:', true);
		$this->debug('<table>');
		foreach($return as $key => $data) {

			$this->debug('<tr><td>- ' . $data['info']['url'] . '</td>');
			if($data['info']['http_code'] != 200) {
				
				$this->debug('<td>Skip!</td>');
				trigger_error($data['info']['url'] . ' returned ' . $data['info']['http_code'] . ' and was skipped', E_USER_NOTICE);
				unset($this->files[$key]);
			
			} else {
				
				$this->files[$key]['data'] = $data['content'];
				$this->files[$key]['path'] = $this->options['min_dir'].$this->options['cache_dir'].md5($data['info']['url']);
				$this->files[$key]['hash'] = hash($this->options['algorithm'], $this->files[$key]['data']);
				file_put_contents($this->options['min_dir'].$this->options['cache_dir'].md5($data['info']['url']), $data['content']);
				$this->debug('<td>OK!</td>');
				
			}
			$this->debug('</tr>');
			
		}
		$this->debug('</table>');
		
	}
	
	/* validate and retrive cache files */
	private function validate_files() {
		
		$this->debug('Validating Files:', true);
		$this->debug('<table style="border-collapse:collapse;">');
		
		foreach($this->files as $key => $file) {

			if(preg_match('/((http|ftp|https):\/\/[\w\-_]+(\.[\w\-_]+)+([\w\-\.,@?^=%&amp;:\/~\+#]*[\w\-\@?^=%&amp;\/~\+#])?)/siU', $file['path'], $match)) {
				
				$src_path   = $file['path'];
				$cache_path = $this->options['min_dir'].$this->options['cache_dir'].md5($file['path']);

				if(file_exists($cache_path)) {
					
					$this->files[$key]['data'] = file_get_contents($cache_path);
					$this->files[$key]['path'] = $cache_path;
					$this->files[$key]['hash'] = hash($this->options['algorithm'], $this->files[$key]['data']);
					$this->debug('<tr><td>- Cache:</td><td>' . $cache_path . '</td></tr>');
				
				} else {
					
					$this->remote_files[$key] = $src_path;
					$this->debug('<tr><td>- Download:</td><td>' . $src_path . '</td></tr>');
					
				}
				
			} else {

				if(file_exists($file['path'])) {
					$this->files[$key]['data'] = file_get_contents($file['path']);
					$this->files[$key]['hash'] = hash($this->options['algorithm'], $this->files[$key]['data']);
					$this->debug('<tr><td>- Found:</td><td>' . $file['path'] . '</td></tr>');
				} else {
					unset($this->files[$key]);
					$this->debug('<tr><td>- Invalid:</td><td>' . $file['path'] . '</td></tr>');
				}

			}

		}
	
		$this->debug('</table>', true);
	
	}
    
    /* reset everything to their default values */
    public function reset() {

		$this->options       = array();
    	$this->posteriority  = array();
    	$this->priority      = array();
    	$this->ignore        = array();
    	$this->allowed_types = array('css', 'js');
    	$this->files		 = array();
    	$this->hashes		 = array();
    	$this->code			 = array();
    	$this->merge_path	 = '';
    	$this->path_pattern  = '';
    	$this->all_files	 = array();

		$this->options = array(
			'algorithm'  => 'crc32b',
			'cache'      => 'minify.sfv',
			'cache_dir'  => 'cache/',
			'debug'      => false,
			'directory'  => '',
			'absolute'   => true,
			'name'       => 'files',
			'prefix'     => false,
			#'regex'      => '/^.*\.min\.(css|js)$/i',
			'script_src' => '<script type="text/javascript" src="%s?%s"></script>' . "\n",
			'style_link' => '<link rel="stylesheet" type="text/css" media="screen" href="%s?%s" />' . "\n",
			'suffix'     => 'min',
			'type'       => '',
			'min_dir'    => __DIR__ . '/minify/',
			'cache_ttl'		 => 604800
		);
		
		ksort($this->options);

    }
    
    /* save $output for later use. $linebreak will add a new line in front of $output */
    private function debug($output, $linebreak = false, $print_key = false) {
    	
    	if($this->options['debug'] === TRUE) {
    		
    		if($linebreak)
    			$this->debug_output .= "\n";
    		
    		if(is_array($output)) {
				if($print_key) {
					$this->debug_output .= '<table>';
				}
    			foreach($output as $key => $ent) {
					if(!$print_key) {
						$this->debug_output .= '- ' . $ent . "\n";
					} else {
						$this->debug_output .= '<tr><td>- ' . $key . ':</td><td>';
						$this->debug_output .= (empty($ent)) ? '<i>undefined</i>' : htmlentities($ent);
						$this->debug_output .= '</td>';
					}
				}
				if($print_key) {
					$this->debug_output .= '</table>';
				}
    		} else $this->debug_output .= $output . "\n";
    		
    		return TRUE;
    	
    	} else return FALSE;
    
    }
    
    /* generate the path and name of the merged file */
    private function get_merge_path() {
		
		if(empty($this->options['directory'])) {
			trigger_error('missing option directory', E_USER_ERROR);
			exit(1);
		}

    	$this->merge_path = $this->options['directory'];
		
		if(!is_dir($this->merge_path)) {
			trigger_error('invalid directory ' . $this->merge_path, E_USER_ERROR);
			exit(1);
		}
    	
    	if($this->options['prefix'])
    		$this->merge_path .= $this->options['prefix'] . '.';
    	
    	$this->merge_path .= $this->options['name'];
    	
    	if($this->options['suffix'])
    		$this->merge_path .= '.' . $this->options['suffix'];
    		
		$this->merge_path .= '.' . $this->options['type'];

		return true;
    
    }
    
    /* generate an array with html code for each file */
    private function get_links() {
		
		$hash = hash_file($this->options['algorithm'], $this->merge_path);
		
		if($this->options['absolute'])
			$path = '/' . $this->merge_path;
		else
			$path = $this->merge_path;
		
		switch($this->options['type']) {
			case 'js':
				array_push($this->links, sprintf($this->options['script_src'], $path, $hash));
				break;
			case 'css':
				array_push($this->links, sprintf($this->options['style_link'], $path, $hash));
				break;
		}
    
    }

    /* compare every file with the hashes in the cache file */
    private function compare() {
		
        if($cache = file_get_contents($this->options['directory'] . $this->options['cache'])) {
			$cache = explode("\n", $cache);

			foreach($cache as $line) {

				list($file, $hash) = explode(' ', $line);
				$hashes[$file] = $hash;

			}

			$this->debug('Compare:', true);

			foreach($this->files as $k => $file) {
				
				if(!array_key_exists($file['path'], $hashes)) {
				
					$this->debug('check' . "\t" . $file['path'] . "\t" . $file['hash'] . "\t" . 'FAIL!');
					return false;
				
				}
				
				if($file['hash'] != $hashes[$file['path']]) {

					$this->debug('check' . "\t" . $file['path'] . "\t" . $file['hash'] . "\t" . 'FAIL!');
					return false;
					
				}
				
				unset($hashes[$file['path']]);
				$this->debug('check' . "\t" . $file['path'] . "\t" . $file['hash'] . "\t" . 'OK!');

			}
			
			if(!empty($hashes))
				return false;

			return true;
			
		} else return false;

    }

    /* compress the code in the files */
    private function compress() {

		$code = '';
		
		foreach($this->files as $file)
			$code .= $file['data'];
			
		switch($this->options['type']) {
			case 'js':
			
				/* if() { */ // In the future we should check if we're allowed to run closure-compiler locally.
			
					$curl = new CURLRequest();
					$code = urlencode($code);
					
					if((strlen($code)/1000)/1000 > 1) {
						trigger_error('Code too large and won\'t be allowed by the web service. Please limit the total size of your files to 1000 KB', E_USER_ERROR);
						exit(1);
					}

					$return = $curl->get('http://closure-compiler.appspot.com/compile', array(
						CURLOPT_RETURNTRANSFER => true, 
						CURLOPT_POSTFIELDS     => 'js_code=' . $code . '&compilation_level=SIMPLE_OPTIMIZATIONS&output_format=json&output_info=errors&output_info=compiled_code',
						CURLOPT_POST           => true
					));
					$data = json_decode($return['content'], true);
					if(isset($data['errors'])) {
						trigger_error($data['errors'][0]['error'], E_USER_ERROR);
						exit(1);
					} elseif(isset($data['serverErrors'])) {
						trigger_error($data['serverErrors'][0]['error'], E_USER_ERROR);
						exit(1);
					} elseif(isset($data['compiledCode'])) {
						$code = $data['compiledCode'];
					} else {
						if($this->options['debug'] === true) {
							echo '<pre>';
							print_r($data);
							print_r($return);
							echo '</pre>';
						}
						die(trigger_error('An unknown error has occured', E_USER_ERROR));
					}
					
				/* } */
					
				break;
			case 'css':
				$code = trim(CSSCompression::express($code, 'small'));
				break;
		}
		
		$this->code = $code;
		return true;

    }
    
    /* save the code to disk in a merged file */
    private function save() {
		
		$this->debug('Save:', true);	

		file_put_contents($this->merge_path, $this->code);
		chmod($this->merge_path, 0755);

		$this->debug('Code saved to ' . $this->merge_path);

    	return true;
    
    }
	
	/* generate hashes */
	private function generate_hashes() {
		foreach($this->files as $k => $file) {
			$this->hashes[$file] = hash('crc32b', $file['data']);
		}
	}

    /* generate and save a new cache */
    private function generate_hash_file() {
        
        $cache = '';
        $this->debug('Cache:', true);
		
		$this->debug('<table>');
        foreach($this->files as $file) {
        
            $cache .= $file['path'] . ' ' . $file['hash'] . "\n";
            $this->debug('<tr><td>- ' . $file['path'] . '</td><td>' . $file['hash'] . '</td></tr>');
            
        }
		$this->debug('</table>');
        
        file_put_contents($this->options['directory'] . $this->options['cache'], trim($cache));

    }

}