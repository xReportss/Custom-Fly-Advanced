<?php

declare(strict_types=1);

namespace xreps\FlyPlugin;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xreps\FlyPlugin\commands\FlyCommand;
use xreps\FlyPlugin\commands\ParticleCommand;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\particle\EnchantmentTableParticle;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\WaterParticle;
use pocketmine\world\particle\LavaParticle;
use pocketmine\world\particle\RedstoneParticle;
use pocketmine\world\particle\SmokeParticle;
use pocketmine\world\particle\ExplodeParticle;
use pocketmine\world\particle\BubbleParticle;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\AngryVillagerParticle;
use pocketmine\world\particle\Particle;
use pocketmine\math\Vector3;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\color\Color;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener {
    
    /** @var array */
    private array $flyEnabled = [];
    
    /** @var array */
    private array $playerParticles = [];
    
    /** @var array */
    private array $playerAnimations = [];
    
    /** @var int */
    private int $particleTaskId;
    
    /** @var string */
    private string $defaultParticleType;
    
    /** @var string */
    private string $defaultAnimationType;
    
    /** @var int */
    private int $particleFrequency;
    
    /** @var int */
    private int $particleCount;
    
    /** @var array */
    private array $availableParticles = [
        "heart" => "Corazones",
        "portal" => "Portal",
        "enchant" => "Encantamiento",
        "flame" => "Llamas",
        "water" => "Agua",
        "lava" => "Lava",
        "redstone" => "Redstone",
        "smoke" => "Humo",
        "explode" => "Explosión",
        "bubble" => "Burbujas",
        "happy" => "Aldeano Feliz",
        "angry" => "Aldeano Enojado"
    ];
    
    /** @var array */
    private array $availableAnimations = [
        "circle" => "Círculo",
        "spiral" => "Espiral",
        "helix" => "Hélice",
        "wings" => "Alas",
        "rain" => "Lluvia",
        "random" => "Aleatorio"
    ];
    
    /** @var array */
    private array $animationData = [];
    
    /** @var array */
    private array $cooldowns = [];
    
    /** @var int */
    private int $cooldownTime;
    
    /** @var bool */
    private bool $cooldownEnabled;
    
    /** @var Config */
    private Config $playerData;
    
    /** @var bool */
    private bool $savePlayerData;
    
    public function onEnable(): void {
        $this->getLogger()->info(TF::GREEN . "FlyPlugin habilitado correctamente!");
        
        // Registrar los comandos
        $this->getServer()->getCommandMap()->register("flyplugin", new FlyCommand($this));
        $this->getServer()->getCommandMap()->register("flyplugin", new ParticleCommand($this));
        
        // Crear la carpeta de configuración si no existe
        @mkdir($this->getDataFolder());
        
        // Guardar la configuración por defecto
        $this->saveDefaultConfig();
        
        // Inicializar el archivo de datos de jugadores
        $this->initPlayerData();
        
        // Cargar configuración de partículas
        $this->loadParticleConfig();
        
        // Cargar configuración de cooldown
        $this->loadCooldownConfig();
        
        // Registrar eventos
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        
        // Iniciar tarea de partículas
        $this->startParticleTask();
        
        // Iniciar tarea de guardado automático
        $this->startAutoSaveTask();
    }
    
    public function onDisable(): void {
        $this->getLogger()->info(TF::RED . "FlyPlugin deshabilitado!");
        
        // Cancelar tarea de partículas si existe
        if (isset($this->particleTaskId)) {
            $this->getScheduler()->cancelTask($this->particleTaskId);
        }
        
        // Guardar datos de jugadores
        $this->saveAllPlayerData();
    }
    
    /**
     * Inicializa el archivo de datos de jugadores
     */
    private function initPlayerData(): void {
        $this->playerData = new Config($this->getDataFolder() . "playerdata.yml", Config::YAML, []);
        $this->savePlayerData = (bool)$this->getConfig()->get("save-player-data", true);
    }
    
    /**
     * Inicia la tarea de guardado automático
     */
    private function startAutoSaveTask(): void {
        // Guardar datos cada 15 minutos (18000 ticks)
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function(): void {
                if ($this->savePlayerData) {
                    $this->saveAllPlayerData();
                    $this->getLogger()->debug("Datos de jugadores guardados automáticamente.");
                }
            }
        ), 18000);
    }
    
    /**
     * Carga la configuración de partículas desde config.yml
     */
    private function loadParticleConfig(): void {
        $config = $this->getConfig();
        
        // Cargar tipo de partícula por defecto
        $this->defaultParticleType = strtolower($config->get("default-particle-type", "heart"));
        
        // Cargar tipo de animación por defecto
        $this->defaultAnimationType = strtolower($config->get("default-animation-type", "circle"));
        
        // Cargar frecuencia de partículas en ticks (por defecto: 10 ticks = 0.5 segundos)
        $this->particleFrequency = max(1, $config->get("particle-frequency", 10));
        
        // Cargar cantidad de partículas por jugador (por defecto: 5)
        $this->particleCount = max(1, $config->get("particle-count", 5));
    }
    
    /**
     * Carga la configuración de cooldown desde config.yml
     */
    private function loadCooldownConfig(): void {
        $config = $this->getConfig();
        
        // Cargar si el cooldown está habilitado
        $this->cooldownEnabled = (bool)$config->get("cooldown-enabled", true);
        
        // Cargar tiempo de cooldown en segundos
        $this->cooldownTime = max(1, $config->get("cooldown-time", 30));
    }
    
    /**
     * Inicia la tarea programada para mostrar partículas
     */
    private function startParticleTask(): void {
        $this->particleTaskId = $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function(): void {
                foreach ($this->getServer()->getOnlinePlayers() as $player) {
                    if ($this->hasFlyEnabled($player) && $player->isFlying()) {
                        $playerName = $player->getName();
                        
                        // Obtener tipo de partícula del jugador o usar el predeterminado
                        $particleType = $this->playerParticles[$playerName] ?? $this->defaultParticleType;
                        
                        // Obtener tipo de animación del jugador o usar el predeterminado
                        $animationType = $this->playerAnimations[$playerName] ?? $this->defaultAnimationType;
                        
                        // Verificar permiso para el tipo de partícula
                        if (!$player->hasPermission("flyplugin.particle." . $particleType)) {
                            // Si no tiene permiso, usar la partícula predeterminada
                            $particleType = $this->defaultParticleType;
                        }
                        
                        // Verificar permiso para el tipo de animación
                        if (!$player->hasPermission("flyplugin.animation." . $animationType)) {
                            // Si no tiene permiso, usar la animación predeterminada
                            $animationType = $this->defaultAnimationType;
                        }
                        
                        // Mostrar partículas con la animación seleccionada
                        $this->spawnParticlesWithAnimation($player, $particleType, $animationType);
                    }
                }
            }
        ), $this->particleFrequency)->getTaskId();
    }
    
    /**
     * Genera partículas alrededor de un jugador con la animación especificada
     */
    private function spawnParticlesWithAnimation(Player $player, string $particleType, string $animationType): void {
        $world = $player->getWorld();
        $particle = $this->getParticleByType($particleType);
        
        if ($particle === null) {
            return;
        }
        
        $position = $player->getPosition();
        $playerName = $player->getName();
        
        // Inicializar datos de animación para el jugador si no existen
        if (!isset($this->animationData[$playerName])) {
            $this->animationData[$playerName] = [
                "tick" => 0,
                "height" => 0,
                "angle" => 0,
                "direction" => 1
            ];
        }
        
        // Incrementar contador de ticks para animaciones
        $this->animationData[$playerName]["tick"]++;
        
        // Ejecutar la animación seleccionada
        switch ($animationType) {
            case "circle":
                $this->animateCircle($world, $position, $particle, $this->particleCount);
                break;
            case "spiral":
                $this->animateSpiral($world, $position, $particle, $this->particleCount, $this->animationData[$playerName]);
                break;
            case "helix":
                $this->animateHelix($world, $position, $particle, $this->particleCount, $this->animationData[$playerName]);
                break;
            case "wings":
                $this->animateWings($world, $position, $particle, $this->particleCount, $this->animationData[$playerName]);
                break;
            case "rain":
                $this->animateRain($world, $position, $particle, $this->particleCount);
                break;
            case "random":
                $this->animateRandom($world, $position, $particle, $this->particleCount);
                break;
            default:
                $this->animateCircle($world, $position, $particle, $this->particleCount);
                break;
        }
    }
    
    /**
     * Animación de partículas en círculo
     */
    private function animateCircle($world, $position, $particle, $count): void {
        for ($i = 0; $i < $count; $i++) {
            $angle = 2 * M_PI * $i / $count;
            $x = $position->x + cos($angle) * 0.7;
            $y = $position->y + 0.5;
            $z = $position->z + sin($angle) * 0.7;
            
            $world->addParticle(new Vector3($x, $y, $z), $particle);
        }
    }
    
    /**
     * Animación de partículas en espiral
     */
    private function animateSpiral($world, $position, $particle, $count, &$data): void {
        $tick = $data["tick"];
        
        for ($i = 0; $i < $count; $i++) {
            $angle = 2 * M_PI * $i / $count + $tick * 0.1;
            $radius = 0.7 + sin($tick * 0.1) * 0.3;
            $x = $position->x + cos($angle) * $radius;
            $y = $position->y + 0.5;
            $z = $position->z + sin($angle) * $radius;
            
            $world->addParticle(new Vector3($x, $y, $z), $particle);
        }
    }
    
    /**
     * Animación de partículas en hélice
     */
    private function animateHelix($world, $position, $particle, $count, &$data): void {
        $tick = $data["tick"];
        
        for ($i = 0; $i < $count; $i++) {
            $angle = 2 * M_PI * $i / $count + $tick * 0.1;
            $height = ($i / $count) * 2 - 1; // -1 a 1
            $x = $position->x + cos($angle) * 0.7;
            $y = $position->y + 0.5 + $height;
            $z = $position->z + sin($angle) * 0.7;
            
            $world->addParticle(new Vector3($x, $y, $z), $particle);
        }
    }
    
    /**
     * Animación de partículas en forma de alas
     */
    private function animateWings($world, $position, $particle, $count, &$data): void {
        $tick = $data["tick"];
        $angle = $data["angle"];
        
        // Actualizar ángulo para el movimiento de alas
        $data["angle"] += 0.1;
        if ($data["angle"] > 2 * M_PI) {
            $data["angle"] = 0;
        }
        
        // Factor de aleteo
        $flapFactor = sin($angle) * 0.5 + 0.5; // 0 a 1
        
        // Ala izquierda
        for ($i = 0; $i < $count / 2; $i++) {
            $t = $i / ($count / 2); // 0 a 1
            $wingX = -$t * 1.5 * $flapFactor;
            $wingY = sin($t * M_PI) * 0.7 * $flapFactor;
            $wingZ = 0;
            
            $x = $position->x + $wingX;
            $y = $position->y + 0.5 + $wingY;
            $z = $position->z + $wingZ;
            
            $world->addParticle(new Vector3($x, $y, $z), $particle);
        }
        
        // Ala derecha
        for ($i = 0; $i < $count / 2; $i++) {
            $t = $i / ($count / 2); // 0 a 1
            $wingX = $t * 1.5 * $flapFactor;
            $wingY = sin($t * M_PI) * 0.7 * $flapFactor;
            $wingZ = 0;
            
            $x = $position->x + $wingX;
            $y = $position->y + 0.5 + $wingY;
            $z = $position->z + $wingZ;
            
            $world->addParticle(new Vector3($x, $y, $z), $particle);
        }
    }
    
    /**
     * Animación de partículas en forma de lluvia
     */
    private function animateRain($world, $position, $particle, $count): void {
        for ($i = 0; $i < $count; $i++) {
            $x = $position->x + (mt_rand(-10, 10) / 10);
            $y = $position->y + 1.5;
            $z = $position->z + (mt_rand(-10, 10) / 10);
            
            $world->addParticle(new Vector3($x, $y, $z), $particle);
        }
    }
    
    /**
     * Animación de partículas aleatorias
     */
    private function animateRandom($world, $position, $particle, $count): void {
        for ($i = 0; $i < $count; $i++) {
            $x = $position->x + (mt_rand(-10, 10) / 10);
            $y = $position->y + (mt_rand(-10, 10) / 10);
            $z = $position->z + (mt_rand(-10, 10) / 10);
            
            $world->addParticle(new Vector3($x, $y, $z), $particle);
        }
    }
    
    /**
     * Obtiene el objeto de partícula según el tipo especificado
     */
    private function getParticleByType(string $type): ?Particle {
        switch ($type) {
            case "heart":
                return new HeartParticle();
            case "portal":
                return new PortalParticle();
            case "enchant":
            case "enchantment":
                return new EnchantmentTableParticle();
            case "flame":
                return new FlameParticle();
            case "water":
                return new WaterParticle();
            case "lava":
                return new LavaParticle();
            case "redstone":
                return new RedstoneParticle(new Color(255, 0, 0)); // Rojo
            case "smoke":
                return new SmokeParticle();
            case "explode":
                return new ExplodeParticle();
            case "bubble":
                return new BubbleParticle();
            case "happy":
                return new HappyVillagerParticle();
            case "angry":
                return new AngryVillagerParticle();
            default:
                return new HeartParticle(); // Partícula por defecto
        }
    }
    
    /**
     * Verifica si un jugador tiene el modo vuelo activado
     */
    public function hasFlyEnabled(Player $player): bool {
        return isset($this->flyEnabled[$player->getName()]) && $this->flyEnabled[$player->getName()];
    }
    
    /**
     * Activa el modo vuelo para un jugador
     */
    public function enableFly(Player $player): void {
        $player->setAllowFlight(true);
        $player->setFlying(true);
        $this->flyEnabled[$player->getName()] = true;
        $player->sendMessage(TF::GREEN . "¡Modo vuelo activado!");
        
        // Reproducir sonido de activación
        $this->playSound($player, "mob.bat.takeoff", 1, 1);
        
        // Establecer cooldown
        $this->setCooldown($player);
    }
    
    /**
     * Desactiva el modo vuelo para un jugador
     */
    public function disableFly(Player $player): void {
        $player->setAllowFlight(false);
        $player->setFlying(false);
        $this->flyEnabled[$player->getName()] = false;
        $player->sendMessage(TF::RED . "¡Modo vuelo desactivado!");
        
        // Reproducir sonido de desactivación
        $this->playSound($player, "mob.chicken.plop", 1, 0.8);
        
        // Establecer cooldown
        $this->setCooldown($player);
    }
    
    /**
     * Establece el cooldown para un jugador
     */
    public function setCooldown(Player $player): void {
        if (!$this->cooldownEnabled) {
            return;
        }
        
        $this->cooldowns[$player->getName()] = time();
    }
    
    /**
     * Verifica si un jugador está en cooldown
     * @return bool|int Retorna false si no está en cooldown, o el tiempo restante en segundos
     */
    public function checkCooldown(Player $player) {
        // Si el cooldown está desactivado, siempre retornar false
        if (!$this->cooldownEnabled) {
            return false;
        }
        
        // Si el jugador tiene permiso para omitir el cooldown, retornar false
        if ($player->hasPermission("flyplugin.bypass.cooldown")) {
            return false;
        }
        
        $playerName = $player->getName();
        
        // Si el jugador no tiene cooldown, retornar false
        if (!isset($this->cooldowns[$playerName])) {
            return false;
        }
        
        // Calcular tiempo transcurrido desde el último uso
        $lastUsed = $this->cooldowns[$playerName];
        $timeElapsed = time() - $lastUsed;
        
        // Si ha pasado suficiente tiempo, eliminar el cooldown y retornar false
        if ($timeElapsed >= $this->cooldownTime) {
            unset($this->cooldowns[$playerName]);
            return false;
        }
        
        // Retornar el tiempo restante en segundos
        return $this->cooldownTime - $timeElapsed;
    }
    
    /**
     * Establece el tipo de partícula para un jugador
     */
    public function setPlayerParticle(Player $player, string $particleType): void {
        $playerName = $player->getName();
        $this->playerParticles[$playerName] = $particleType;
        
        // Guardar preferencia en el archivo de datos
        if ($this->savePlayerData) {
            $this->savePlayerPreference($playerName, "particle", $particleType);
        }
    }
    
    /**
     * Obtiene el tipo de partícula de un jugador
     */
    public function getPlayerParticle(Player $player): string {
        return $this->playerParticles[$player->getName()] ?? $this->defaultParticleType;
    }
    
    /**
     * Establece el tipo de animación para un jugador
     */
    public function setPlayerAnimation(Player $player, string $animationType): void {
        $playerName = $player->getName();
        $this->playerAnimations[$playerName] = $animationType;
        
        // Guardar preferencia en el archivo de datos
        if ($this->savePlayerData) {
            $this->savePlayerPreference($playerName, "animation", $animationType);
        }
    }
    
    /**
     * Obtiene el tipo de animación de un jugador
     */
    public function getPlayerAnimation(Player $player): string {
        return $this->playerAnimations[$player->getName()] ?? $this->defaultAnimationType;
    }
    
    /**
     * Guarda una preferencia específica de un jugador
     */
    private function savePlayerPreference(string $playerName, string $key, string $value): void {
        $data = $this->playerData->getAll();
        
        if (!isset($data[$playerName])) {
            $data[$playerName] = [];
        }
        
        $data[$playerName][$key] = $value;
        $this->playerData->setAll($data);
        $this->playerData->save();
    }
    
    /**
     * Carga las preferencias de un jugador
     */
    private function loadPlayerPreferences(string $playerName): void {
        if (!$this->savePlayerData) {
            return;
        }
        
        $data = $this->playerData->getAll();
        
        if (isset($data[$playerName])) {
            $playerData = $data[$playerName];
            
            // Cargar tipo de partícula
            if (isset($playerData["particle"])) {
                $this->playerParticles[$playerName] = $playerData["particle"];
            }
            
            // Cargar tipo de animación
            if (isset($playerData["animation"])) {
                $this->playerAnimations[$playerName] = $playerData["animation"];
            }
        }
    }
    
    /**
     * Guarda los datos de todos los jugadores
     */
    public function saveAllPlayerData(): void {
        if (!$this->savePlayerData) {
            return;
        }
        
        $data = $this->playerData->getAll();
        
        // Guardar preferencias de partículas
        foreach ($this->playerParticles as $playerName => $particleType) {
            if (!isset($data[$playerName])) {
                $data[$playerName] = [];
            }
            $data[$playerName]["particle"] = $particleType;
        }
        
        // Guardar preferencias de animaciones
        foreach ($this->playerAnimations as $playerName => $animationType) {
            if (!isset($data[$playerName])) {
                $data[$playerName] = [];
            }
            $data[$playerName]["animation"] = $animationType;
        }
        
        $this->playerData->setAll($data);
        $this->playerData->save();
    }
    
    /**
     * Obtiene la lista de partículas disponibles
     */
    public function getAvailableParticles(): array {
        return $this->availableParticles;
    }
    
    /**
     * Obtiene la lista de animaciones disponibles
     */
    public function getAvailableAnimations(): array {
        return $this->availableAnimations;
    }
    
    /**
     * Reproduce un sonido para un jugador
     */
    public function playSound(Player $player, string $soundName, float $volume = 1.0, float $pitch = 1.0): void {
        $pk = new PlaySoundPacket();
        $pk->soundName = $soundName;
        $pk->x = $player->getPosition()->getX();
        $pk->y = $player->getPosition()->getY();
        $pk->z = $player->getPosition()->getZ();
        $pk->volume = $volume;
        $pk->pitch = $pitch;
        $player->getNetworkSession()->sendDataPacket($pk);
    }
    
    /**
     * Maneja el evento de conexión de un jugador
     */
    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Cargar preferencias del jugador
        $this->loadPlayerPreferences($playerName);
    }
    
    /**
     * Maneja la desconexión de un jugador para limpiar la memoria
     */
    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Guardar preferencias del jugador antes de limpiar la memoria
        if ($this->savePlayerData) {
            if (isset($this->playerParticles[$playerName]) || isset($this->playerAnimations[$playerName])) {
                $this->savePlayerPreference($playerName, "particle", $this->playerParticles[$playerName] ?? $this->defaultParticleType);
                $this->savePlayerPreference($playerName, "animation", $this->playerAnimations[$playerName] ?? $this->defaultAnimationType);
            }
        }
        
        if(isset($this->flyEnabled[$playerName])){
            unset($this->flyEnabled[$playerName]);
        }
        
        if(isset($this->playerParticles[$playerName])){
            unset($this->playerParticles[$playerName]);
        }
        
        if(isset($this->playerAnimations[$playerName])){
            unset($this->playerAnimations[$playerName]);
        }
        
        if(isset($this->animationData[$playerName])){
            unset($this->animationData[$playerName]);
        }
        
        if(isset($this->cooldowns[$playerName])){
            unset($this->cooldowns[$playerName]);
        }
    }
    
    /**
     * Obtiene el tiempo de cooldown configurado
     */
    public function getCooldownTime(): int {
        return $this->cooldownTime;
    }
    
    /**
     * Verifica si el cooldown está habilitado
     */
    public function isCooldownEnabled(): bool {
        return $this->cooldownEnabled;
    }
}
