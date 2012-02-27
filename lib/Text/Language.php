<?php
	abstract class Language{
		abstract function getLanguageId();
		abstract function getLanguageName();
		public function getText($ident, $args = null){
			$return = $this->strings[$ident];

			if(!$return){
				$return = $ident;
			}

			// Allow for argument passing with function overloading
			if(count($args) > 0){
				$return = vsprintf($return, $args);
			}
			
			// Allow for references
			$return = preg_replace_callback('/\{\{(.*?)\}\}/', function($matches){
												$matches = explode("|", $matches[1], 2); // For each match, explode out any arguments
												$name = $matches[0];

												if(count($matches) > 1){
													$args = explode("|", $matches[1]);
												}

												return __g($name, $args);
											}, $return);
			
			while(is_array($return)){
				$return = $return[rand(0, count($return) - 1)];
			}
			
			return $return;
		}
	}
	
	function getLanguageById($id){
		try{
			require_once("../languages/$id.php");
			return new $id;
		}catch(Exception $ex){
			throw new Exception("Language $id not found!");
		}
	}
?>
