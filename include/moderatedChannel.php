<?php
class moderatedChannel {
	private $name = '';
	public $settings = array();

	private $banTimers = array();
	private $banLog = array();

	public function __construct($name) {
		$this->name = $name;
		$this->settings = moderation::$defaults;

		$this->loadSettings();
	}

	public function __destruct() {
		$this->saveSettings();
		console("{$this->name}: __destruct()", NIX_YELLOW);
	}

	public function loadSettings() {
		$sfile = MODERATION_CHANNELS_DIR.strtolower($this->name).'.txt';
		if (!file_exists($sfile)) {
			console("{$this->name}: No settings file exists for this channel.", NIX_YELLOW);
			return;
		}
		$time = microtime(true);
		$contents = explode("\r\n", file_get_contents($sfile));

		while (true) {
			if (count($contents) <= 0)
				break;
			$row = array_shift($contents);
			if (trim($row) === '')
				break;

			$line = explode(' => ', $row, 2);
			if (!($line[0] = moderation::getActualSettingName($line[0])))
				continue;

			$this->settings[$line[0]] = moderation::getProperType($line[0], $line[1]);
		}

		while (true) {
			if (count($contents) <= 0)
				break;
			$row = array_shift($contents);
			$this->banLog[] = unserialize($row);
		}

		$timetaken = sprintf("%1.7f",microtime(true)-$time);
		$i = count($this->banLog);
		console("{$this->name}: Settings & banlist loaded in {$timetaken} seconds.");
		console("{$this->name}: Ban count on load: {$i}");
	}

	public function saveSettings() {
		$time = microtime(true);
		$sfile = MODERATION_CHANNELS_DIR.strtolower($this->name).'.txt';
		$lines = array();

		foreach ($this->settings as $k => $s) {
			if ($s !== moderation::$defaults[$k]) {
				$t = moderation::settingToString($s);
				$lines[] = "$k => $t";
			}
		}
		$lines[] = '';

		foreach ($this->banLog as $s)
			$lines[] = serialize($s);

		file_put_contents($sfile, implode("\r\n", $lines));
		$timetaken = sprintf("%1.7f",microtime(true)-$time);
		console("{$this->name}: Settings & banlist saved in {$timetaken} seconds.");
	}



	private function getBan($nick, $host) {
		$nick = strtolower($nick);
		$host = strtolower($host);
		foreach ($this->banLog as &$ban) {
			if (strtolower($ban->username) == $nick || strtolower($ban->host) == $host)
				return $ban;
		}
		return NULL;
	}

	private function getBanKey($nick) {
		$nick = strtolower($nick);
		foreach ($this->banLog as $k=>$ban) {
			if (strtolower($ban->username) == $nick)
				return $k;
		}
		return NULL;
	}

	private function upStrike($nick, $host) {
		if ($ban = &$this->getBan($nick, $host))
			$ban->strike();
		else {
			$ban = new moderatedBan($nick, $host);
			$this->banLog[] = &$ban;
		}

		return ($ban->getStrike());
	}

	private function getStrikeDuration($n) {
		$n-=1;
		$levels = explode(' ',$this->settings['strikeLength']);
		if (isset($levels[$n]))
			return (int)$levels[$n];
		return -1;
	}

	private function ban($nick, $host, $reason) {
		$banhost = "*!*@".$host;
		$s = $this->upStrike($nick, $host);
		if (($sd = $this->getStrikeDuration($s)) > 0) {
			$this->banTimers[$banhost] = time()+$sd;
			$reason .= ' (Ban length: '.getTextTime($sd).')';
		}
		else
			$reason .= ' (Ban persists until manually removed)';
		irc::ban($this->name, $banhost);
		irc::kick($this->name, $nick, $reason);
		
		$this->saveSettings();

		return $s;
	}



	// Commands.
	private function showHelp(&$data) {
		irc::notice($data->nick, "!set: gives you a list of available options.  Use \"!set\" for more info");
		irc::notice($data->nick, "!reset: resets the strikes for a user, putting them back on strike 1.  You still need to manually unset any outstanding bans yourself.");
		irc::notice($data->nick, "!strikes: lists all people with strikes in the channel, or shows strike details of a specific user if given.");
		irc::notice($data->nick, "Wiki quick information reference: http://wiki.srb2.org/wiki/User:Inuyasha/Kikyo#Flood_Protection_.28.23Srb2fun.29");
	}

