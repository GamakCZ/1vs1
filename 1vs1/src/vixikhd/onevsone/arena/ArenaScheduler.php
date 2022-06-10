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

namespace vixikhd\onevsone\arena;

use pocketmine\block\BaseSign;
use pocketmine\block\utils\SignText;
use pocketmine\scheduler\Task;
use pocketmine\world\Position;
use pocketmine\world\sound\AnvilUseSound;
use pocketmine\world\sound\ClickSound;
use pocketmine\world\World;
use vixikhd\onevsone\math\Time;
use vixikhd\onevsone\math\Vector3;

/**
 * Class ArenaScheduler
 *
 * @package onevsone\arena
 */
class ArenaScheduler extends Task
{

	/** @var int $startTime */
	public int $startTime = 10;
	/** @var float|int $gameTime */
	public int|float $gameTime = 20 * 60;
	/** @var int $restartTime */
	public int $restartTime = 10;
	/** @var Arena $plugin */
	protected Arena $plugin;

	/**
	 * ArenaScheduler constructor.
	 *
	 * @param Arena $plugin
	 */
	public function __construct(Arena $plugin)
	{
		$this->plugin = $plugin;
	}

	public function onRun(): void
	{
		$this->reloadSign();

		if ($this->plugin->setup) {
			return;
		}

		switch ($this->plugin->phase) {
			case Arena::PHASE_LOBBY:
				if (count($this->plugin->players) >= 2) {
					$this->plugin->broadcastMessage("§a> Starting in " . Time::calculateTime($this->startTime) . " sec.", Arena::MSG_TIP);
					$this->startTime--;
					if ($this->startTime === 0) {
						$this->plugin->startGame();
						foreach ($this->plugin->players as $player) {
                            				$player->setImmobile(false);
							$this->plugin->level->addSound($player->getLocation(), new AnvilUseSound());
						}
					} else {
						foreach ($this->plugin->players as $player) {
							$this->plugin->level->addSound($player->getLocation(), new ClickSound());
						}
					}
				} else {
					$this->plugin->broadcastMessage("§c> You need more players to start a game!", Arena::MSG_TIP);
					$this->startTime = 10;
				}
				break;
			case Arena::PHASE_GAME:
				$this->plugin->broadcastMessage("§a> There are " . count($this->plugin->players) . " players, time to end: " . Time::calculateTime($this->gameTime), Arena::MSG_TIP);
				if ($this->plugin->checkEnd()) $this->plugin->startRestart();
				$this->gameTime--;
				break;
			case Arena::PHASE_RESTART:
				$this->plugin->broadcastMessage("§a> Restarting in $this->restartTime sec.", Arena::MSG_TIP);
				$this->restartTime--;

				if ($this->restartTime === 0) {
					foreach ($this->plugin->players as $player) {
						$player->teleport($this->plugin->plugin->getServer()->getWorldManager()->getDefaultWorld()?->getSpawnLocation());

						$player->getInventory()->clearAll();
						$player->getArmorInventory()->clearAll();
						$player->getCursorInventory()->clearAll();

						$player->getHungerManager()->setFood(20);
						$player->setHealth(20);
						$player->setImmobile(false);

						$player->setGamemode($this->plugin->plugin->getServer()->getGamemode());
					}
					$this->plugin->loadArena(true);
					$this->reloadTimer();
				}
				break;
		}
	}

	public function reloadSign(): void
	{
		if (!is_array($this->plugin->data["joinsign"]) || empty($this->plugin->data["joinsign"])) {
			return;
		}

		$signPos = Position::fromObject(Vector3::fromString($this->plugin->data["joinsign"][0]), $this->plugin->plugin->getServer()->getWorldManager()->getWorldByName($this->plugin->data["joinsign"][1]));

		if (!$signPos->getWorld() instanceof World) {
			return;
		}

		$signText = ["§2§l1vs1", "§9[ §b? / ? §9]", "§6Setup", "§6Wait few sec..."];
		
		if ($signPos->getWorld()->getTile($signPos) === null) {
			return;
		}

		if ($this->plugin->setup) {
			/** @var BaseSign $sign */
			// TODO: WTF WHY SIGN TEXT ISNT UPDATE
			$sign = $signPos->getWorld()->getTile($signPos);
			$sign->setText(new SignText([$signText[0], $signText[1], $signText[2], $signText[3]]));
			return;
		}

		$signText[1] = "§9[ §b" . count($this->plugin->players) . " / " . $this->plugin->data["slots"] . " §9]";

		switch ($this->plugin->phase) {
			case Arena::PHASE_LOBBY:
				if (count($this->plugin->players) >= $this->plugin->data["slots"]) {
					$signText[2] = "§6Full";
				} else {
					$signText[2] = "§aJoin";
				}

				$signText[3] = "§8Map: §7{$this->plugin->level->getFolderName()}";
				break;
			case Arena::PHASE_GAME:
				$signText[2] = "§5InGame";
				$signText[3] = "§8Map: §7{$this->plugin->level->getFolderName()}";
				break;
			case Arena::PHASE_RESTART:
				$signText[2] = "§cRestarting...";
				$signText[3] = "§8Map: §7{$this->plugin->level->getFolderName()}";
				break;
		}

		/** @var BaseSign $sign */
		// TODO: WTF WHY SIGN TEXT ISNT UPDAT
		$sign = $signPos->getWorld()->getTile($signPos);
		$sign->setText(new SignText([$signText[0], $signText[1], $signText[2], $signText[3]]));
	}

	public function reloadTimer(): void
	{
		$this->startTime = 10;
		$this->gameTime = 20 * 60;
		$this->restartTime = 10;
	}
}
