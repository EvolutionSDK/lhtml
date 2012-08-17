<?php

/**
 * LHTML SSL TAG
 * <:ssl> Tag - Determines SSL Connections
 * # # # # # # # # # # # # # # # # # # # #
 * * * * * * * * * * * * * * * * * * * * *
 * 
 * @author Kelly Lauren Summer Becker
 * @website http://kellybecker.me
 * 
 * * * * * * * *
 * Documentation
 * * * * * * * *
 * 
 * The options for this tag are determined in the Tag name itself using xml namespacing.
 * We are putting the options in the tag name over the attributes because this runs at
 * Tag initialization instead of the output.
 * 
 * There are 2 variables that must be used after the name declaration of :ssl.
 * 
 * The first option is the ssl toggle (on or off) this determines whether or not the
 * page should be rendered as ssl or if it needs to be non-ssl (non-ssl pages load faster).
 * By default this setting is set to on. But you may manually set it to on by running on.
 * 
 * Ex forcing ssl use. <:ssl:on />
 * 
 * If you want a page to be explicitly loaded in a non-ssl format you can set that variable to false.
 * 
 * Ex forcing non-ssl use. <:ssl:off />
 * 
 * I also understand that sometimes your ssl might be on a different domain name. Don't worry I thought
 * about that too. All you need to do is pass the third paramater which is a domain (without http://).
 * 
 * Ex using domain while forcing ssl use. <:ssl:on:domain.com /> or <:ssl::domain.com />
 * Ex using domain while forcing non-ssl use. <:ssl:off:domain.com />
 * 
 **/

namespace Bundles\LHTML\Nodes;
use Bundles\LHTML\Node;
use Exception;
use e;

class SSL extends Node {
	
	public function ready() {
		$this->element = false;

		/**
		 * If were in dev mode disable SSL switch
		 */
		$dev = e::$environment->requireVar('Development.Master', 'yes | no');
		if($dev == 'yes' || $dev === true) return;

		/**
		 * If our site does not support ssl disable switch
		 */
		$nossl = e::$environment->getVar('NoSSL', 'yes | no');
		if($nossl == 'yes' || $nossl === true) return;

		/**
		 * Get Options From Tag Name
		 */
		$opts = explode(':', substr($this->fake_element, 5));
		array_walk($opts,function(&$d){$d=strlen($d)<1?null:$d;});
		list($toggle, $domain) = $opts;

		/**
		 * If no domain was provided then use the HTTP_HOST
		 */		
		if(is_null($domain)) $domain = $_SERVER['HTTP_HOST'];

		/**
		 * If this is domain uses .dev then dont redirect
		 */
		if(array_pop(explode('.', $domain)) == 'dev')
			return;

		if($toggle == 'off') {
			if($_SERVER['HTTPS'] == "on") {
		    	$url = "http://". $domain . $_SERVER['REQUEST_URI'];
				header("Location: $url");
		    	exit;
		    }
		}
		
		else if($toggle == 'on' || is_null($toggle)) {
			if($_SERVER['HTTPS'] != "on") {
		    	$url = "https://". $domain . $_SERVER['REQUEST_URI'];
				header("Location: $url");
		    	exit;
			}
		}
	}
	
}