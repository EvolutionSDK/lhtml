<?php

namespace Bundles\LHTML\Nodes;
use Bundles\LHTML\Node;
use Bundles\LHTML\Parser;
use Bundles\LHTML\Scope;
use Bundles\LHTML\RebuildWithNewStack;
use Exception;
use e;

/**
 * Jolt quick templating system
 * @author Nate Ferrero
 */
class Jolt extends Node {

	/**
	 * Handle Jolt sections
	 * @author Nate Ferrero
	 */
	public static $sections = array();
	public static $parents = array();

	/**
	 * Save a section stack for later use
	 */
	public static function setSection($section, $file, $stack) {
		/**
		 * Initialize array
		 */
		if(!isset(self::$sections[$section]))
			self::$sections[$section] = array();

		/**
		 * Check if the section is already being included, i.e. won't be used again later
		 */
		if(isset(self::$sections[$section][$file])
			&& self::$sections[$section][$file] == '@default')
			return false;

		/**
		 * Save the section stack
		 */
		self::$sections[$section][$file] = $stack;
		return true;
	}

	/**
	 * Load a section stack
	 */
	public static function getSection($section, $default) {
		if(isset(self::$sections[$section])) {
			/**
			 * If a non-default area has been defined, return it first
			 */
			foreach(array_reverse(array_keys(self::$sections[$section])) as $file)
				return self::$sections[$section][$file];
		} else {
			self::$sections[$section] = array();
		}

		/**
		 * There was no section to get, load the default
		 */
		self::$sections[$section][$default] = '@default';
		return Parser::parseFile($default);
	}
	
	/**
	 * Process the jolt tag
	 * @author Nate Ferrero
	 */
	public function prebuild() {
		$this->element = false;
		$jdata = $this->_data();

		/**
		 * Get current directory
		 * @author Nate Ferrero
		 */
		$dir = realpath(dirname($jdata->__file__));

		/**
		 * Check if this is jolt section logic
		 */
		if(isset($this->attributes['section'])) {
			return $this->doSection();
		}

		/**
		 * Jolt templating
		 * @author Nate Ferrero
		 */
		if(!isset($this->attributes['template']))
			throw new Exception('Jolt template is not specified');

		/**
		 * Load template
		 * @author Nate Ferrero
		 */
		$template = $this->attributes['template'];
		$template = "$dir/$template";
		
		if(pathinfo($template, PATHINFO_EXTENSION) !== 'jolt')
			$template .= '.jolt';
		
		// Get content areas and remove them from the jolt tag
		$contents = $this->detachAllChildren();

		// Load the jolt file
		$stack = Parser::parseFile($template);

		// Process each jolt template and remove them from the output
		$templates = array();
		$jolts = $stack->getElementsByTagName('jolt:templates');
		foreach ($jolts as $jolt) {
			foreach ($jolt->children as $template) {
				$templates[] = $template;
			}
			$jolt->remove();
		}

		// Add the new stack to the jolt template tag
		$stack->appendTo($this);

		// Add attributes as variables in new stack
		foreach ($this->attributes as $key => $value)
			if($key !== ':load')
				$jdata->$key = $this->_string_parse($value, true); /* Second argument means objects will be returned as-is;
				Because these variables are not final output to the page, we don't need them to strictly be strings */
		
		// Assemble template content areas
		foreach ($contents as $child) {
			if(!($child instanceof Node)) {
				if(trim($child) !== '')
					throw new Exception("Cannot place raw content directly inside a `<:jolt>` tag in `$jdata->__file__`");
				continue;
			}

			// Check for content tags
			$tags = $stack->getElementsByTagName($child->fake_element);

			// Move contents to new tags
			foreach ($tags as $tag) {

				$tag->element = false;
				$child->appendTo($tag);

				// Only move to the first tag
				break;
			}
		}

		/**
		 * If there's no templates, return now
		 */
		if(count($templates) == 0)
			return;

		// Apply all templates to remaining elements
		foreach ($templates as $template) {
			if (!($template instanceof Node)) continue;
			$applyTo = $stack->getElementsByTagName($template->fake_element);
			foreach ($applyTo as $applyNow) {
				$applyNow->element = false;
				$applyNow->_data = new Scope($applyNow);

				/**
				 * Add sources that get executed on build
				 */
				foreach ($applyNow->attributes as $key => $value) {
					$applyNow->_data()->addDeferredSource($key, $value);
				}
				
				// First isolate any children in the instance, and keep them for now
				$applyChildren = $applyNow->detachAllChildren();
				
				// Copy template into instance
				foreach ($template->children as $templateChild) {
					if ($templateChild instanceof Node) {
						$newChild = clone $templateChild;
						$newChild->appendTo($applyNow);
					} else {
						$newChild = $templateChild;
						$applyNow->children[] = $newChild;
					}
				}
				
				// Copy children back into content
				$catchAll = array();
				foreach ($applyChildren as $child) {
					if(!($child instanceof Node) || (strpos($child->fake_element, 'jolt:') !== 0)) {
						$catchAll[] = $child;
						continue;
					}
					$tags = $applyNow->getElementsByTagName($child->fake_element);
					$absorbed = false;
					foreach ($tags as $tag) {
						$absorbed = true;
						$tag->element = false;
						$child->element = false;
						$child->appendTo($tag);
						break;
					}
					if(!$absorbed)
						$catchAll[] = $child;
				}

				// Add all remaining items to the catchall
				$tags = $applyNow->getElementsByTagName('jolt:catchall');
				foreach ($tags as $tag) {
					$tag->element = false;
					$tag->absorbAll($catchAll);
					break;
				}
			}
		}
	}
	
	/**
	 * Jolt section logic
	 * @author Nate Ferrero
	 */
	private function doSection() {

		$section = $this->attributes['section'];

		/**
		 * Check if this is an outclude or include
		 */
		if(isset($this->attributes['parent'])) {

			$parent = $this->attributes['parent'];

			$node = new Node('');
			$node->children = $this->children;
			$outclude = self::setSection($section, $jdata->__file__, $node);

			/**
			 * If we are being included instead of outcluding the parent, just return here
			 * @author Nate Ferrero
			 */
			if(!$outclude)
				return;
			
			e\trace("Jolt", "Loading parent `$parent`");

			/**
			 * Include the parent content area if not included
			 */
			$v = "$dir/$parent";
			if(pathinfo($v, PATHINFO_EXTENSION) !== 'lhtml')
				$v .= '.lhtml';

			if($jdata->__file__ == $v)
				throw new Exception("Cannot use a jolt section as it's own parent");

			if(!isset(self::$parents[$v])) {
				self::$parents[$v] = Parser::parseFile($v);
			}

			/**
			 * Rebuild the stack from the parent
			 */
			$rebuild = new RebuildWithNewStack;
			$rebuild->stack = self::$parents[$v];
			throw $rebuild;
		}

		/**
		 * Content section definitions
		 */
		if(isset($this->attributes['content'])) {

			$content = $this->attributes['content'];

			e\trace("Jolt", "Loading content `$content`");

			$v = "$dir/$content";
			if(pathinfo($v, PATHINFO_EXTENSION) !== 'lhtml')
				$v .= '.lhtml';

			/**
			 * Append the section before building
			 */
			$node = self::getSection($section, $v);
			$node->appendTo($this);
			return;
		}

		throw new Exception('Incorrect use of `<:jolt section="'.$section.'">` without providing a `parent` or `content` attribute');
	}

}