	private function showMyStrikes(&$data) {
		if ($ban = &$this->getBan($data->nick, $data->host)) {
			$date = date("F j, Y, g:i a", $ban->getBanTime());
			$strike = $ban->getStrike();
			irc::notice($data->nick, "{$ban->username} ({$ban->host}): On strike {$strike}, last ban on {$date}.");
		}
		irc::notice($data->nick, "{$data->nick} ({$data->host}): Not in {$this->name} strike log.");
	}

	private function showStrikes(&$data) {
		if ($data->messageex[1] == NULL) { //no commands
			if (count($this->banLog) <= 0) {
				irc::notice($data->nick, "Nobody in {$this->name} has any active strikes.");
				return true;
			}

			foreach($this->banLog as $log) {
				$date = date("F j, Y, g:i a", $log->getBanTime());
				$strike = $log->getStrike();
				irc::notice($data->nick, "{$log->username} ({$log->host}): On strike {$strike}, last ban on {$date}.");
			}
			return true;
		}

		if ($ban = &$this->getBan($data->messageex[1], $data->messageex[1])) {
			$date = date("F j, Y, g:i a", $ban->getBanTime());
			$strike = $ban->getStrike();
			irc::notice($data->nick, "{$ban->username} ({$ban->host}): On strike {$strike}, last ban on {$date}.");
			return true;
		}
		irc::notice($data->nick, "{$data->messageex[1]}: Not in {$this->name} strike log.");
	}

	private function resetStrikes(&$data) {
		if ($data->messageex[1] == NULL) {
			$this->banLog = array();
			irc::notice("@".$this->name, "All strikes were reset for all users.");
			$this->saveSettings();
		}
		else if (($k = $this->getBanKey($data->messageex[1])) !== NULL) {
			unset($this->banLog[$k]);
			irc::notice("@".$this->name, "User {$data->messageex[1]} has had their strikes reset.");
			$this->saveSettings();
		}
		else
			irc::notice($data->nick, "User {$data->messageex[1]} wasn't found in the ban log.");
		return true;
	}

	private function doSettings(&$data) {
		// List settings
		if (!$data->messageex[1]) {
			$msgs = array();
			foreach($this->settings as $setting => $value) {
				$sstring = moderation::settingToString($value);
				$msgs[] = b().$setting.r()." ({$sstring})";
			}

			$separated = separateList($msgs, 10);

			irc::notice($data->nick, "Settings list for {$this->name}: (Use !set [setting] for more info, and !set [setting] [value] to change a setting's value)");
			foreach($separated as $s) {
				$combined = implode(', ', $s);
				irc::notice($data->nick, $combined);
			}
			return true;
		}
		
		$name = moderation::getActualSettingName($data->messageex[1]);
		if ($name === NULL) {
			irc::notice($data->nick, "No setting with the name \"{$data->messageex[1]}\" exists.");
			return true;
		}
		if (!isset($data->messageex[2]) || $data->messageex[2] === "") { //no value!
			irc::notice($data->nick, b().$name.r().": ".moderation::$help[$name]);
			
			$settingA = moderation::settingToString($this->settings[$name]);
			$settingB = moderation::settingToString(moderation::$defaults[$name]);
			irc::notice($data->nick, "Current setting for {$this->name} is ".b()."{$settingA}".r().".  Default setting is ".b()."{$settingB}".r().".");
			return true;
		}
		
		if (moderation::$types[$name] === "string") {
			array_shift($data->messageex);
			array_shift($data->messageex);
			$this->settings[$name] = implode(' ', $data->messageex);
		}
		else
			$this->settings[$name] = moderation::getProperType($name, $data->messageex[2]);

		$sstring = moderation::settingToString($this->settings[$name]);
		irc::notice("@".$this->name, "Setting \"{$name}\" was changed to ".b()."{$sstring}".r().".");
		$this->saveSettings();
		return true;
	}
	
