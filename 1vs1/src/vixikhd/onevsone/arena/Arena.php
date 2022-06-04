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

use pocketmine\block\BlockLegacyIds;
use pocketmine\block\tile\Tile;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\item\ItemFactory;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;
use vixikhd\onevsone\event\PlayerArenaWinEvent;
use vixikhd\onevsone\event\PlayerEquipEvent;
use vixikhd\onevsone\math\Vector3;
use vixikhd\onevsone\OneVsOne;

/**
 * Class Arena
 *
 * @package onevsone\arena
 */
class Arena implements Listener
{

    public const MSG_MESSAGE = 0;
    public const MSG_TIP = 1;
    public const MSG_POPUP = 2;
    public const MSG_TITLE = 3;
    public const PHASE_LOBBY = 0;
    public const PHASE_GAME = 1;
    public const PHASE_RESTART = 2;

    /** @var OneVsOne $plugin */
    public OneVsOne $plugin;

    /** @var ArenaScheduler $scheduler */
    public ArenaScheduler $scheduler;

    /** @var int $phase */
    public int $phase = 0;

    /** @var array $data */
    public array $data = [];

    /** @var bool $setting */
    public bool $setup = false;

    /** @var Player[] $players */
    public array $players = [];

    /** @var Player[] $toRespawn */
    public array $toRespawn = [];

    /** @var World|null $level */
    public ?World $level = null;

    /** @var string $kit */
    public string $kit;

    /**
     * Arena constructor.
     *
     * @param OneVsOne $plugin
     * @param array $arenaFileData
     */
    public function __construct(OneVsOne $plugin, array $arenaFileData)
    {
        $this->plugin = $plugin;
        $this->data = $arenaFileData;
        $this->setup = !$this->enable(false);
        $this->plugin->getScheduler()->scheduleRepeatingTask($this->scheduler = new ArenaScheduler($this), 20);
        if ($this->setup) {
            if (empty($this->data)) {
                $this->createBasicData();
            }
        } else {
            $this->loadArena();
        }
    }

    /**
     * @param bool $loadArena
     *
     * @return bool $isEnabled
     */
    public function enable(bool $loadArena = true): bool
    {
        if (empty($this->data)) {
            return false;
        }
        if ($this->data['level'] === null) {
            return false;
        }
        if (!$this->plugin->getServer()->getWorldManager()->isWorldGenerated($this->data['level'])) {
            return false;
        }
        if (!$this->plugin->getServer()->getWorldManager()->isWorldLoaded($this->data['level'])) {
            $this->plugin->getServer()->getWorldManager()->loadWorld($this->data['level']);
        }
        $this->level = $this->plugin->getServer()->getWorldManager()->getWorldByName($this->data['level']);
        if (!is_int($this->data['slots'])) {
            return false;
        }
        if (!is_array($this->data['spawns'])) {
            return false;
        }
        if (count($this->data['spawns']) !== $this->data['slots']) {
            return false;
        }
        if (!is_array($this->data['joinsign'])) {
            return false;
        }
        if (count($this->data['joinsign']) !== 2) {
            return false;
        }
        $this->data['enabled'] = true;
        $this->setup = false;
        if ($loadArena) {
            $this->loadArena();
        }
        return true;
    }

    /**
     * @param bool $restart
     */
    public function loadArena(bool $restart = false): void
    {
        if (!$this->data['enabled']) {
            $this->plugin->getLogger()->error('Can not load arena: Arena is not enabled!');
            return;
        }
        if (!$restart) {
            $this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
            if (!$this->plugin->getServer()->getWorldManager()->isWorldGenerated($this->data['level'])) {
                $this->plugin->getServer()->getWorldManager()->loadWorld($this->data['level']);
            }
            $this->level = $this->plugin->getServer()->getWorldManager()->getWorldByName($this->data['level']);
        } else {
            $this->scheduler->reloadTimer();
        }
        if (!$this->level instanceof World) {
            $this->level = $this->plugin->getServer()->getWorldManager()->getWorldByName($this->data['level']);
        }
        $keys = array_keys($this->plugin->dataProvider->config['kits']);
        $this->kit = $keys[array_rand($keys)];
        $this->phase = static::PHASE_LOBBY;
        $this->players = [];
    }

    private function createBasicData(): void
    {
        $this->data = ['level' => null, 'slots' => 2, 'spawns' => [], 'enabled' => false, 'joinsign' => []];
    }

    public function startGame(): void
    {
        $players = [];
        foreach ($this->players as $player) {
            $players[$player->getName()] = $player;
        }
        $this->players = $players;
        $this->phase = 1;
        $this->broadcastMessage('Match Started!', self::MSG_TITLE);
    }

