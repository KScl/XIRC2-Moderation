<?php
class moderatedBan implements Serializable {
	public $username;
	public $host;

	private $strike;

	private $time;
	private $bantime;

	public function __construct($username, $host) {
		$this->username = $username;
		$this->host = $host;
		$this->time = $this->bantime = time();
		$this->strike = 1;
	}
	
	public function getStrike() {
		return $this->strike;
	}
	
	public function getBanTime() {
		return $this->bantime;
	}

	public function strike() {
		++$this->strike;
		$this->time = $this->bantime = time();
	}

	// return:
	// 0 if not expired
	// 1 if expired but more strikes exist
	// -1 if completely expired and needs removal
	public function check($expiretime) {
		if (time() - $this->time >= $expiretime) {
			--$this->strike;
			$this->time = time();

			if ($this->strike <= 0)
				return -1;
			return 1;
		}
		return 0;
	}
	
	// Serialization
	public function serialize() {
		$a[0] = $this->username;
		$a[1] = $this->host;
		$a[2] = $this->strike;
		$a[3] = $this->time;
		$a[4] = $this->bantime;
		return serialize($a);
	}

	public function unserialize($s) {
		$a = unserialize($s);
		$this->username = $a[0];
		$this->host = $a[1];
		$this->strike = $a[2];
		$this->time = $a[3];
		$this->bantime = $a[4];
	}
}
