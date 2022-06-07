<?php

/**
 * Copyright 2018-2019 GamakCZ
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace vixikhd\onevsone;

use JsonException;
use pocketmine\command\Command;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use vixikhd\onevsone\arena\Arena;
use vixikhd\onevsone\commands\OneVsOneCommand;
use vixikhd\onevsone\math\Vector3;
use vixikhd\onevsone\provider\YamlDataProvider;

/**
 * Class OneVsOne
 *
 * @package onevsone
 */
class OneVsOne extends PluginBase implements Listener
{

	/** @var YamlDataProvider */
	public YamlDataProvider $dataProvider;

	/** @var EmptyArenaChooser $emptyArenaChooser */
	public EmptyArenaChooser $emptyArenaChooser;

	/** @var Command[] $commands */
	public array $commands = [];

	/** @var Arena[] $arenas */
	public array $arenas = [];

	/** @var Arena[] $setters */
	public array $setters = [];

	/** @var int[] $setupData */
	public array $setupData = [];

	public function onLoad(): void
	{
		$this->dataProvider = new YamlDataProvider($this);
	}

	public function onEnable(): void
	{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->dataProvider->loadArenas();
		$this->emptyArenaChooser = new EmptyArenaChooser($this);
		$this->getServer()->getCommandMap()->register("1vs1", $this->commands[] = new OneVsOneCommand($this));
	}

    /**
     * @throws JsonException
     */
    public function onDisable(): void
	{
		$this->dataProvider->saveArenas();
	}

	public function onChat(PlayerChatEvent $event): void
	{
		$player = $event->getPlayer();

		if (!isset($this->setters[$player->getName()])) {
			return;
		}

		$event->cancel();
		$args = explode(" ", $event->getMessage());

		$arena = $this->setters[$player->getName()];

		switch ($args[0]) {
			case "help":
				$player->sendMessage("§a> 1vs1 setup help (1/1):\n" . "§7help : Displays list of available setup commands\n" . "§7level : Set arena level\n" . "§7spawn : Set arena spawns\n" . "§7joinsign : Set arena joinsign\n" . "§7enable : Enable the arena");
				break;
			case "level":
				if (!isset($args[1])) {
					$player->sendMessage("§cUsage: §7level <levelName>");
					break;
				}
				if (!$this->getServer()->getWorldManager()->isWorldGenerated($args[1])) {
					$player->sendMessage("§c> Level $args[1] does not found!");
					break;
				}
				$player->sendMessage("§a> Arena level updated to $args[1]!");
				$arena->data["level"] = $args[1];
				break;
			case "spawn":
				if (!isset($args[1])) {
					$player->sendMessage("§cUsage: §7setspawn <int: spawn>");
					break;
				}
				if (!is_numeric($args[1])) {
					$player->sendMessage("§cType number!");
					break;
				}
				if ((int)$args[1] > $arena->data["slots"]) {
					$player->sendMessage("§cThere are only {$arena->data["slots"]} slots!");
					break;
				}

				$arena->data["spawns"]["spawn-{$args[1]}"] = (new Vector3($player->getPosition()->getX(), $player->getPosition()->getY(), $player->getPosition()->getZ()))->__toString();
				$player->sendMessage("§a> Spawn $args[1] set to X: " . (string)round($player->getPosition()->getX()) . " Y: " . (string)round($player->getPosition()->getY()) . " Z: " . (string)round($player->getPosition()->getZ()));
				break;
			case "joinsign":
				$player->sendMessage("§a> Break block to set join sign!");
				$this->setupData[$player->getName()] = 0;
				break;
			case "enable":
				if (!$arena->setup) {
					$player->sendMessage("§6> Arena is already enabled!");
					break;
				}
				if (!$arena->enable()) {
					$player->sendMessage("§c> Could not load arena, there are missing information!");
					break;
				}
				$player->sendMessage("§a> Arena enabled!");
				break;
			case "done":
				$player->sendMessage("§a> You are successfully leaved setup mode!");
				unset($this->setters[$player->getName()]);
				if (isset($this->setupData[$player->getName()])) {
					unset($this->setupData[$player->getName()]);
				}
				break;
			default:
				$player->sendMessage("§6> You are in setup mode.\n" . "§7- use §lhelp §r§7to display available commands\n" . "§7- or §ldone §r§7to leave setup mode");
				break;
		}
	}

	public function onBreak(BlockBreakEvent $event): void
	{
		$player = $event->getPlayer();
		$block = $event->getBlock();
		if (isset($this->setupData[$player->getName()]) && $this->setupData[$player->getName()] === 0) {
			$this->setters[$player->getName()]->data["joinsign"] = [(new Vector3($block->getPosition()->getX(), $block->getPosition()->getY(), $block->getPosition()->getZ()))->__toString(), $block->getPosition()->getWorld()->getFolderName()];
			$player->sendMessage("§a> Join sign updated!");
			unset($this->setupData[$player->getName()]);
			$event->cancel();
		}
	}

	public function joinToRandomArena(Player $player): void
	{
		$arena = $this->emptyArenaChooser->getRandomArena();
		if (!is_null($arena)) {
			$arena->joinToArena($player);
			return;
		}
		$player->sendMessage("§c> All the arenas are full!");
	}
}