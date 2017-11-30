<?php
/*
 * Channel moderation bot class for XIRC2.
 *   - Inuyasha
 */

define('MODERATION_DIR', dirname(__FILE__).DIRECTORY_SEPARATOR);
define('MODERATION_INCLUDE_DIR', MODERATION_DIR.'include'.DIRECTORY_SEPARATOR);
define('MODERATION_CHANNELS_DIR', MODERATION_DIR.'channels'.DIRECTORY_SEPARATOR);

require_once(MODERATION_INCLUDE_DIR.'moderatedChannel.php');
require_once(MODERATION_INCLUDE_DIR.'moderatedBan.php');

class moderation implements XIRC_Module {
	static $types = array(
		"enabled"         => "bool",
		"ignoreOps"       => "bool",
		"ignoreVoice"     => "bool",
		"alertOps"        => "bool",
		"floodCheck"      => "bool",
		"floodLines"      => "int",
		"floodTime"       => "int",
		"capsCheck"       => "bool",
		"capsLines"       => "int",
		"capsLength"      => "int",
		"capsPercent"     => "int",
		"capsForgetTime"  => "int",
		"longKickMessage" => "bool",
		"strikeLength"    => "string",
		"strikeGraceTime" => "int",
	);
	static $defaults = array(
		"enabled"         => false,
		"ignoreOps"       => false,
		"ignoreVoice"     => false,
		"alertOps"        => true,
		"floodCheck"      => true,
		"floodLines"      => 7,
		"floodTime"       => 10,
		"capsCheck"       => true,
		"capsLines"       => 5,
		"capsLength"      => 10,
		"capsPercent"     => 80,
		"capsForgetTime"  => -1,
		"longKickMessage" => false,
		"strikeLength"    => "10 60 600 3600",
		"strikeGraceTime" => 86400,
	);
	static $help = array(
		"enabled"         => "Have you tried turning it off and on again?",
		"ignoreOps"       => "Ops are ignored by the flood bot, and can spam as much as they like if this is on.  If it's off, they're subject to the same limits and rules as everyone else.",
		"ignoreVoice"     => "Users with +v ignored by the flood bot, and can spam as much as they like if this is on.  If it's off, they're subject to the same limits and rules as everyone else.  Note that if ignore_ops is off, ops will still be subject to the protection mechanisms.",
		"alertOps"        => "Sends an op notice whenever any spam protection is triggered, if enabled.",
		"floodCheck"      => "Enables/disables flood protection.",
		"floodLines"      => "The number of lines it takes for the bot to kickban a person from the channel.",
		"floodTime"       => "The amount of time (in seconds) that the number of lines must be said in -- in slightly easier to understand terms; if flood_lines and flood_time are both set to 6, anyone saying 6 lines in any 6 second period will be kicked/banned",
		"capsCheck"       => "Enables/disables capital letter spam protection.",
		"capsLines"       => "The number of consistent lines in all caps a person must say for them to be kickbanned from the channel.",
		"capsLength"      => "The minimum length of a line for it to be checked by the caps checker.",
		"capsPercent"     => "The percentage, from 0 to 100, of text that must be in uppercase for the line to be considered abusing caps lock.",
		"capsForgetTime"  => "If a person stays silent for this many seconds, the system will forget how many consecutive all-caps lines they've said.  Use -1 to disable.",
		"longKickMessage" => "Uses a longer kick message when kicking someone, showing specifically what they did to be kicked.  Turning this off just kicks the person with a generic \"You're flooding the channel, cool down for a bit\" message.  Keeping this on makes it easy for a person to figure out exactly how to avoid the filters, however.",
		"strikeLength"    => "This is a space-separated list of how long each strike should last, in seconds.  When the end of the list is reached, any further bans are assumed to be permanent.",
		"strikeGraceTime" => "If a user doesn't trip the spam protection in this many seconds, their strikes are reduced by one.  For reference, 3600 is one hour, 86400 is one day.",
	);

	private $channels = array();

	public function __construct() {
		foreach (self::$defaults as $k=>$d)
			self::$defaults[$k] = config::read('Moderation', strtolower($k), $d);
	}

	public static function capsPercentage($msg) {
		$upr =  strlen($msg) - strlen(preg_replace('/[A-Z]/', '', $msg));
		$all =  strlen($msg) - strlen(preg_replace(array('/[a-z]/', '/[A-Z]/'), '', $msg));
		if ($all <= 1)
			return 0;
		else
			return (int)(($upr/$all)*100);
	}

	public static function settingToString($s) {
		if ($s === true) return "on";
		elseif ($s === false) return "off";
		else return (string)$s;
	}

	public static function getActualSettingName($sname) {
		$keys = array_keys(self::$types);
		foreach ($keys as $k)
			if (!strcasecmp($k, $sname))
				return $k;
		return NULL;
	}

	public static function getProperType($n, $s) {
		switch(self::$types[$n]) {
			case 'bool':
				if ($s{0} === 't' || $s{0} === 'y' || $s{0} === '1' || $s === 'on')
					return true;
				return false;
			case 'int':
				return (int)$s;
			case 'string':
			default:
				return $s;
		}
		return NULL;
	}

	/*
	 * Events
	 */
	public function onIrcInit($myName) {
		events::hook($myName, EVENT_CHANNEL_MESSAGE,   'onChat');
		events::hook($myName, EVENT_SELF_JOIN,  'onSelfJoin');
		events::hook($myName, EVENT_SELF_PART,  'onSelfPart');
	}

	public function onMainLoop() {
		foreach($this->channels as $o)
			$o->checkBansAndStrikes();
	}

	public function onChat(&$data) {
		$ch = strtolower($data->channel);
		if (array_key_exists($ch, $this->channels))
			$this->channels[$ch]->onChat($data);
	}

	public function onSelfJoin(&$data) {
		$ch = strtolower($data->channel);
		if (array_key_exists($ch, $this->channels)) // ?! We already have data.
			return;

		$this->channels[$ch] = new moderatedChannel($data->channel);
	}

	public function onSelfPart(&$data) {
		$ch = strtolower($data->channel);
		if (array_key_exists($ch, $this->channels))
			unset($this->channels[$ch]);
	}
}