<?php

declare(strict_types=1);

namespace xreps\FlyPlugin;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xreps\FlyPlugin\commands\FlyCommand;
use xreps\FlyPlugin\utils\PlayerDataManager;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
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
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\color\Color;

class Main extends PluginBase implements Listener {
    
    /** @var array */
    private array $flyEnabled = [];
    
    /** @var array */
    private array $playerParticles = [];
    
    /** @var array */
    private array $playerAnimations = [];
    
    /** @var array */
    private array $playerMelodies = [];
    
    /** @var array */
    private array $melodyEnabled = [];
    
    /** @var array */
    private array $fallDamageProtection = [];
    
    /** @var TaskHandler */
    private TaskHandler $particleTask;
    
    /** @var TaskHandler|null */
    private ?TaskHandler $melodyTask = null;
    
    /** @var string */
    private string $defaultParticleType;
    
    /** @var string */
    private string $defaultAnimationType;
    
    /** @var string */
    private string $defaultMelodyType;
    
    /** @var int */
    private int $particleFrequency;
    
    /** @var int */
    private int $particleCount;
    
    /** @var int */
    private int $flyCooldown;
    
    /** @var int */
    private int $melodyFrequency;
    
    /** @var bool */
    private bool $enableMelodies;
    
    /** @var PlayerDataManager */
    private PlayerDataManager $playerDataManager;
    
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
    private array $availableMelodies = [
        "none" => "Sin melodía",
        "calm" => "Calma",
        "adventure" => "Aventura",
        "magical" => "Mágica",
        "epic" => "Épica",
        "mysterious" => "Misteriosa"
    ];
    
    /** @var array */
    private array $melodySounds = [
        "calm" => [
            ["note.harp", 1.0, 0.5],
            ["note.harp", 1.0, 0.6],
            ["note.harp", 1.0, 0.7],
            ["note.harp", 1.0, 0.8]
        ],
        "adventure" => [
            ["note.bell", 1.0, 0.6],
            ["note.bell", 1.0, 0.8],
            ["note.bell", 1.0, 1.0],
            ["note.bell", 1.0, 0.8]
        ],
        "magical" => [
            ["note.chime", 1.0, 0.5],
            ["note.chime", 1.0, 0.7],
            ["note.chime", 1.0, 0.9],
            ["note.chime", 1.0, 1.1]
        ],
        "epic" => [
            ["note.xylophone", 1.0, 0.5],
            ["note.xylophone", 1.0, 0.7],
            ["note.xylophone", 1.0, 0.9],
            ["note.xylophone", 1.0, 1.1]
        ],
        "mysterious" => [
            ["note.bass", 1.0, 0.5],
            ["note.bass", 1.0, 0.6],
            ["note.bass", 1.0, 0.7],
            ["note.flute", 1.0, 0.8]
        ]
    ];
    
    /** @var array */
    private array $animationData = [];
    
    /** @var array */
    private array $melodyData = [];
    
    public function onEnable(): void {
        $this->getLogger()->info(TF::GREEN . "FlyPlugin habilitado correctamente!");
        
        // Inicializar el gestor de datos de jugadores
        $this->playerDataManager = new PlayerDataManager($this);
        
        // Registrar solo el comando principal fly
        $this->getServer()->getCommandMap()->register("lol", new FlyCommand($this));
        
        // Crear la carpeta de configuración si no existe
        @mkdir($this->getDataFolder());
        
        // Guardar la configuración por defecto
        $this->saveDefaultConfig();
        
        // Cargar configuración
        $this->loadConfig();
        
        // Registrar eventos
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        
        // Iniciar tarea de partículas
        $this->startParticleTask();
        
        // Iniciar tarea de melodías si están habilitadas
        if ($this->enableMelodies) {
            $this->startMelodyTask();
        }
    }
    
    public function onDisable(): void {
        $this->getLogger()->info(TF::RED . "FlyPlugin deshabilitado!");
        
        // Cancelar tarea de partículas si existe
        if (isset($this->particleTask)) {
            $this->particleTask->cancel();
        }
        
        // Cancelar tarea de melodías si existe
        if ($this->melodyTask !== null) {
            $this->melodyTask->cancel();
        }
    }
    
