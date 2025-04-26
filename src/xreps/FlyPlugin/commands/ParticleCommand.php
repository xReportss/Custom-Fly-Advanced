<?php

declare(strict_types=1);

namespace xreps\FlyPlugin\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xreps\FlyPlugin\Main;
use pocketmine\form\Form;

class ParticleCommand extends Command {
    
    /** @var Main */
    private Main $plugin;
    
    public function __construct(Main $plugin) {
        parent::__construct("flyparticle", "Personaliza las partículas de vuelo", "/flyparticle", ["flyp"]);
        $this->setPermission("flyplugin.command.particle");
        $this->plugin = $plugin;
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
        
        // Mostrar el menú principal de partículas
        $this->showMainMenu($sender);
        return true;
    }
    
    /**
     * Muestra el menú principal de partículas
     */
    private function showMainMenu(Player $player): void {
        $form = new class($this->plugin, $player, [$this, "showParticleMenu"], [$this, "showAnimationMenu"]) implements Form {
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
                            'text' => "§a§lCambiar Partícula\n§7Selecciona un tipo de partícula"
                        ],
                        [
                            'text' => "§a§lCambiar Animación\n§7Selecciona un patrón de animación"
                        ],
                        [
                            'text' => "§c§lCerrar\n§7Volver al juego"
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
                    case 2: // Cerrar
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
    public function showParticleMenu(Player $player): void {
        $form = new class($this->plugin, $player, [$this, "showMainMenu"]) implements Form {
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
                                 ($hasPermission ? "§7Haz clic para seleccionar" : "§7No tienes permiso");
                    
                    $buttons[] = ['text' => $buttonText];
                }
                
                // Botón para volver
                $buttons[] = ['text' => "§6§lVolver\n§7Regresar al menú principal"];
                
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
        $form = new class($this->plugin, $player, [$this, "showMainMenu"]) implements Form {
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
                                 ($hasPermission ? "§7Haz clic para seleccionar" : "§7No tienes permiso");
                    
                    $buttons[] = ['text' => $buttonText];
                }
                
                // Botón para volver
                $buttons[] = ['text' => "§6§lVolver\n§7Regresar al menú principal"];
                
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
}