    public function broadcastMessage(string $message, int $id = 0, string $subMessage = ''): void
    {
        foreach ($this->players as $player) {
            switch ($id) {
                case self::MSG_MESSAGE:
                    $player->sendMessage($message);
                    break;
                case self::MSG_TIP:
                    $player->sendTip($message);
                    break;
                case self::MSG_POPUP:
                    $player->sendPopup($message);
                    break;
                case self::MSG_TITLE:
                    $player->sendTitle($message, $subMessage);
                    break;
            }
        }
    }

    public function startRestart(): void
    {
        $player = null;
        foreach ($this->players as $p) {
            $player = $p;
        }
        if ((!$player instanceof Player) || (!$player->isOnline())) {
            $this->phase = self::PHASE_RESTART;
            return;
        }
        $player->sendTitle('§aYOU WON!');
        $event = new PlayerArenaWinEvent($this->plugin, $player, $this);
        $event->call();
        $this->plugin->getServer()->broadcastMessage("§a[1vs1] Player {$player->getName()} won the match at {$this->level->getFolderName()}!");
        $this->phase = self::PHASE_RESTART;
    }

    public function checkEnd(): bool
    {
        return count($this->players) <= 1;
    }

    public function onMove(PlayerMoveEvent $event): void
    {
        if ($this->phase !== self::PHASE_LOBBY) {
            return;
        }
        $player = $event->getPlayer();
        if ($this->inGame($player)) {
            $index = null;
            foreach ($this->players as $i => $p) {
                if ($p->getId() === $player->getId()) {
                    $index = $i;
                }
            }
            if ($event->getPlayer()->getLocation()->asVector3()->distance(Vector3::fromString($this->data['spawns'][$index])) > 1) {
                $player->teleport(Vector3::fromString($this->data['spawns'][$index]));
            }
        }
    }

    public function inGame(Player $player): bool
    {
        if ($this->phase === self::PHASE_LOBBY) {
            $inGame = false;
            foreach ($this->players as $players) {
                if ($players->getId() === $player->getId()) {
                    $inGame = true;
                }
            }
            return $inGame;
        }
        return isset($this->players[$player->getName()]);
    }

    public function onExhaust(PlayerExhaustEvent $event): void
    {
        $player = $event->getPlayer();
        if (!$player instanceof Player) {
            return;
        }
        if (!$this->plugin->dataProvider->config['hunger'] && $this->inGame($player)) {
            $event->cancel();
        }
    }

    public function onInteract(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();
        $block = $event->getBlock();

        if ($this->inGame($player) && $event->getBlock()->getId() === BlockLegacyIds::CHEST) {
            $event->cancel();
            return;
        }

        if (!$block->getPosition()->getWorld()->getTile($block->getPosition()) instanceof Tile) {
            return;
        }

        $signPos = Position::fromObject(Vector3::fromString($this->data['joinsign'][0]), $this->plugin->getServer()->getWorldManager()->getWorldByName($this->data['joinsign'][1]));

        if ((!$signPos->equals($block->getPosition())) || $signPos->getWorld()->getId() !== $block->getPosition()->getWorld()->getId()) {
            return;
        }

        if ($this->phase === self::PHASE_GAME) {
            $player->sendMessage('§c> Arena is in-game');
            return;
        }

        if ($this->phase === self::PHASE_RESTART) {
            $player->sendMessage('§c> Arena is restarting!');
            return;
        }

        if ($this->setup) {
            return;
        }

        $this->joinToArena($player);
    }

