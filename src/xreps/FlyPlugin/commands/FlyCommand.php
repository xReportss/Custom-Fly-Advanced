<?php

declare(strict_types=1);

namespace xreps\FlyPlugin\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xreps\FlyPlugin\Main;
use pocketmine\form\Form;

class FlyCommand extends Command {
    
    /** @var Main */
    private Main $plugin;
    
    /** @var ParticleCommand */
    private ParticleCommand $particleCommand;
    
    /** @var MelodyCommand */
    private MelodyCommand $melodyCommand;
    
    public function __construct(Main $plugin) {
        parent::__construct("fly", "Activa o desactiva el modo vuelo y personaliza partículas y melodías", "/lol [particle|melody]", ["volar"]);
        $this->setPermission("flyplugin.command.fly");
        $this->plugin = $plugin;
        
        // Inicializar los comandos de partículas y melodías
        $this->particleCommand = new ParticleCommand($plugin);
        $this->melodyCommand = new MelodyCommand($plugin);
    }
    
    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if(!$sender instanceof Player) {
            $sender->sendMessage(TF::RED . "Este comando solo puede ser usado por jugadores.");
            return false;
        }
        
        if(!$this->testPermission($sender)) {
            $sender->sendMessage(TF::RED . "No tienes permiso para usar este comando.");
            return false;
        }
        
        // Verificar si hay subcomandos
        if (isset($args[0])) {
            $subCommand = strtolower($args[0]);
            
            switch ($subCommand) {
                case "particle":
                case "p":
                    // Verificar permiso para partículas
                    if (!$sender->hasPermission("flyplugin.command.particle")) {
                        $sender->sendMessage(TF::RED . "No tienes permiso para personalizar partículas.");
                        return false;
                    }
                    
                    // Mostrar menú de partículas
                    $sender->sendMessage(TF::YELLOW . "Abriendo menú de partículas...");
                    $this->showParticleMenu($sender);
                    return true;
                
                case "melody":
                case "m":
                    // Verificar permiso para melodías
                    if (!$sender->hasPermission("flyplugin.command.melody")) {
                        $sender->sendMessage(TF::RED . "No tienes permiso para personalizar melodías.");
                        return false;
                    }
                    
                    // Mostrar menú de melodías
                    $sender->sendMessage(TF::YELLOW . "Abriendo menú de melodías...");
                    $this->showMelodyMenu($sender);
                    return true;
                
                case "help":
                case "?":
                    // Mostrar ayuda
                    $this->showHelp($sender);
                    return true;
                
                default:
                    $sender->sendMessage(TF::RED . "Subcomando desconocido. Usa /fly help para ver los comandos disponibles.");
                    return false;
            }
        }
        
        // Si no hay subcomandos, verificar cooldown y mostrar el menú principal
        $playerDataManager = $this->plugin->getPlayerDataManager();
        list($canUse, $remainingTime) = $playerDataManager->canUseCommand($sender);
        
        if(!$canUse) {
            $sender->sendMessage(TF::RED . "Debes esperar " . $remainingTime . " segundos antes de usar este comando de nuevo.");
            return false;
        }
        
        // Establecer cooldown
        $playerDataManager->setCooldown($sender);
        
        // Mensaje de debug
        $sender->sendMessage(TF::YELLOW . "Abriendo menú de vuelo...");
        