    /**
     * Carga la configuración desde config.yml
     */
    private function loadConfig(): void {
        $config = $this->getConfig();
        
        // Cargar tipo de partícula por defecto
        $this->defaultParticleType = strtolower($config->get("default-particle-type", "heart"));
        
        // Cargar tipo de animación por defecto
        $this->defaultAnimationType = strtolower($config->get("default-animation-type", "circle"));
        
        // Cargar tipo de melodía por defecto
        $this->defaultMelodyType = strtolower($config->get("default-melody", "none"));
        
        // Cargar frecuencia de partículas en ticks (por defecto: 10 ticks = 0.5 segundos)
        $this->particleFrequency = max(1, $config->get("particle-frequency", 10));
        
        // Cargar cantidad de partículas por jugador (por defecto: 5)
        $this->particleCount = max(1, $config->get("particle-count", 5));
        
        // Cargar tiempo de cooldown para el comando fly (por defecto: 5 segundos)
        $this->flyCooldown = max(0, $config->get("fly-cooldown", 5));
        
        // Cargar configuración de melodías
        $this->enableMelodies = (bool)$config->get("enable-melodies", true);
        $this->melodyFrequency = max(20, $config->get("melody-frequency", 40));
    }
    
    /**
     * Inicia la tarea programada para mostrar partículas
     */
    private function startParticleTask(): void {
        $this->particleTask = $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
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
        ), $this->particleFrequency);
    }
    
    /**
     * Inicia la tarea programada para reproducir melodías
     */
    private function startMelodyTask(): void {
        $this->melodyTask = $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function(): void {
                foreach ($this->getServer()->getOnlinePlayers() as $player) {
                    if ($this->hasFlyEnabled($player) && $player->isFlying()) {
                        $playerName = $player->getName();
                        
                        // Verificar si las melodías están habilitadas para este jugador
                        if (!isset($this->melodyEnabled[$playerName]) || !$this->melodyEnabled[$playerName]) {
                            continue;
                        }
                        
                        // Obtener tipo de melodía del jugador o usar el predeterminado
                        $melodyType = $this->playerMelodies[$playerName] ?? $this->defaultMelodyType;
                        
                        // Si no hay melodía seleccionada, saltar
                        if ($melodyType === "none") {
                            continue;
                        }
                        
                        // Verificar permiso para el tipo de melodía
                        if (!$player->hasPermission("flyplugin.melody." . $melodyType)) {
                            // Si no tiene permiso, usar la melodía predeterminada
                            $melodyType = $this->defaultMelodyType;
                        }
                        
                        // Reproducir la melodía
                        $this->playMelody($player, $melodyType);
                    }
                }
            }
        ), $this->melodyFrequency);
    }
    
    /**
     * Reproduce una nota de la melodía para un jugador
     */
    private function playMelody(Player $player, string $melodyType): void {
        // Si el tipo de melodía no existe o es "none", no hacer nada
        if ($melodyType === "none" || !isset($this->melodySounds[$melodyType])) {
            return;
        }
        
        $playerName = $player->getName();
        
        // Inicializar datos de melodía para el jugador si no existen
        if (!isset($this->melodyData[$playerName])) {
            $this->melodyData[$playerName] = [
                "index" => 0,
                "lastPlayed" => 0
            ];
        }
        
        // Obtener el índice actual de la melodía
        $index = $this->melodyData[$playerName]["index"];
        
        // Obtener la nota actual
        $melody = $this->melodySounds[$melodyType];
        $note = $melody[$index];
        
        // Reproducir el sonido
        $this->playSound($player, $note[0], $note[1], $note[2]);
        
        // Actualizar el índice para la próxima nota
        $this->melodyData[$playerName]["index"] = ($index + 1) % count($melody);
        $this->melodyData[$playerName]["lastPlayed"] = time();
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
                // CORREGIDO: La API de RedstoneParticle ha cambiado
                return new RedstoneParticle(1); // Usar 1 como valor predeterminado para lifetime
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
    }
    
    /**
     * Desactiva el modo vuelo para un jugador
     */
    public function disableFly(Player $player): void {
        $player->setAllowFlight(false);
        $player->setFlying(false);
        $this->flyEnabled[$player->getName()] = false;
        $player->sendMessage(TF::RED . "¡Modo vuelo desactivado!");
        
        // Activar protección contra daño de caída por 5 segundos
        $this->activateFallDamageProtection($player);
        
        // Reproducir sonido de desactivación
        $this->playSound($player, "mob.chicken.plop", 1, 0.8);
    }
    
    /**
     * Activa la protección contra daño de caída para un jugador
     */
    private function activateFallDamageProtection(Player $player): void {
        $playerName = $player->getName();
        $this->fallDamageProtection[$playerName] = time() + 5; // 5 segundos de protección
        
        // Mostrar mensaje de protección
        $player->sendMessage(TF::YELLOW . "Tienes 5 segundos de protección contra daño de caída.");
        
        // Mostrar partículas de protección
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function() use ($player, $playerName): void {
                // Verificar si el jugador sigue online
                if (!$player->isOnline()) {
                    return;
                }
                
                // Verificar si la protección sigue activa
                if (!isset($this->fallDamageProtection[$playerName]) || $this->fallDamageProtection[$playerName] < time()) {
                    return;
                }
                
                // Mostrar partículas de protección alrededor del jugador
                $position = $player->getPosition();
                $world = $player->getWorld();
                
                for ($i = 0; $i < 8; $i++) {
                    $angle = 2 * M_PI * $i / 8;
                    $x = $position->x + cos($angle) * 0.7;
                    $y = $position->y + 0.1;
                    $z = $position->z + sin($angle) * 0.7;
                    
                    $world->addParticle(new Vector3($x, $y, $z), new HappyVillagerParticle());
                }
            }
        ), 10, 10); // Cada 10 ticks (0.5 segundos) durante 10 repeticiones (5 segundos)
    }
    
    /**
     * Verifica si un jugador tiene protección contra daño de caída
     */
    public function hasFallDamageProtection(Player $player): bool {
        $playerName = $player->getName();
        return isset($this->fallDamageProtection[$playerName]) && $this->fallDamageProtection[$playerName] > time();
    }
    
    /**
     * Establece el tipo de partícula para un jugador
     */
    public function setPlayerParticle(Player $player, string $particleType): void {
        $this->playerParticles[$player->getName()] = $particleType;
        
        // Guardar preferencias
        $this->savePlayerPreferences($player);
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
        $this->playerAnimations[$player->getName()] = $animationType;
        
        // Guardar preferencias
        $this->savePlayerPreferences($player);
    }
    
    /**
     * Obtiene el tipo de animación de un jugador
     */
    public function getPlayerAnimation(Player $player): string {
        return $this->playerAnimations[$player->getName()] ?? $this->defaultAnimationType;
    }
    
    /**
     * Establece el tipo de melodía para un jugador
     */
    public function setPlayerMelody(Player $player, string $melodyType): void {
        $this->playerMelodies[$player->getName()] = $melodyType;
        
        // Guardar preferencias
        $this->savePlayerPreferences($player);
    }
    
    /**
     * Obtiene el tipo de melodía de un jugador
     */
    public function getPlayerMelody(Player $player): string {
        return $this->playerMelodies[$player->getName()] ?? $this->defaultMelodyType;
    }
    
    /**
     * Activa o desactiva las melodías para un jugador
     */
    public function setMelodyEnabled(Player $player, bool $enabled): void {
        $this->melodyEnabled[$player->getName()] = $enabled;
        
        // Guardar preferencias
        $this->savePlayerPreferences($player);
    }
    
    /**
     * Verifica si las melodías están activadas para un jugador
     */
    public function isMelodyEnabled(Player $player): bool {
        return $this->melodyEnabled[$player->getName()] ?? true;
    }
    
    /**
     * Guarda las preferencias de un jugador
     */
    private function savePlayerPreferences(Player $player): void {
        $playerName = $player->getName();
        
        $particleType = $this->playerParticles[$playerName] ?? $this->defaultParticleType;
        $animationType = $this->playerAnimations[$playerName] ?? $this->defaultAnimationType;
        $melodyType = $this->playerMelodies[$playerName] ?? $this->defaultMelodyType;
        $melodyEnabled = $this->melodyEnabled[$playerName] ?? true;
        
        $this->playerDataManager->savePlayerPreferences($player, $particleType, $animationType, $melodyType, $melodyEnabled);
    }
    
    /**
     * Carga las preferencias de un jugador
     */
    private function loadPlayerPreferences(Player $player): void {
        $playerName = $player->getName();
        
        list($particleType, $animationType, $melodyType, $melodyEnabled) = $this->playerDataManager->loadPlayerPreferences($player);
        
        $this->playerParticles[$playerName] = $particleType;
        $this->playerAnimations[$playerName] = $animationType;
        $this->playerMelodies[$playerName] = $melodyType;
        $this->melodyEnabled[$playerName] = $melodyEnabled;
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
     * Obtiene la lista de melodías disponibles
     */
    public function getAvailableMelodies(): array {
        return $this->availableMelodies;
    }
    
    /**
     * Obtiene el tiempo de cooldown para el comando fly
     */
    public function getFlyCooldown(): int {
        return $this->flyCooldown;
    }
    
    /**
     * Obtiene el tipo de partícula por defecto
     */
    public function getDefaultParticleType(): string {
        return $this->defaultParticleType;
    }
    
    /**
     * Obtiene el tipo de animación por defecto
     */
    public function getDefaultAnimationType(): string {
        return $this->defaultAnimationType;
    }
    
    /**
     * Obtiene el tipo de melodía por defecto
     */
    public function getDefaultMelodyType(): string {
        return $this->defaultMelodyType;
    }
    
    /**
     * Obtiene el gestor de datos de jugadores
     */
    public function getPlayerDataManager(): PlayerDataManager {
        return $this->playerDataManager;
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
     * Maneja el evento de daño para cancelar el daño de caída si el jugador tiene protección
     */
    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        
        // Verificar si la entidad es un jugador
        if (!$entity instanceof Player) {
            return;
        }
        
        // Verificar si el daño es por caída
        if ($event->getCause() === EntityDamageEvent::CAUSE_FALL) {
            // Verificar si el jugador tiene protección contra daño de caída
            if ($this->hasFallDamageProtection($entity)) {
                // Cancelar el evento de daño
                $event->cancel();
                
                // Mostrar partículas de protección
                $position = $entity->getPosition();
                $world = $entity->getWorld();
                
                for ($i = 0; $i < 16; $i++) {
                    $x = $position->x + (mt_rand(-10, 10) / 10);
                    $y = $position->y + (mt_rand(0, 10) / 10);
                    $z = $position->z + (mt_rand(-10, 10) / 10);
                    
                    $world->addParticle(new Vector3($x, $y, $z), new HappyVillagerParticle());
                }
                
                // Reproducir sonido de protección
                $this->playSound($entity, "random.pop", 1, 1.5);
            }
        }
    }
    
    /**
     * Maneja la conexión de un jugador para cargar sus preferencias
     */
    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        
        // Cargar preferencias del jugador
        $this->loadPlayerPreferences($player);
    }
    
    /**
     * Maneja la desconexión de un jugador para limpiar la memoria
     */
    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        if(isset($this->flyEnabled[$playerName])){
            unset($this->flyEnabled[$playerName]);
        }
        
        if(isset($this->animationData[$playerName])){
            unset($this->animationData[$playerName]);
        }
        
        if(isset($this->melodyData[$playerName])){
            unset($this->melodyData[$playerName]);
        }
        
        if(isset($this->fallDamageProtection[$playerName])){
            unset($this->fallDamageProtection[$playerName]);
        }
        
        // No eliminamos las preferencias porque las necesitamos guardar entre sesiones
    }
}
