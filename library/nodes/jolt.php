<?php

namespace Bundles\LHTML\Nodes;
use Bundles\LHTML\Node;
use Bundles\LHTML\Parser;
use Bundles\LHTML\Scope;
use Exception;

/**
 * Jolt quick templating system
 * @author Nate Ferrero
 */
class Jolt extends Node {
	
	public function prebuild() {
		$this->element = false;
		$jdata = $this->_data();

		/**
		 * Load template
		 * @author Nate Ferrero
		 */
		$dir = realpath(dirname($jdata->__file__));
		$template = $this->attributes['template'];
		$template = "$dir/$template";
		
		if (pathinfo($template, PATHINFO_EXTENSION) !== 'jolt')
			$template .= '.jolt';
		
		// Load the jolt file
		$stack = Parser::parseFile($template);

		// Process each jolt template and remove them from the output
		$jolts = $stack->getElementsByTagName('jolt:templates');
		foreach ($jolts as $jolt) {
			foreach ($jolt->children as $template) {
				$templates[] = $template;
			}
			$jolt->remove();
		}

		// Get new scope object
		$data = $stack->_data();

		// Add the new stack to the jolt template tag
		$stack->appendTo($this);

		// Add attributes as variables in new stack
		foreach ($this->attributes as $key => $value)
			$data->$key = $this->_string_parse($value);
		
		// Assemble template content areas
		foreach ($this->children as $child) {
			if (!($child instanceof Node)) continue;

			// Check for content tags
			$tags = $stack->getElementsByTagName($child->fake_element);

			// Move contents to new tags
			foreach ($tags as $tag) {
				$tag->element = false;
				foreach ($child->children as $subChild) {

					// Clone the node and set the correct new parent
					if ($subChild instanceof Node) {
						$newChild = clone $subChild;
						$newChild->appendTo($tag);
					} else {
						$newChild = $subChild;
						$tag->children[] = $newChild;
					}
				}

				// Remove the used content
				$child->remove();
			}
		}

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
	
}