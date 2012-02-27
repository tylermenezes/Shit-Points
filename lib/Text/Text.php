<?php
	require_once("Language.php");
	
	class Text{
		private static $language;

		
		public static function setLanguage($id){
			self::$language = getLanguageById($id);
		}
		
		public static function getText($ident, $args = null){
			return self::$language->getText($ident, $args);
		}
		
		public static function showText($ident, $args = null){
			echo self::getText($ident, $args);
		}
	}
	
	function __e($ident){
		$args = func_get_args();
		array_shift($args);
		if(count($args) == 1 && is_array($args[0])){
			$args = $args[1];
		}
		Text::showText($ident, $args);
	}
	
	function __g($ident){
		$args = func_get_args();
		array_shift($args);
		if(count($args) == 1 && is_array($args[0])){
			$args = $args[0];
		}
		return Text::getText($ident, $args);
	}
	
	Text::setLanguage("en_us");
?>
