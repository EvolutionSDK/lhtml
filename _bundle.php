<?php

namespace Bundles\LHTML;
use Bundles\Router\NotFoundException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Exception;
use stack;
use e;

class Bundle {

	public static $url_vars = array();
	
	public function __getBundle() {
		return new Instance;
	}

	public function __callBundle() {
		return new Instance;
	}
	
	public function __initBundle() {
		
		/**
		 * Add basic hooks
		 */
		e::configure('lhtml')->activeAddKey('hook', ':e', new e_handle);

		/**
		 * Add LHTML hooks
		 */
		e::configure('lhtml')->activeAddKey('hook', ':slug', function() { return e::$lhtml->_get_special_vars(':slug'); });
		e::configure('lhtml')->activeAddKey('hook', ':id', function() { return e::$lhtml->_get_special_vars(':id'); });
		e::configure('lhtml')->activeAddKey('hook', ':urlVars', function() { return e::$lhtml->_get_special_vars(':urlVars'); } );
	}

	/**
	 * Get all routes for the sitemap
	 * @author Nate Ferrero
	 */
	public function _on_portal_sitemap($path, $dir) {
		$dir .= '/lhtml';
		$all = array();
		try {
			$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::SELF_FIRST);
			foreach($objects as $name => $object){

				if(pathinfo($name, PATHINFO_EXTENSION) !== 'lhtml')
					continue;

				/**
				 * Remove the start dir
				 */
				$name = substr($name, strlen($dir));

				/**
				 * Remove .lhtml from the end and return
				 */
			    $all[substr($name, 0, strlen($name) - 6)] = ucfirst(pathinfo($name, PATHINFO_FILENAME));
			}
		} catch(Exception $e) {}
		return $all;
	}
	
	public function _on_portal_route($path, $dir) {
		$this->route($path, array($dir));
	}
	
	//public function _on_router_route($path) {
	//	$this->route($path, array(e\site));
	//}

	public function _on_portal_exception($path, $dir, $exception) {
		$this->exception($path, array($dir), $exception);
	}
	
	public function _on_router_exception($path, $exception) {
		$this->exception($path, array(e\site), $exception);
	}

	public function exception($path, $dirs, $exception) {
		$search = 'special-' . ($exception instanceof NotFoundException ? 'notfound' : 'exception');
		$this->route(array($search), $dirs);
	}
	
	public function route($path, $dirs = null) {
		
		// If dirs are not specified, use defaults
		if(is_null($dirs))
			$dirs = e::configure('lhtml')->locations;
		
		// Make sure path contains valid controller name
		if(!isset($path[0]) || $path[0] == '')
			$path = array('index');
		
		// Get the lhtml name
		$name = strtolower(implode('/', $path));
		
		e\Trace(__CLASS__, "Looking for $name.lhtml");
		
		// Check all dirs for a matching lhtml
		foreach($dirs as $dir) {
			// Look in lhtml folder
			if(basename($dir) !== 'lhtml')
				$dir .= '/lhtml';
			
			// Skip if missing
			if(!is_dir($dir))
				continue;
			
			$matched = false;	$vars = array();	$nodir = false; $badmatch = false;
			$p = 1;
			foreach($path as $key => $segment) {
	 			if($matched == 'file') $vars[] = $segment;
	 			if((!$matched || $matched == 'dir') && is_dir("$dir/$segment")) {
					$dir .= "/$segment";
					$matched = 'dir';
				}
				elseif(is_file("$dir/$segment.lhtml")) {
					$file = "$dir/$segment.lhtml";
					$matched = 'file';
				}
				elseif($matched != 'file' && $matched != 'dir') {
					$badmatch = true;
					break;
				}
			}
			
			if($matched == 'dir' && !$badmatch && is_file("$dir/index.lhtml")) {
				$file = "$dir/index.lhtml";
				$matched = 'index';
			}

			# no match at all, just continue
			if($matched == false) continue;
			
			# set the url vars to use
			self::$url_vars = $vars;

			/**
			 * Parse the LHTML file
			 * @author Nate Ferrero
			 */
			$start = microtime(true);
			$out = e::$lhtml->file($file)->parse(null, true)->build();
			$end = microtime(true);
			$time = ($end - $start) * 1000;

			// Show debug time if set
			if(isset($_GET['--lhtml-time'])) {
				

				// $file $time
				eval(d);
			}

			/**
			 * Output the header
			 * @author Nate Ferrero
			 */
			header('Content-Type: ' . Instance::$contentType . '; charset=utf-8');

			/**
			 * HACK
			 * Since double quotes aren't parsed correctly (and &doesnt; work in some cases)
			 * @author Nate Ferrero
			 * @todo Find a better solution for this!
			 */
			$out = str_replace(array('-#-'), array('&quot;'), $out);
			echo $out;

			// Complete the page load
			e\Complete();
		}
	}

	/**
	 * Clean up te LHTML cache
	 * @author Nate Ferrero
	 */
	public function _on_e_command_update($dir) {
		$dir .= '/lhtml';
		foreach(glob("$dir/*") as $file)
			if(!unlink($file)) return "Error: Unable to clear LHTML cache";
		return "LHTML cache cleared";
	} 
}

