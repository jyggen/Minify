<?php
/*
 * Minify Class by Jonas Stendahl
 * http://www.jyggen.com/
 *
 * JSMin Class by Nicolas Martin
 * http://joliclic.free.fr/php/javascript-packer/en/
 *
 * CSS Compressor by Corey Hart
 * http://www.codenothing.com/
 */

require 'minify/JSMin.class.php';
require 'minify/CSSCompression.php';

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
		if($this->options['debug']) {
		
			print '<pre>' . $this->debug_output;
		
			$this->stop = microtime(true);
            $res = $this->stop - $this->start;
            
            print "\n" . 'Execution Time: ' . round($res, 6) . '</pre>';
			
		}
	
	}
	
	/* Minify! */
	public function run() {
        
        /* check if options->type is valid */
        if(!in_array($this->options['type'], $this->allowed_types))
			trigger_error($this->options['type'] . ' is not a valid file type', E_USER_ERROR);
		
		/* get all the files from the options->dir */
		$this->get_files();
		
		/* generate merge_path and path_pattern */
		$this->get_merge_path();
		$this->get_path_pattern();
		
		/* compress everything if options->cache doesn't exists */
		if(!file_exists($this->options['directory'] . $this->options['cache'])) {
			
			$this->debug('Status: Couldn\'t find ' . $this->options['cache'] . '.', true);
			$this->compress();				
			$this->save();
			$this->generate_hash_file();
		
        /* compress and merge everything if options->merge is true and merge_path doesn't exist */
        } elseif($this->options['merge'] == true && !file_exists($this->merge_path)) {
        	
        	$this->debug('Status: Couldn\'t find ' . $this->merge_path . '.', true);
			$this->compress();
			$this->save();
			$this->generate_hash_file();
		
		/* compress everything if files and hashes don't match */
		} elseif(!$this->compare()) {
			
			$this->debug('Status: Files don\'t match.', true);
			$this->compress();
			$this->save();
			$this->generate_hash_file();
		
		/* roll with our existing files */
		} else {
		
			$this->debug('Status: Everything seems OK!', true);
		
		}
		
		/* fetch the html code for all files */
		$this->get_links();
		
		$this->debug('--------------------------------------', true);

    }
    
    /* set option $name to $value */
    public function set($name, $value) {

		$this->options[$name] = $value;
    
    }
	
	/* prioritize $file when compressing.  */
	public function prioritize($file) {
	
		array_push($this->priority, $file);
	
	}
	
	/* posterioritize $file when compressing. */
	public function posterioritize($file) {
	
		array_push($this->posteriority, $file);
	
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
			'debug'      => false,
			'absolute'   => true,
			'directory'  => '',
			'merge'      => true,
			'name'       => 'all',
			'prefix'     => false,
			'regex'      => '/^.*\.minify\.(css|js)$/i',
			'script_src' => '<script type="text/javascript" src="%s?%s"></script>' . "\n",
			'style_link' => '<link rel="stylesheet" type="text/css" media="screen" href="%s?%s" />' . "\n",
			'suffix'     => 'minify',
			'type'       => ''
		);
    	    	
    }
    
    /* save $output for later use. $linebreak will add a new line in front of $output */
    private function debug($output, $linebreak = false) {
    	
    	if($this->options['debug'] == true) {
    		
    		if($linebreak)
    			$this->debug_output .= "\n";
    		
    		if(is_array($output)) {
    		
    			foreach($output as $ent)
					$this->debug_output .= ' - ' . $ent . "\n";
    		
    		} else
    			$this->debug_output .= $output . "\n";
    		
    		return true;
    	
    	} else return false;
    
    }
    
    /* generate the path and name of the merged file */
    private function get_merge_path() {
    
    	$this->merge_path = $this->options['directory'];
    	
    	if($this->options['prefix'])
    		$this->merge_path .= $this->options['prefix'] . '.';
    	
    	$this->merge_path .= $this->options['name'];
    	
    	if($this->options['suffix'])
    		$this->merge_path .= '.' . $this->options['suffix'];
    		
		$this->merge_path .= '.' . $this->options['type'];
		
		$this->debug('Merge Path: ' . $this->merge_path, true);
		return true;
    
    }
    
    /* generate the pattern which each file will get named after */
    private function get_path_pattern() {
    
    	$this->path_pattern = $this->options['directory'];
    	
    	if($this->options['prefix'])
    		$this->path_pattern .= $this->options['prefix'] . '.';
    	
    	$this->path_pattern .= '%s';
    	
    	if($this->options['suffix'])
    		$this->path_pattern .= '.' . $this->options['suffix'];
    		
		$this->path_pattern .= '.' . $this->options['type'];
		
		$this->debug('Path Pattern: ' . $this->path_pattern);
		return true;
    
    }
    
    /* generate an array with html code for each file */
    private function get_links() {
    
    	if($this->options['merge']) {
		
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
		
		} else {
		
			foreach($this->files as $file) {
					
				$hash = hash_file($this->options['algorithm'], $this->options['directory'] . $file);
				
				$ext = strrchr($file, '.');  
					
				if($ext !== false)  
					$file = substr($file, 0, -strlen($ext));
					
				$file = sprintf($this->path_pattern, $file);
				
				if($this->options['absolute'])
					$file = '/' . $file;
				
				switch($this->options['type']) {
					case 'js':
						array_push($this->links, sprintf($this->options['script_src'], $file, $hash));
						break;
					case 'css':
						array_push($this->links, sprintf($this->options['style_link'], $file, $hash));
						break;
				}
					
			}
			
		}
    
    }

    /* get all files in options->directory and order them */
    private function get_files() {
		
		if(!file_exists($this->options['directory']))
			trigger_error($this->options['directory'] . ' does not exist', E_USER_ERROR);
		
        $directory = scandir($this->options['directory']);
		$files     = array();
		
		$this->all_files = $directory;
		
        foreach($directory as $file) {
        	
        	if($file != '.' && $file != '..') {
        		if(fnmatch('*.' . $this->options['type'], $file)) {
        			if(!in_array($file, $this->ignore)) {
        				if(!preg_match($this->options['regex'], $file)) {
							
							array_push($files, $file);
							$this->hashes[$file] = hash_file($this->options['algorithm'], $this->options['directory'] . $file);
							
        				}
        			}
        		}
        	}
        	
        }
        
        if(empty($files))
        	trigger_error('no files found', E_USER_NOTICE);
       	
       	$this->debug('Files:', true);
		$this->debug($files);	
       	
        $this->order($files);
        return true;

    }
    
    /* order the files (priority -> normal -> posteriority) */
    private function order($files) {
        
        $return = array();
        
        foreach($this->priority as $file) {
        	
        	if(file_exists($this->options['directory'] . $file))
        		array_push($return, $file);
        		
        	else
        		trigger_error($file . ' does not exist', E_USER_NOTICE);
        
        }
           
        foreach($files as $file) {

            if(!in_array(str_replace($this->options['directory'], '', $file), $this->priority) && !in_array(str_replace($this->options['directory'], '', $file), $this->posteriority))
				array_push($return, $file);

        }
        
        foreach($this->posteriority as $file) {
        	
        	if(file_exists($this->options['directory'] . $file))
        		array_push($return, $file);
        		
        	else
        		trigger_error($file . ' does not exist', E_USER_NOTICE);
        
        }
        
        $this->debug('Order:', true);
		$this->debug($files);	
        
		$this->files = $return;
		return true;
    
    }

    /* compare every file with the hashes in the cache file */
    private function compare() {
        
        $cache = file_get_contents($this->options['directory'] . $this->options['cache']);
        $cache = explode("\n", $cache);

        foreach($cache as $line) {
            
            list($file, $hash) = explode(' ', $line);
            $hashes[$file] = $hash;

        }
        
        $this->debug('Compare:', true);

        foreach($this->files as $file) {
            
            if(!array_key_exists($file, $hashes)) {
            
            	$this->debug('check' . "\t" . $file . "\t" . $this->hashes[$file] . "\t" . 'FAIL!');
            	return false;
            
            }
            
            if($this->hashes[$file] != $hashes[$file]) {
            
                $this->debug('check' . "\t" . $file . "\t" . $this->hashes[$file] . "\t" . 'FAIL!');
                return false;
                
            }
            
			unset($hashes[$file]);
			$this->debug('check' . "\t" . $file . "\t" . $this->hashes[$file] . "\t" . 'OK!');

        }
        
        if(!empty($hashes))
        	return false;

        return true;

    }

    /* compress the code in the files */
    private function compress() {
		
		if(!$this->options['merge']) {
		
			foreach($this->files as $file) {
				
				$code = file_get_contents($this->options['directory'] . $file);
				
				switch($this->options['type']) {
					case 'js':
						$js   = new JSMin($code);
						$code = trim($js->pack());
						break;
					case 'css':
						$css  = new CSSCompression();
						$code = trim($css->compress($code));
						break;
				}
				
				$this->code[$file] = $code;
				
			}
			
		} else {
			
			$code = '';
			
			foreach($this->files as $file)
				$code .= file_get_contents($this->options['directory'] . $file);
				
			switch($this->options['type']) {
				case 'js':
					$js   = new JSMin($code);
					$code = trim($js->pack());
					break;
				case 'css':
					$css  = new CSSCompression();
					$code = trim($css->compress($code));
					break;
			}
			
			$this->code = $code;
			
		}

    }
    
    /* save the code to disk, either in a merged file or in seperate files */
    private function save() {
		
		$this->debug('Save:', true);	
		
    	if($this->options['merge']) {

			file_put_contents($this->merge_path, $this->code);
			chmod($this->merge_path, 0755);

			$this->debug('Code saved to ' . $this->merge_path);
			
    	} else {
    	
    		foreach($this->code as $file => $string) {
    		
				$ext = strrchr($file, '.');  
					
				if($ext !== false)  
					$file = substr($file, 0, -strlen($ext));  
				
				$path = sprintf($this->path_pattern, $file);
				
				file_put_contents($path, $string);
				chmod($path, 0755);
				
				$this->debug('Code saved to ' . $path);
			
			}
			
    	}
    	
    	$this->clean();
    	return true;
    
    }

    /* generate and save a new cache */
    private function generate_hash_file() {
        
        $cache = '';
        $this->debug('Cache:', true);
  
        foreach($this->hashes as $file => $hash) {
        
            $cache .= $file . ' ' . $hash . "\n";
            $this->debug('cache' . "\t" . $file . "\t" . $hash . "\t" . 'OK!');
            
        }
        
        file_put_contents($this->options['directory'] . $this->options['cache'], trim($cache));
        chmod($this->options['directory'] . $this->options['cache'], 0600);

    }
    
    /* remove old files (eg. if you change options->merge to false from true) */
    private function clean() {
	
		foreach($this->all_files as $file) {
			
			if($this->options['merge']) {
				if($this->options['directory'] . $file != $this->merge_path && preg_match($this->options['regex'], $file)) {
					
					unlink($this->options['directory'] .  $file);
					$this->debug($file . ' removed.');
				
				}
			} else {
				if($this->options['directory'] . $file == $this->merge_path) {
					
					unlink($this->options['directory'] . $file);
					$this->debug($file . ' removed.');
					
				}
			}
		
		}
	   
    }

}
