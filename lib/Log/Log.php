<?php
	require_once("LogEntry.php");


	class Log{
		private static $history;
		
		public static function add(LogEntry $entry){
			self::$history[] = $entry;
		}
		
		public static function debug($message){
			if(!is_string($message) && !is_int($message) && !is_double($message) && !is_float($message))
				$message = print_r($message, true);
			
			self::$history[] = new LogEntry(__FILE__, $message, 1);
		}
		
		public static function toHtml($maxVerbosity){
			$log = self::getLogEntriesByFile();
			
			$out = "<ul>";
			foreach($log as $file=>$entries){
				$out .= "<li><h1>$file</h1><ul>";
				foreach($entries as $entry){
					if($entry->getVerbosity() > $maxVerbosity)
						continue;
						
					$out .= "<li>" . nl2br($entry->getMessage()) . "</li>";
				}
				$out .= "</ul></li>";
			}
			$out .= "</ul>";
			
			return $out;
		}
		
		public static function toText(){
			$log = self::getLogEntriesByFile($maxVerbosity);
			
			foreach($log as $file=>$entries){
				$out .= "$file";
				foreach($entries as $entry){
					if($entry->getVerbosity() > $maxVerbosity)
						continue;
						
					$out .= "\t" . $entry->getMessage();
				}
			}
			
			return $out;
		}
		
		private static function getLogEntriesByFile(){
			foreach(self::$history as $entry){
				$files[$entry->getFile()][] = $entry;
			}
			
			return $files;
		}
	}
?>