<?php

declare(strict_types=1);

namespace xreps\FlyPlugin\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xreps\FlyPlugin\Main;
use pocketmine\form\Form;

class MelodyCommand extends Command {
    
    /** @var Main */
    private Main $plugin;
    
    public function __construct(Main $plugin) {
        parent::__construct("flymelody", "Personaliza las melodías durante el vuelo", "/flymelody", ["flym"]);
        $this->setPermission("flyplugin.command.melody");
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
    
        // Mensaje de debug
        $sender->sendMessage(TF::YELLOW . "Abriendo menú de melodías...");
    
        // Mostrar el menú de melodías
        $this->showMelodyMenu($sender);
        return true;
    }
    
    /**
     * Muestra el menú principal de melodías
     */
    private function showMelodyMenu(Player $player): void {
        $form = new class($this->plugin, $player) implements Form {
            /** @var Main */
            private Main $plugin;
            /** @var Player */
            private Player $player;
            
            public function __construct(Main $plugin, Player $player) {
                $this->plugin = $plugin;
                $this->player = $player;
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
                
                // Si es el último botón, cerrar
                if($data === count($availableMelodies) + 1) {
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
