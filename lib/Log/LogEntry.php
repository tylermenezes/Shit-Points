<?php
	class LogEntry{
		private $verbosity;
		private $message;
		private $file;
		
		public function __construct($file, $message, $verbosity = 1){
			$this->file = $file;
			$this->message = $message;
			$this->verbosity = $verbosity;
		}
		
		public function getMessage(){
			return $this->message;
		}
		
		public function getFile(){
			return $this->file;
		}
		
		public function getVerbosity(){
			return $this->verbosity;
		}
	}
?>