<?php

namespace Bundles\LHTML\Nodes;
use Bundles\LHTML\Node;
use Exception;

/**
 * Empty Node
 */
class _empty extends Node {
	
	public function ready() {
		$this->element = false;
	}

	public function build($build = false) {
		if($build === true) return parent::build();
		else return '';
	}
	
}