    public function joinToArena(Player $player): void
    {
        if (!$this->data['enabled']) {
            $player->sendMessage('§c> Arena is under setup!');
            return;
        }
        if (count($this->players) >= $this->data['slots']) {
            $player->sendMessage('§c> Arena is full!');
            return;
        }
        if ($this->inGame($player)) {
            $player->sendMessage('§c> You are already in queue!');
            return;
        }
        $selected = false;
        for ($lS = 1; $lS <= $this->data['slots']; $lS++) {
            if ($selected || isset($this->players[$index = "spawn-$lS"])) {
                continue;
            }
            $player->teleport(Position::fromObject(Vector3::fromString($this->data['spawns'][$index]), $this->level));
            $this->players[$index] = $player;
            $selected = true;
        }
        $this->broadcastMessage("§a> Player {$player->getName()} joined the match! §7[" . count($this->players) . "/{$this->data['slots']}]");
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $player->setGamemode(GameMode::ADVENTURE());
        $player->setHealth(20);
        $player->getHungerManager()->setFood(20);
        $player->setImmobile();
        $inv = $player->getArmorInventory();
        if (empty($this->plugin->dataProvider->config['kits']) || !is_array($this->plugin->dataProvider->config['kits'])) {
            $inv->setHelmet(VanillaItems::DIAMOND_HELMET());
            $inv->setChestplate(VanillaItems::DIAMOND_CHESTPLATE());
            $inv->setLeggings(VanillaItems::DIAMOND_LEGGINGS());
            $inv->setBoots(VanillaItems::DIAMOND_BOOTS());
            $player->getInventory()->addItem(VanillaItems::IRON_SWORD());
            $apples = VanillaItems::GOLDEN_APPLE();
            $apples->setCount(5);
            $player->getInventory()->addItem($apples);
            $event = new PlayerEquipEvent($this->plugin, $player, $this);
            $event->call();
            return;
        }
        $kitData = $this->plugin->dataProvider->config['kits'][$this->kit];
        if (isset($kitData['helmet'])) {
            $inv->setHelmet($this->getItemFactory()->get($kitData['helmet'][0], $kitData['helmet'][1], $kitData['helmet'][2]));
        }
        if (isset($kitData['chestplate'])) {
            $inv->setChestplate($this->getItemFactory()->get($kitData['chestplate'][0], $kitData['chestplate'][1], $kitData['chestplate'][2]));
        }
        if (isset($kitData['leggings'])) {
            $inv->setLeggings($this->getItemFactory()->get($kitData['leggings'][0], $kitData['leggings'][1], $kitData['leggings'][2]));
        }
        if (isset($kitData['boots'])) {
            $inv->setBoots($this->getItemFactory()->get($kitData['boots'][0], $kitData['boots'][1], $kitData['boots'][2]));
        }
        foreach ($kitData as $slot => [$id, $damage, $count]) {
            if (is_numeric($slot)) {
                $slot = (int)$slot;
                $player->getInventory()->setItem($slot, $this->getItemFactory()->get($id, $damage, $count));
            }
        }
        $event = new PlayerEquipEvent($this->plugin, $player, $this);
        $event->call();
    }

    public function getItemFactory(): ItemFactory
    {
        return ItemFactory::getInstance();
    }

    public function onDeath(PlayerDeathEvent $event): void
    {
        $player = $event->getPlayer();
        if (!$this->inGame($player)) {
            return;
        }
        foreach ($event->getDrops() as $item) {
            $player->getWorld()->dropItem($player->getLocation(), $item);
        }
        $this->toRespawn[$player->getName()] = $player;
        $this->disconnectPlayer($player, '', true);
        $this->broadcastMessage("§a> {$this->plugin->getServer()->getLanguage()->translate($event->getDeathMessage())} §7[" . count($this->players) . "/{$this->data['slots']}]");
        $event->setDeathMessage('');
        $event->setDrops([]);
    }

    public function disconnectPlayer(Player $player, string $quitMsg = '', bool $death = false): void
    {
        if ($this->phase === self::PHASE_LOBBY) {
            $index = '';
            foreach ($this->players as $i => $p) {
                if ($p->getId() === $player->getId()) {
                    $index = $i;
                }
            }
            if ($index !== '') {
                unset($this->players[$index]);
            }
        } else {
            unset($this->players[$player->getName()]);
        }
        $player->getEffects()->clear();
        $player->setGamemode($this->plugin->getServer()->getGamemode());
        $player->setHealth(20);
        $player->getHungerManager()->setFood(20);
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $player->setImmobile(false);
        $player->teleport($this->plugin->getServer()->getWorldManager()->getDefaultWorld()?->getSpawnLocation());
        if (!$death) {
            $this->broadcastMessage("§a> Player {$player->getName()} left the match. §7[" . count($this->players) . "/{$this->data['slots']}]");
        }
        if ($quitMsg !== '') {
            $player->sendMessage("§a> $quitMsg");
        }
    }

    public function onRespawn(PlayerRespawnEvent $event): void
    {
        $player = $event->getPlayer();
        if (isset($this->toRespawn[$player->getName()])) {
            $event->setRespawnPosition($this->plugin->getServer()->getWorldManager()->getDefaultWorld()?->getSpawnLocation());
            unset($this->toRespawn[$player->getName()]);
        }
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        if ($this->inGame($event->getPlayer())) {
            $this->disconnectPlayer($event->getPlayer());
        }
    }

    public function onLevelChange(EntityTeleportEvent $event): void
    {
        $player = $event->getEntity();
        $to = $event->getTo();
        if (!$player instanceof Player) {
            return;
        }
        if ($this->inGame($player) && $to->getWorld() !== $this->level) {
            $this->disconnectPlayer($player, 'You are successfully leaved arena!');
        }
    }

    public function __destruct()
    {
        unset($this->scheduler);
    }
}