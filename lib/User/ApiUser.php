<?php
	require_once("Permissions.php");

	class ApiUser{
		private $id;
		private $permissions;
		private $first_name;
		private $last_name;
		private $gender;
		private $locale;
		private $public_key;
		private $private_key;
		
		function __construct($id){
			$this->id = "api-" . $id;
		}
		
		public function getId(){
			return $this->id;
		}

		private $currentShit = null;
		public function getShit(){
			if($this->currentShit === null){
				$query = "SELECT `points` FROM `users` WHERE `fb_id` = '%s';";
				$newshit = Sql::get(sprintf($query, $this->getId()));
				$this->currentShit = $newshit[0]["points"];
				if(strrpos($this->currentShit, ".") !== false){
					$this->currentShit = substr($this->currentShit, 0, strrpos($this->currentShit, "."));
				}
			}
			return $this->currentShit;
		}

		private function setShit($new){
			$query = "UPDATE `users` SET `points` = %s WHERE `fb_id` = '%s';";
			Sql::exec(sprintf($query, $new, $this->getId()));
			$this->currentShit = $new;
		}
		
		public function getPublicKey(){
			if(!isset($this->public_key)){
				$r = Sql::query(sprintf("SELECT `public` FROM `api_users` WHERE uid = '%s' LIMIT 1;", $this->getId()));
				$this->public_key = $r[0]["public"];
			}
			return $this->public_key;
		}
		
		public function getPrivateKey(){
			if(!isset($this->private_key)){
				$r = Sql::get(sprintf("SELECT `private` FROM `api_users` WHERE uid = '%s' LIMIT 1;", $this->getId()));
				$this->private_key = $r[0]["private"];
			}
			return $this->private_key;
		}
		
		public function checkSignature($method, $vals, $signature){
			return $this->sign($method, $vals) == $signature;
		}
		
		public function sign($method, $vals = null){
			$string = $this->getPrivateKey() . ":" . $method . ":";
			
			if($vals){
				ksort($vals);
				foreach($vals as $key=>$val){
					$string .= $key . "=" . $val . "||";
				}
			}
			$string = substr($string, 0, strlen($string) - 2);
			return hash("sha512", $string);
		}
		
		public function pushShit($amount = 1){
			$this->setShit($this->getShit() + $amount);
		}

		public function popShit($amount = 1){
			$this->setShit($this->getShit() - $amount);
		}

		public function sendShit($amount, $to){
			$u = new User($to);
			$u->pushShit($amount);
			Notification::send(new User($this->id), $u, "notification api " . $this->id);
			$this->popShit($amount);
		}
	}
?>
