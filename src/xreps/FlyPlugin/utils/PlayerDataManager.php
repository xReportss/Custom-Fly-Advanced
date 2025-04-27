<?php

declare(strict_types=1);

namespace xreps\FlyPlugin\utils;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use xreps\FlyPlugin\Main;

class PlayerDataManager {
    /** @var Main */
    private Main $plugin;
    
    /** @var Config */
    private Config $playerData;
    
    /** @var array */
    private array $cooldowns = [];
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        
        // Crear el directorio de datos si no existe
        @mkdir($plugin->getDataFolder() . "players");
        
        // Inicializar el archivo de datos de jugadores
        $this->playerData = new Config($plugin->getDataFolder() . "players/data.yml", Config::YAML);
    }
    
    /**
     * Guarda las preferencias de un jugador
     */
    public function savePlayerPreferences(Player $player, string $particleType, string $animationType, string $melodyType, bool $melodyEnabled): void {
        $playerName = strtolower($player->getName());
        
        $data = [
            "particle" => $particleType,
            "animation" => $animationType,
            "melody" => $melodyType,
            "melodyEnabled" => $melodyEnabled
        ];
        
        $this->playerData->set($playerName, $data);
        $this->playerData->save();
    }
    
    /**
     * Carga las preferencias de un jugador
     * @return array [particleType, animationType, melodyType, melodyEnabled]
     */
    public function loadPlayerPreferences(Player $player): array {
        $playerName = strtolower($player->getName());
        
        if ($this->playerData->exists($playerName)) {
            $data = $this->playerData->get($playerName);
            return [
                $data["particle"] ?? $this->plugin->getDefaultParticleType(),
                $data["animation"] ?? $this->plugin->getDefaultAnimationType(),
                $data["melody"] ?? $this->plugin->getDefaultMelodyType(),
                $data["melodyEnabled"] ?? true
            ];
        }
        
        // Valores por defecto si no hay datos guardados
        return [
            $this->plugin->getDefaultParticleType(),
            $this->plugin->getDefaultAnimationType(),
            $this->plugin->getDefaultMelodyType(),
            true
        ];
    }
    
    /**
     * Establece el tiempo de cooldown para un jugador
     */
    public function setCooldown(Player $player): void {
        $this->cooldowns[strtolower($player->getName())] = time();
    }
    
    /**
     * Verifica si un jugador puede usar el comando (si ha pasado el tiempo de cooldown)
     * @return array [canUse, remainingTime]
     */
    public function canUseCommand(Player $player): array {
        $cooldownTime = $this->plugin->getFlyCooldown();
        
        // Si el cooldown está desactivado, siempre puede usar el comando
        if ($cooldownTime <= 0) {
            return [true, 0];
        }
        
        $playerName = strtolower($player->getName());
        
        // Si el jugador tiene el permiso para ignorar el cooldown
        if ($player->hasPermission("flyplugin.bypass.cooldown")) {
            return [true, 0];
        }
        
        // Si el jugador no ha usado el comando antes
        if (!isset($this->cooldowns[$playerName])) {
            return [true, 0];
        }
        
        $lastUsed = $this->cooldowns[$playerName];
        $timePassed = time() - $lastUsed;
        
        if ($timePassed >= $cooldownTime) {
            return [true, 0];
        } else {
            return [false, $cooldownTime - $timePassed];
        }
    }
}
