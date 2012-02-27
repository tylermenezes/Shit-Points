<?php
	class Cache{
		private static $cache;
		private static $memcacheIdent = "shitpoints_";
		
		public static function init(){
			if(self::$cache)
				return;
				
			self::$cache = new Memcache;
			self::$cache->connect('localhost', 11211);
		}
		
		public static function add($query, $result, $expires = 3600){
			Log::add(new LogEntry(__FILE__, "Adding $query"));
			self::$cache->add(self::$memcacheIdent . self::getQueryIdent($query), $result, null, $expires);
		}
		
		public static function get($query){
			$result = self::$cache->get(self::$memcacheIdent . self::getQueryIdent($query));
			if($result){
				Log::add(new LogEntry(__FILE__, "Cache hit on $query", 2));
			}else{
				Log::add(new LogEntry(__FILE__, "Cache miss on $query", 2));
			}
			return $result;
		}
		
		private static function getQueryIdent($query){
			return md5($query);
		}
		
	}
	
	Cache::init();
?>
