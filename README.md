
<a align="center"><img src="https://github.com/GamakCZ/1vs1/blob/master/icon.png?raw=true" height=20%></img></a>
<h1 align="center">1vs1</h1>

<div align="center">
	<a href="https://discord.gg/uwBf2jS">
        <img src="https://img.shields.io/badge/chat-on%20discord-7289da.svg" alt="discord">
    </a>
    <a href="https://github.com/GamakCZ/OneVsOne/blob/master/LICENSE">
        <img src="https://img.shields.io/badge/license-Apache%20License%202.0-yellowgreen.svg" alt="license">
    </a>
    <a href="https://poggit.pmmp.io/ci/GamakCZ/OneVsOne/OneVsOne">
        <img src="https://poggit.pmmp.io/ci.shield/GamakCZ/1vs1/1vs1" alt="poggit-ci">
    </a>
    <br><br>
    ✔️ Simple setup
    <br>
    ✔️Multi arena support
    <br>
    ✔️ Fast, without lags
    <br>
    ✔️ Last PocketMine API support
    <br>
    
</div>

### Releases:

| Version | Zip Download | Phar Download |
| --- | --- | --- |
| 1.0.0 | [GitHub](https://github.com/GamakCZ/1vs1/archive/1.0.0.zip) | [GitHub](https://github.com/GamakCZ/1vs1/releases/download/1.0.0/1vs1_v1.0.0.phar) |
<br>

- **Other released versions [here](https://github.com/GamakCZ/1vs1/releases)**
- **All developement builds on poggit [here](https://poggit.pmmp.io/ci/GamakCZ/1vs1/1vs1)**

<div align="center">
	<h2>How to setup?</h2>
</div>

 - <h3>Installation:</h3>
 1. Download latest release or sucess. build
 2. Upload it to your server folder /plugins/
 3. Restart the server

-  <h3>Create and setup an arena:</h3>
1. Create an arena using `/1vs1 create <arenaName>`
2. Join the setup mode (command `/1vs1 set <arenaName>`)
3. There are setup commands (they are without `/`), you can use them to set the arena

- _Setup commands_:

| Command | Description |
| --- | --- |
| help | Displays all setup commands |
| done | Is used to exit setup mode |
| level `<levelName>` | Sets arena game level |
| spawn `<spawnNum>` | Sets arena spawn position |
| joinsign | Update joinsign |
| enable | Enable the arena |

<div align="center">
	<h2>Commands:</h2>
</div>
<br>

<p align="center">  

```yaml
Commands:
    /1vs1 help:
        Description: Displays all OneVsOne commands
        Permission: 1vs1.cmd.help (OP)
    /1vs1 create:
        Description: Create new arena
        Permission: 1vs1.cmd.create (OP)
        Usage: /1vs1 set <arenaName>
    /1vs1 remove:
        Description: Remove arena
        Permission: 1vs1.cmd.remove (OP)
        Usage: /1vs1 remove <arenaName>
        Note: Changes will be after restart
    /1vs1 set:
        Description: Command allows setup arena
        Permission: 1vs1.cmd.set (OP)
        Usage: /1vs1 set <arenaName>
        Note: This command can be used only in-game
    /1vs1 arenas:
        Description: Displays list of all arenas
        Permission: 1vs1.cmd.arenas (OP)
```
</p>

<div align="center">
	<h2>Permissions</h2>
</div>
<br>

<p align="center">

```yaml
1vs1.cmd:  
    description: Permissions for all OneVsOne commands
    default: op  
    children:  
        1vs1.cmd.help:
            description: Permission for /1vs1 help  
            default: op  
        1vs1.cmd.create:  
            description: Permission for /1vs1 create  
            default: op
        1vs1.cmd.remove:
            description: Permission for /1vs1 remove
            default: op
        1vs1.cmd.set:  
            description: Permission for /1vs1 set  
            default: op  
        1vs1.cmd.arenas:  
            description: Permission for /1vs1 arenas  
            default: op    
			
```
</p>

<div align="center">
	<h2>API</h2>
</div>
<br>

<h3>Events:</h3>

- [PlayerArenaWinEvent](https://github.com/GamakCZ/1vs1/blob/master/1vs1/src/onevsone/event/PlayerArenaWinEvent.php)

```php
/**  
 * Arena constructor.
 * @param Server $server  
 * @param Plugin $plugin  
 */
 public function __construct(Server $server, Plugin $plugin) {  
    $server->getPluginManager()->registerEvents($this, $plugin);  
 }  
  
/**  
 * @param PlayerArenaWinEvent $event  
 */
 public function onWin(PlayerArenaWinEvent $event) {  
    $player = $event->getPlayer();  
    $this->addCoins($player, 100);  
    $player->sendMessage("§a> You won 100 coins!");  
 }  
		
/**  
 * @param Player $player  
 * @param int $coins  
 */
 public function addCoins(Player $player, int $coins) {}
```

<div align="center">
    <h2>Credits</h2>
</div>

<div>- Icon made by <a href="https://www.flaticon.com/authors/nikita-golubev" title="Nikita Golubev">Nikita Golubev</a> from <a href="https://www.flaticon.com/" 			    title="Flaticon">www.flaticon.com</a> is licensed by <a href="http://creativecommons.org/licenses/by/3.0/" 			    title="Creative Commons BY 3.0" target="_blank">CC 3.0 BY</a></div>

