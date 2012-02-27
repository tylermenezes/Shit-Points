<?php
	class Delivery{
		private $id;
		private $to;
		private $from;
		private $data;
		private $when;
		private $redeemed;
		
		private function __construct($id, $to, $from, $data, $when, $redeemed){
			$this->id = $id;
			$this->to = $to;
			$this->from = $from;
			$this->data = $data;
			$this->when = $when;
			$this->redeemed = $redeemed;
		}

		public function getId(){
			return $this->id;
		}

		public function getTo(){
			return $this->to;
		}

		public function getFrom(){
			return $this->from;
		}

		public function getData(){
			return $this->data;
		}

		public function getWhen(){
			return $this->when;
		}

		public function getRedeemed(){
			return $this->redeemed;
		}

		public function redeem(){
			$query = "UPDATE `deliveries` SET `redeemed` = '1' WHERE `id` = '%s' LIMIT 1;";
			Sql::exec(sprintf($query, $this->getId()));
		}

		public static function send($id, $to, $from, $data){
			$query = "INSERT INTO `deliveries` (`id`, `to`, `from`, `data`, `when`) VALUES ('%s', %s, %s, '%s', %s);";
			Sql::exec(sprintf($query, $id, $to->getId(), $from->getId(), $data, time()));
		}

		public static function getFromId($id){
			$query = "SELECT * FROM `deliveries` WHERE `id` = '%s' LIMIT 1;";
			$result = Sql::get(sprintf($query, $id));
			$result = $result[0];
			return new Delivery($result['id'], new User($result['to']), new User($result['from']), $result['data'], $result['when'], $result['redeemed']);
		}
	}
?>
