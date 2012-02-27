<?php
	class Page{
		private $title;
		private $uri;
		private $align;
		
		function __construct($title, $uri, $align="left"){
			$this->title = $title;
			$this->uri = $uri;
			$this->align = $align;
		}
		
		function getTitle(){
			return $this->title;
		}
		
		function getUri(){
			return $this->uri;
		}
		
		function getUriBase(){
			$base = explode("/", $this->getUri());
			return $base[0];
		}
		
		function getAlign(){
			return $this->align;
		}
	}
?>