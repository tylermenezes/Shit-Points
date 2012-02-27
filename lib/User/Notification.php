<?php
	class Notification{
		private $id;
		private $from;
		private $to;
		private $message;
		private $read;
		private $when;
		
		private function __construct($id, $from, $to, $message, $read, $when){
			$this->id = $id;
			$this->from = $from;
			$this->to = $to;
			$this->message = $message;
			$this->read = $read;
			$this->when = $when;
		}

		public function getId(){
			return $this->id;
		}

		public function getFrom(){
			return new User($this->from);
		}

		public function getTo(){
			return new User($this->to);
		}

		public function getMessage(){
			return $this->message;
		}

		public function getRead(){
			return $this->read;
		}

		public function getWhen(){
			return $this->when;
		}

		public function markRead(){
			$this->read = true;
			$query = "UPDATE `notifications` SET `read` = '1' WHERE `message_id` = %s LIMIT 1;";
			Sql::exec(sprintf($query, $this->getId()));
		}

		public static function getAllToUser($user){
			$query = "SELECT * FROM `notifications` WHERE `to_id` = '%s';";
			$result = Sql::get(sprintf($query, $user->getId()));
			
			if(!$result){
				return false;
			}
			foreach($result as $not){
				$notifications[] = new Notification($not["message_id"], $not["to_id"], $not["from_id"], $not["message"], $not["read"], $not["when"]);
			}

			return $notifications;
		}

		public static function send($from, $to, $message){
			$query = "INSERT INTO `notifications` (`from_id`, `to_id`, `message`, `when`) VALUES ('%s', '%s', '%s', '%s');";
			Sql::exec(sprintf($query, $from->getId(), $to->getId(), mysql_real_escape_string($message), time()));
			$to->incrementCount();
		}
	}
?>
