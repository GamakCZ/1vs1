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

namespace vixikhd\onevsone\provider;

use pocketmine\level\Level;
use pocketmine\utils\Config;
use vixikhd\onevsone\arena\Arena;
use vixikhd\onevsone\OneVsOne;

/**
 * Class YamlDataProvider
 * @package onevsone\provider
 */
class YamlDataProvider {

    /** @var OneVsOne $plugin */
    private $plugin;

    /** @var array $config */
    public $config;

    /**
     * YamlDataProvider constructor.
     * @param OneVsOne $plugin
     */
    public function __construct(OneVsOne $plugin) {
        $this->plugin = $plugin;
        $this->init();
    }

    public function init() {
        if(!is_dir($this->getDataFolder())) {
            @mkdir($this->getDataFolder());
        }
        if(!is_dir($this->getDataFolder() . "arenas")) {
            @mkdir($this->getDataFolder() . "arenas");
        }
        if(!is_dir($this->getDataFolder() . "saves")) {
            @mkdir($this->getDataFolder() . "saves");
        }
        if(!is_file($this->getDataFolder() . "/config.yml")) {
            $this->plugin->saveResource("/config.yml");
        }
        $this->config = (new Config($this->getDataFolder() . "/config.yml", Config::YAML))->getAll();
        var_dump($this->config);
    }

    public function loadArenas() {
        foreach (glob($this->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . "*.yml") as $arenaFile) {
            $config = new Config($arenaFile, Config::YAML);
            $this->plugin->arenas[basename($arenaFile, ".yml")] = new Arena($this->plugin, $config->getAll(\false));
        }
    }

    public function saveArenas() {
        foreach ($this->plugin->arenas as $fileName => $arena) {
            $config = new Config($this->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $fileName . ".yml", Config::YAML);
            $config->setAll($arena->data);
            $config->save();
        }
    }

    /**
     * @return string $dataFolder
     */
    private function getDataFolder(): string {
        return $this->plugin->getDataFolder();
    }
}