class Instance {

	/**
	 * @todo Clean up, possibly move to url bundle
	 */
	public function _get_special_vars($matcher) {
		switch($matcher) {
			case ':id' :
				if(isset(Bundle::$url_vars[0]) && is_numeric(Bundle::$url_vars[0])) return Bundle::$url_vars[0];
			break;
			case ':slug':
				if(isset(Bundle::$url_vars[0])) return Bundle::$url_vars[0];
			break;
			case ':urlVars':
				if(isset(Bundle::$url_vars[0])) return Bundle::$url_vars;
			break;
		}
		return null;
	}
	
	private $file = null;
	private $string = null;
	private $description = '{unknown}';
	public $stack;
	public static $contentType = 'text/html';

	public function file($file) {
		$this->file = $file;
		$this->string = null;
		$this->description = $file;
		if($this->stack)
			unset($this->stack);
		return $this;
	}
	
	public function string($string, $description = '{string}', $timestamp = null) {
		$this->string = $string;
		$this->file = null;
		$this->description = $description;
		if($this->stack)
			unset($this->stack);
		return $this;
	}

	public function evaluate($condition, $data) {
		$scope = new Scope;
		if(is_array($data)) {
			foreach($data as $key => $value) {
				$scope->$key = $value;
			}
		}
		return $scope->evaluate($condition);
	}

	public function evaluateString($string, $data = array()) {
		$stack = $this->string($string)->parse();
		$scope = $stack->_data();
		foreach($data as $key => $value) {
			$scope->$key = $value;
		}
		$stack->_ready();
		return $stack->build();
	}

	/**
	 * Override content type
	 * @author Nate Ferrero
	 */
	public function setContentType($type) {
		self::$contentType = $type;
	}
	
	/**
	 * Parse the loaded file
	 * @author Nate Ferrero
	 */
	public function parse($rparent = null, $topLevel = false) {
		if(is_null($this->file) && is_null($this->string))
			throw new Exception("LHTML: No file or string specified to parse");

		if(is_null($parent))
			$parent = new Node;

		if(!($parent instanceof Node))
			throw new Exception("Parent is not a Node");
		
		if(!is_null($this->file)) {

			/**
			 * Check cache for existing stack
			 * @author Nate Ferrero
			 */
			$ctime = e::$cache->timestamp('lhtml', $this->file);
			if(isset($_GET['--lhtml-no-cache']) || $ctime === false || filemtime($this->file) > $ctime) {

				/**
				 * Actually parse the file
				 * @author Nate Ferrero
				 */
				$this->stack = Parser::parseFile($this->file, $this->description, $parent);

				/**
				 * Store in cache
				 * @author Nate Ferrero
				 */
				e::$cache->store('lhtml', $this->file, $this->stack);
			}

			/**
			 * Get the stack from the cache
			 */
			if(empty($this->stack)) {
				$uncache = e::$cache->get('lhtml', $this->file);
				if(!($uncache instanceof Node))
					throw new Exception("Cached LHTML stack is not a Node");
				//$uncache->appendTo($parent);
				$this->stack = $uncache;
			}

			unset($this->file);
		}
		else if(!is_null($this->string)) {

			/**
			 * Todo: add caching for strings
			 * @author Nate Ferrero
			 */
			$this->stack = Parser::parseString($this->string, $this->description, $parent);
			unset($this->string);
		}

		/**
		 * Handle stack ready
		 * @author Nate Ferrero
		 */
		$stack = $this->stack;
		if($rparent instanceof Node) {
			$stack->appendTo($rparent);
			$stack = $rparent;
		}

		$this->stack = $stack;
		/**
		 * This creates a loop that either returns or readies the new stack, as needed
		 * @author Nate Ferrero
		 */
		while($topLevel) {
			try {
				$this->stack->_ready();
				e\trace("LHTML Top-Level Render", '', $this->stack->children);
				return $this->stack;
			}
			catch(UseAlternateStack $use) {
				e\trace_exception($use);
				$this->stack = $use->stack;
			}
		}

		return $this->stack;
	}

}

/**
 * Use alternate stack exception
 * @author Nate Ferrero
 */
class UseAlternateStack extends Exception {
	public $stack;
}

/**
 * Allow access to the e stack in LHTML
 */
class e_handle {
	
	public function __call($method, $args) {
		$method = strtolower($method);
		if(!empty($args)) {
			$method = "e::$method";
			return call_user_func_array($method, $args);
		}
		
		if(!isset(e::$$method))
			throw new Exception("Bundle `$method` is not installed");

		return e::$$method;
	}
	
}