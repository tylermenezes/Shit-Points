<?php
	require_once("Cache.php");
	
	class Sql{
		private static $sql;
		
		const EXPIRES_NEVER = 0;
		const EXPIRES_NOW = 1;
		
		public static function init(){
			if(self::$sql)
				return;
				
			self::$sql = mysql_connect("localhost", "shitpoints", "fqY2yGM7MN2CDBC5");
			mysql_select_db("shitpoints", self::$sql);
		}
		
		public static function get($query, $expires = self::EXPIRES_NOW){
			$cache_hit = Cache::get($query);
			if($cache_hit){
				return $cache_hit;
			}

			$result = mysql_query($query, self::$sql);
			$error = mysql_error();
			if($error){
				throw new Exception($error . "  -- QUERY -- " . $query);
			}
			
			$out;
			while($row = mysql_fetch_assoc($result)){
				$out[] = $row;
			}
			
			if($expires != self::EXPIRES_NOW){
				Cache::add($query, $out, $expires);
			}
			
			return $out;
		}
		
		public static function exec($query){
			return mysql_query($query, self::$sql) or die(mysql_error());
		}
	}
	
	Sql::init();
?>
