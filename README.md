XIRC2-Moderation
================

XIRC2 channel moderation bot, formerly running at Esper/#srb2fun

You need the XIRC2 base (STJrInuyasha/XIRC2) to use this.

Setup:
* Place these files in \bots\moderation\.
* Under the `[Bots]` subheader in the configuration file, add: `load[] = "moderation"`
* Create a `[Moderation]` subheader in the configuration file for this module's configuration.

Options:
* `enabled`: Enables or disables all aspects of the moderation bot. If you set this off by default, it can still be turned on afterward on a per-channel basis.
* `ignoreOps`: Ops are ignored by the flood bot, and can spam as much as they like if this is on.  If it's off, they're subject to the same limits and rules as everyone else.
* `ignoreVoice`: Users with +v ignored by the flood bot, and can spam as much as they like if this is on.  If it's off, they're subject to the same limits and rules as everyone else.  Note that if ignore_ops is off, ops will still be subject to the protection mechanisms.
* `alertOps`: Sends an op notice whenever any spam protection is triggered, if enabled.
* `floodCheck`: Enables/disables flood protection.
* `floodLines`: The number of lines it takes for the bot to kickban a person from the channel.
* `floodTime`: The amount of time (in seconds) that the number of lines must be said in -- in slightly easier to understand terms; if flood_lines and flood_time are both set to 6, anyone saying 6 lines in any 6 second period will be kicked/banned.
* `capsCheck`: Enables/disables capital letter spam protection.
* `capsLines`: The number of consistent lines in all caps a person must say for them to be kickbanned from the channel.
* `capsLength`: The minimum length of a line for it to be checked by the caps checker.
* `capsPercent`: The percentage, from 0 to 100, of text that must be in uppercase for the line to be considered abusing caps lock.
* `capsForgetTime`: If a person stays silent for this many seconds, the system will forget how many consecutive all-caps lines they've said.  Use -1 to disable.
* `longKickMessage`: Uses a longer kick message when kicking someone, showing specifically what they did to be kicked.  Turning this off just kicks the person with a generic "You're flooding the channel, cool down for a bit" message.  Keeping this on makes it easy for a person to figure out exactly how to avoid the filters, however.
* `strikeLength`: This is a space-separated list of how long each strike should last, in seconds.  When the end of the list is reached, any further bans are assumed to be permanent.
* `strikeGraceTime`: If a user doesn't trip the spam protection in this many seconds, their strikes are reduced by one.  For reference, 3600 is one hour, 86400 is one day.

Notes:
* Changing config options changes their defaults. You can still change these later per channel with the !set command.
* Channel data is saved in \bots\moderation\channels\#channel.txt.