        // Mostrar el formulario de selección
        $this->showFlyMenu($sender);
        return true;
    }
    
    /**
     * Muestra la ayuda del comando
     */
    private function showHelp(Player $player): void {
        $player->sendMessage(TF::GREEN . "=== Ayuda del Comando Flay ===");
        $player->sendMessage(TF::YELLOW . "/flay " . TF::WHITE . "- Abre el menú principal de vuelo");
        
        if ($player->hasPermission("flyplugin.command.particle")) {
            $player->sendMessage(TF::YELLOW . "/flay particle " . TF::WHITE . "- Personaliza las partículas de vuelo");
        }
        
        if ($player->hasPermission("flyplugin.command.melody")) {
            $player->sendMessage(TF::YELLOW . "/flay melody " . TF::WHITE . "- Personaliza las melodías durante el vuelo");
        }
        
        $player->sendMessage(TF::YELLOW . "/flay help " . TF::WHITE . "- Muestra esta ayuda");
    }
    
    /**
     * Muestra el menú de vuelo al jugador
     */
    private function showFlyMenu(Player $player): void {
        $plugin = $this->plugin; // Guardar referencia al plugin para usar en el closure
        $self = $this; // Guardar referencia a $this para usar en closures
        
        $form = new class($plugin, $player, $self) implements Form {
            /** @var Main */
            private Main $plugin;
            /** @var Player */
            private Player $player;
            /** @var FlyCommand */
            private FlyCommand $flyCommand;
            
            public function __construct(Main $plugin, Player $player, FlyCommand $flyCommand) {
                $this->plugin = $plugin;
                $this->player = $player;
                $this->flyCommand = $flyCommand;
            }
            
            public function jsonSerialize(): array {
                $flyEnabled = $this->plugin->hasFlyEnabled($this->player);
                
                $content = '§7Selecciona una opción para activar o desactivar el modo vuelo.';
                
                if ($flyEnabled) {
                    $content .= "\n\n§e¡Actualmente tienes el modo vuelo §aACTIVADO§e!";
                    $content .= "\n§7Se mostrarán partículas a tu alrededor mientras vuelas.";
                    
                    // Mostrar información sobre partículas y animación actual
                    $particleType = $this->plugin->getPlayerParticle($this->player);
                    $animationType = $this->plugin->getPlayerAnimation($this->player);
                    
                    $availableParticles = $this->plugin->getAvailableParticles();
                    $availableAnimations = $this->plugin->getAvailableAnimations();
                    
                    $particleName = $availableParticles[$particleType] ?? "Desconocido";
                    $animationName = $availableAnimations[$animationType] ?? "Desconocido";
                    
                    $content .= "\n\n§ePartícula: §b" . $particleName;
                    $content .= "\n§eAnimación: §b" . $animationName;
                    $content .= "\n\n§7Usa §f/fly particle §7para personalizar tus partículas.";
                } else {
                    $content .= "\n\n§e¡Actualmente tienes el modo vuelo §cDESACTIVADO§e!";
                }
                
                $buttons = [
                    [
                        'text' => $flyEnabled ? "§c§lDesactivar Vuelo\n§eHaz clic para desactivar" : "§a§lActivar Vuelo\n§eHaz clic para activar"
                    ]
                ];
                
                // Añadir botón de personalización de partículas si tiene permiso
                if ($this->player->hasPermission("flyplugin.command.particle")) {
                    $buttons[] = [
                        'text' => "§b§lPersonalizar Partículas\n§eHaz clic para cambiar partículas"
                    ];
                }
                
                // Añadir botón de personalización de melodías si tiene permiso
                if ($this->player->hasPermission("flyplugin.command.melody")) {
                    $buttons[] = [
                        'text' => "§d§lPersonalizar Melodías\n§eHaz clic para cambiar melodías"
                    ];
                }
                
                // Botón de cancelar
                $buttons[] = [
                    'text' => "§4§lCancelar\n§eHaz clic para cerrar"
                ];
                
                return [
                    'type' => 'form',
                    'title' => '§l§9Menú de Vuelo',
                    'content' => $content,
                    'buttons' => $buttons
                ];
            }
            
            public function handleResponse(Player $player, $data): void {
                if($data === null) {
                    return; // El jugador cerró el formulario
                }
                
                // Verificar permisos
                $hasParticlePermission = $player->hasPermission("flyplugin.command.particle");
                $hasMelodyPermission = $player->hasPermission("flyplugin.command.melody");
                
                $buttonIndex = 0;
                
                if ($data === $buttonIndex++) { // Activar/Desactivar vuelo
                    if($this->plugin->hasFlyEnabled($player)) {
                        $this->plugin->disableFly($player);
                    } else {
                        $this->plugin->enableFly($player);
                    }
                } else if ($hasParticlePermission && $data === $buttonIndex++) { // Personalizar partículas
                    // Mostrar menú de partículas
                    $this->flyCommand->showParticleMenu($player);
                } else if ($hasMelodyPermission && $data === $buttonIndex++) { // Personalizar melodías
                    // Mostrar menú de melodías
                    $this->flyCommand->showMelodyMenu($player);
                } else { // Cancelar o último botón
                    $player->sendMessage(TF::YELLOW . "Has cerrado el menú de vuelo.");
                }
            }
        };
        
        $player->sendForm($form);
    }
    
    /**
     * Muestra el menú principal de partículas
     */
    public function showParticleMenu(Player $player): void {
        $self = $this; // Guardar referencia a $this para usar en closures
        
        $form = new class($this->plugin, $player, 
            function(Player $p) use ($self) { $self->showParticleTypeMenu($p); },
            function(Player $p) use ($self) { $self->showAnimationMenu($p); }
        ) implements Form {
            /** @var Main */
            private Main $plugin;
            /** @var Player */
            private Player $player;
            /** @var callable */
            private $particleMenuCallback;
            /** @var callable */
            private $animationMenuCallback;
            
            public function __construct(Main $plugin, Player $player, callable $particleMenuCallback, callable $animationMenuCallback) {
                $this->plugin = $plugin;
                $this->player = $player;
                $this->particleMenuCallback = $particleMenuCallback;
                $this->animationMenuCallback = $animationMenuCallback;
            }
            
            public function jsonSerialize(): array {
                $currentParticle = $this->plugin->getPlayerParticle($this->player);
                $currentAnimation = $this->plugin->getPlayerAnimation($this->player);
                
                $particleNames = $this->plugin->getAvailableParticles();
                $animationNames = $this->plugin->getAvailableAnimations();
                
                $currentParticleName = $particleNames[$currentParticle] ?? "Desconocido";
                $currentAnimationName = $animationNames[$currentAnimation] ?? "Desconocido";
                
                return [
                    'type' => 'form',
                    'title' => '§l§9Personalización de Partículas',
                    'content' => "§7Personaliza las partículas que se muestran mientras vuelas.\n\n" .
                                "§ePartícula actual: §b" . $currentParticleName . "\n" .
                                "§eAnimación actual: §b" . $currentAnimationName . "\n\n" .
                                "§7Selecciona una opción:",
                    'buttons' => [
                        [
                            'text' => "§a§lCambiar Partícula\n§eSelecciona un tipo de partícula"
                        ],
                        [
                            'text' => "§a§lCambiar Animación\n§eSelecciona un patrón de animación"
                        ],
                        [
                            'text' => "§6§lVolver al Menú Principal\n§eRegresar al menú de vuelo"
                        ],
                        [
                            'text' => "§c§lCerrar\n§eVolver al juego"
                        ]
                    ]
                ];
            }
            
            public function handleResponse(Player $player, $data): void {
                if($data === null) {
                    return; // El jugador cerró el formulario
                }
                
                switch($data) {
                    case 0: // Cambiar partícula
                        ($this->particleMenuCallback)($player);
                        break;
                    case 1: // Cambiar animación
                        ($this->animationMenuCallback)($player);
                        break;
                    case 2: // Volver al menú principal
                        $player->getServer()->dispatchCommand($player, "fly");
                        break;
                    case 3: // Cerrar
                        $player->sendMessage(TF::YELLOW . "Has cerrado el menú de partículas.");
                        break;
                }
            }
        };
        
        $player->sendForm($form);
    }
    
    /**
     * Muestra el menú de selección de partículas
     */
    public function showParticleTypeMenu(Player $player): void {
        $self = $this; // Guardar referencia a $this para usar en closures
        
        $form = new class($this->plugin, $player, 
            function(Player $p) use ($self) { $self->showParticleMenu($p); }
        ) implements Form {
            /** @var Main */
            private Main $plugin;
            /** @var Player */
            private Player $player;
            /** @var callable */
            private $mainMenuCallback;
            
            public function __construct(Main $plugin, Player $player, callable $mainMenuCallback) {
                $this->plugin = $plugin;
                $this->player = $player;
                $this->mainMenuCallback = $mainMenuCallback;
            }
            
            public function jsonSerialize(): array {
                $currentParticle = $this->plugin->getPlayerParticle($this->player);
                $availableParticles = $this->plugin->getAvailableParticles();
                
                $buttons = [];
                foreach ($availableParticles as $particleId => $particleName) {
                    $hasPermission = $this->player->hasPermission("flyplugin.particle." . $particleId);
                    $isSelected = ($currentParticle === $particleId);
                    
                    $buttonText = ($isSelected ? "§l" : "") . 
                                 ($hasPermission ? "§a" : "§c") . 
                                 $particleName . 
                                 ($isSelected ? " §e(Seleccionado)" : "") . 
                                 "\n" . 
                                 ($hasPermission ? "§eHaz clic para seleccionar" : "§eNo tienes permiso");
                    
                    $buttons[] = ['text' => $buttonText];
                }
                
                // Botón para volver
                $buttons[] = ['text' => "§6§lVolver\n§eRegresar al menú de partículas"];
                
                return [
                    'type' => 'form',
                    'title' => '§l§9Selección de Partículas',
                    'content' => "§7Selecciona el tipo de partícula que deseas usar mientras vuelas.\n\n" .
                                "§ePartícula actual: §b" . $availableParticles[$currentParticle] . "\n\n" .
                                "§7Las opciones en §crojo§7 requieren permisos especiales.",
                    'buttons' => $buttons
                ];
            }
            
            public function handleResponse(Player $player, $data): void {
                if($data === null) {
                    return; // El jugador cerró el formulario
                }
                
                $availableParticles = $this->plugin->getAvailableParticles();
                $particleIds = array_keys($availableParticles);
                
                // Si es el último botón, volver al menú principal
                if($data === count($availableParticles)) {
                    ($this->mainMenuCallback)($player);
                    return;
                }
                
                // Verificar si el jugador seleccionó una partícula válida
                if(isset($particleIds[$data])) {
                    $selectedParticle = $particleIds[$data];
                    
                    // Verificar permiso
                    if($player->hasPermission("flyplugin.particle." . $selectedParticle)) {
                        $this->plugin->setPlayerParticle($player, $selectedParticle);
                        $player->sendMessage(TF::GREEN . "¡Has seleccionado la partícula " . $availableParticles[$selectedParticle] . "!");
                        
                        // Reproducir sonido de selección
                        $this->plugin->playSound($player, "random.click", 1, 1.5);
                        
                        // Volver al menú principal
                        ($this->mainMenuCallback)($player);
                    } else {
                        $player->sendMessage(TF::RED . "No tienes permiso para usar esta partícula.");
                        
                        // Reproducir sonido de error
                        $this->plugin->playSound($player, "note.bass", 1, 0.5);
                        
                        // Mostrar de nuevo el menú de partículas
                        $this->jsonSerialize();
                        $player->sendForm($this);
                    }
                }
            }
        };
        
        $player->sendForm($form);
    }
    
    /**
     * Muestra el menú de selección de animaciones
     */
    public function showAnimationMenu(Player $player): void {
        $self = $this; // Guardar referencia a $this para usar en closures
        
        $form = new class($this->plugin, $player, 
            function(Player $p) use ($self) { $self->showParticleMenu($p); }
        ) implements Form {
            /** @var Main */
            private Main $plugin;
            /** @var Player */
            private Player $player;
            /** @var callable */
            private $mainMenuCallback;
            
            public function __construct(Main $plugin, Player $player, callable $mainMenuCallback) {
                $this->plugin = $plugin;
                $this->player = $player;
                $this->mainMenuCallback = $mainMenuCallback;
            }
            
            public function jsonSerialize(): array {
                $currentAnimation = $this->plugin->getPlayerAnimation($this->player);
                $availableAnimations = $this->plugin->getAvailableAnimations();
                
                $buttons = [];
                foreach ($availableAnimations as $animationId => $animationName) {
                    $hasPermission = $this->player->hasPermission("flyplugin.animation." . $animationId);
                    $isSelected = ($currentAnimation === $animationId);
                    
                    $buttonText = ($isSelected ? "§l" : "") . 
                                 ($hasPermission ? "§a" : "§c") . 
                                 $animationName . 
                                 ($isSelected ? " §e(Seleccionado)" : "") . 
                                 "\n" . 
                                 ($hasPermission ? "§eHaz clic para seleccionar" : "§eNo tienes permiso");
                    
                    $buttons[] = ['text' => $buttonText];
                }
                
                // Botón para volver
                $buttons[] = ['text' => "§6§lVolver\n§eRegresar al menú de partículas"];
                
                return [
                    'type' => 'form',
                    'title' => '§l§9Selección de Animaciones',
                    'content' => "§7Selecciona el patrón de animación que deseas usar mientras vuelas.\n\n" .
                                "§eAnimación actual: §b" . $availableAnimations[$currentAnimation] . "\n\n" .
                                "§7Las opciones en §crojo§7 requieren permisos especiales.",
                    'buttons' => $buttons
                ];
            }
            
            public function handleResponse(Player $player, $data): void {
                if($data === null) {
                    return; // El jugador cerró el formulario
                }
                
                $availableAnimations = $this->plugin->getAvailableAnimations();
                $animationIds = array_keys($availableAnimations);
                
                // Si es el último botón, volver al menú principal
                if($data === count($availableAnimations)) {
                    ($this->mainMenuCallback)($player);
                    return;
                }
                
                // Verificar si el jugador seleccionó una animación válida
                if(isset($animationIds[$data])) {
                    $selectedAnimation = $animationIds[$data];
                    
                    // Verificar permiso
                    if($player->hasPermission("flyplugin.animation." . $selectedAnimation)) {
                        $this->plugin->setPlayerAnimation($player, $selectedAnimation);
                        $player->sendMessage(TF::GREEN . "¡Has seleccionado la animación " . $availableAnimations[$selectedAnimation] . "!");
                        
                        // Reproducir sonido de selección
                        $this->plugin->playSound($player, "random.click", 1, 1.5);
                        
                        // Volver al menú principal
                        ($this->mainMenuCallback)($player);
                    } else {
                        $player->sendMessage(TF::RED . "No tienes permiso para usar esta animación.");
                        
                        // Reproducir sonido de error
                        $this->plugin->playSound($player, "note.bass", 1, 0.5);
                        
                        // Mostrar de nuevo el menú de animaciones
                        $this->jsonSerialize();
                        $player->sendForm($this);
                    }
                }
            }
        };
        
        $player->sendForm($form);
    }
    
    /**
     * Muestra el menú principal de melodías
     */
    public function showMelodyMenu(Player $player): void {
        $self = $this; // Guardar referencia a $this para usar en closures
        
        $form = new class($this->plugin, $player, $self) implements Form {
            /** @var Main */
            private Main $plugin;
            /** @var Player */
            private Player $player;
            /** @var FlyCommand */
            private FlyCommand $flyCommand;
            
            public function __construct(Main $plugin, Player $player, FlyCommand $flyCommand) {
                $this->plugin = $plugin;
                $this->player = $player;
                $this->flyCommand = $flyCommand;
            }
            
            public function jsonSerialize(): array {
                $currentMelody = $this->plugin->getPlayerMelody($this->player);
                $melodyEnabled = $this->plugin->isMelodyEnabled($this->player);
                $availableMelodies = $this->plugin->getAvailableMelodies();
                
                $currentMelodyName = $availableMelodies[$currentMelody] ?? "Desconocido";
                
                $buttons = [];
                
                // Botón para activar/desactivar melodías
                $buttons[] = [
                    'text' => $melodyEnabled ? 
                        "§c§lDesactivar Melodías\n§eHaz clic para desactivar" : 
                        "§a§lActivar Melodías\n§eHaz clic para activar"
                ];
                
                // Botones para cada melodía
                foreach ($availableMelodies as $melodyId => $melodyName) {
                    $hasPermission = $this->player->hasPermission("flyplugin.melody." . $melodyId) || $melodyId === "none";
                    $isSelected = ($currentMelody === $melodyId);
                    
                    $buttonText = ($isSelected ? "§l" : "") . 
                                 ($hasPermission ? "§a" : "§c") . 
                                 $melodyName . 
                                 ($isSelected ? " §e(Seleccionado)" : "") . 
                                 "\n" . 
                                 ($hasPermission ? "§eHaz clic para seleccionar" : "§eNo tienes permiso");
                    
                    $buttons[] = ['text' => $buttonText];
                }
                
                // Botón para volver al menú principal
                $buttons[] = ['text' => "§6§lVolver al Menú Principal\n§eRegresar al menú de vuelo"];
                
                // Botón para cerrar
                $buttons[] = ['text' => "§4§lCerrar\n§eVolver al juego"];
                
                return [
                    'type' => 'form',
                    'title' => '§l§9Melodías de Vuelo',
                    'content' => "§7Selecciona la melodía que deseas escuchar mientras vuelas.\n\n" .
                                "§eMelodía actual: §b" . $currentMelodyName . "\n" .
                                "§eMelodías: " . ($melodyEnabled ? "§aActivadas" : "§cDesactivadas") . "\n\n" .
                                "§7Las opciones en §crojo§7 requieren permisos especiales.",
                    'buttons' => $buttons
                ];
            }
            
            public function handleResponse(Player $player, $data): void {
                if($data === null) {
                    return; // El jugador cerró el formulario
                }
                
                $availableMelodies = $this->plugin->getAvailableMelodies();
                $melodyEnabled = $this->plugin->isMelodyEnabled($player);
                
                if($data === 0) {
                    // Activar/Desactivar melodías
                    $this->plugin->setMelodyEnabled($player, !$melodyEnabled);
                    $player->sendMessage(TF::GREEN . "Melodías " . (!$melodyEnabled ? "activadas" : "desactivadas") . " correctamente.");
                    
                    // Reproducir sonido de confirmación
                    $this->plugin->playSound($player, "random.click", 1, 1.5);
                    
                    // Mostrar de nuevo el menú
                    $player->sendForm($this);
                    return;
                }
                
                // Si es el botón de volver al menú principal
                if($data === count($availableMelodies) + 1) {
                    $player->getServer()->dispatchCommand($player, "fly");
                    return;
                }
                
                // Si es el botón de cerrar
                if($data === count($availableMelodies) + 2) {
                    $player->sendMessage(TF::YELLOW . "Has cerrado el menú de melodías.");
                    return;
                }
                
                // Seleccionar melodía
                $melodyIndex = $data - 1; // Restamos 1 porque el primer botón es para activar/desactivar
                $melodyIds = array_keys($availableMelodies);
                
                if(isset($melodyIds[$melodyIndex])) {
                    $selectedMelody = $melodyIds[$melodyIndex];
                    
                    // Verificar permiso (excepto para "none")
                    if($selectedMelody === "none" || $player->hasPermission("flyplugin.melody." . $selectedMelody)) {
                        $this->plugin->setPlayerMelody($player, $selectedMelody);
                        $player->sendMessage(TF::GREEN . "¡Has seleccionado la melodía " . $availableMelodies[$selectedMelody] . "!");
                        
                        // Reproducir sonido de selección
                        $this->plugin->playSound($player, "random.click", 1, 1.5);
                        
                        // Si seleccionó una melodía y están desactivadas, activarlas
                        if($selectedMelody !== "none" && !$this->plugin->isMelodyEnabled($player)) {
                            $this->plugin->setMelodyEnabled($player, true);
                            $player->sendMessage(TF::GREEN . "Melodías activadas automáticamente.");
                        }
                        
                        // Mostrar de nuevo el menú
                        $player->sendForm($this);
                    } else {
                        $player->sendMessage(TF::RED . "No tienes permiso para usar esta melodía.");
                        
                        // Reproducir sonido de error
                        $this->plugin->playSound($player, "note.bass", 1, 0.5);
                        
                        // Mostrar de nuevo el menú
                        $player->sendForm($this);
                    }
                }
            }
        };
        
        $player->sendForm($form);
    }
}