	// Does checking for flood.
	private $floodLines = array();

	private function floodCheck(&$data) {
		$storenick = strtolower($data->nick);

		if ($this->floodLines[$storenick]) {
			foreach ($this->floodLines[$storenick] as $id => $timestamp) { //check if they're fresh or not now
				if (time()-$timestamp > $this->settings["floodTime"])
					unset($this->floodLines[$storenick][$id]); //not fresh, get rid of it!
			}
		}

		$this->floodLines[$storenick][] = time();

		if (count($this->floodLines[$storenick]) >= $this->settings["floodLines"]) {
			$this->floodLines[$storenick] = array();
			if ($this->settings["longKickMessage"])
				$reason = "Flooding; {$this->settings['floodLines']} lines in under {$this->settings['floodTime']} seconds.";
			else
				$reason = 'Too many consecutive lines, banned for flooding.';
			$strike = $this->ban($data->nick, $data->host, $reason);

			if ($this->settings["alertOps"])
				irc::notice("@".$this->name, "{$data->nick} triggered the flood protection in {$this->name} (strike {$strike})");
			console("{$this->name}: {$data->nick} triggered flood ban.", NIX_YELLOW);
		}
	}
	
	// Does checking for excessive caps
	private $capsLines = array();
	private $capsTime  = array();
	
	private function capsCheck(&$data) {
		$storenick = strtolower($data->nick);

		if ($this->settings["capsForgetTime"] > 0 && $this->capsTime[$storenick] > 0 && time()-$this->capsTime[$storenick] > $this->settings["capsForgetTime"])
			$this->capsLines[$storenick] = 0;

		if (strlen($data->message) >= $this->settings["capsLength"]) {
			if (moderation::capsPercentage($data->message) > $this->settings["capsPercent"]) {
				$this->capsTime[$storenick] = time();
				++$this->capsLines[$storenick];
			}
			else
				$this->capsLines[$storenick] = 0;
		}
		if ($this->capsLines[$storenick] >= $this->settings['capsLines']) {
			$this->capsLines[$storenick] = 0;
			if ($this->settings["longKickMessage"])
				$reason = "Excessive CAPS, {$this->settings['capsLines']} consecutive line(s) with {$this->settings["capsPercent"]}% or more capital letters.";
			else
				$reason = 'Excessive CAPS usage; cool down for a bit.';
			$strike = $this->ban($data->nick, $data->host, $reason);

			if ($this->settings["alertOps"])
				irc::notice("@".$this->name, "{$data->nick} triggered the capslock protection in {$this->name} (strike {$strike})");
			console("{$this->name}: {$data->nick} triggered caps ban.", NIX_YELLOW);
		}
	}



	public function checkBansAndStrikes() {
		foreach($this->banTimers as $hostmask => $timestamp) {
			if ($timestamp < time()) { //expired
				irc::unban($this->name,$hostmask);
				unset($this->banTimers[$hostmask]);
			}
		}

		foreach($this->banLog as $uname => $banobj) {
			if (($res = $banobj->check($this->settings["strikeGraceTime"])) !== 0) {
				if ($res < 0)
					unset($this->banLog[$uname]);
				$this->saveSettings();
			}
		}
	}

	public function onChat(&$data) {
		$calls = strtolower($data->messageex[0]);

		if (irc::hasOp($this->name, $data->nick)) {
			$functionCalls = array(
				'!reset'    => 'resetStrikes',
				'!strikes'	=> 'showStrikes',
				'!help'     => 'showHelp',
				'!set'      => 'doSettings',
			);
			if ($func = $functionCalls[$calls]) {
				if ($this->$func($data))
					return;
			}

			if ($this->settings['ignoreOps'])
				return;
		}
		else if ($calls === '!strikes') {
			$this->showMyStrikes($data);
			return;
		}

		if (irc::hasVoice($this->name, $data->nick) && $this->settings['ignoreVoice'])
			return;
		if (!$this->settings['enabled'])
			return;

		if ($this->settings["floodCheck"])
			$this->floodCheck($data);
		if ($this->settings["capsCheck"])
			$this->capsCheck($data);
	}
}
