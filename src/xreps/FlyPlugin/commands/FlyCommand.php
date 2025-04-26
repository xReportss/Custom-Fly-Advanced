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
    
    public function __construct(Main $plugin) {
        parent::__construct("fly", "Activa o desactiva el modo vuelo", "/fly", ["volar"]);
        $this->setPermission("flyplugin.command.fly");
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
        
        // Verificar cooldown
        $cooldown = $this->plugin->checkCooldown($sender);
        if ($cooldown !== false) {
            $sender->sendMessage(TF::RED . "Debes esperar " . $cooldown . " segundos antes de usar este comando nuevamente.");
            
            // Reproducir sonido de error
            $this->plugin->playSound($sender, "note.bass", 1, 0.5);
            
            return true;
        }
        
        // Mostrar el formulario de selección
        $this->showFlyMenu($sender);
        return true;
    }
    
    /**
     * Muestra el menú de vuelo al jugador
     */
    private function showFlyMenu(Player $player): void {
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
                $flyEnabled = $this->plugin->hasFlyEnabled($this->player);
                
                $content = '§7Selecciona una opción para activar o desactivar el modo vuelo.';
                
                // Añadir información sobre el cooldown si está habilitado
                if ($this->plugin->isCooldownEnabled() && !$this->player->hasPermission("flyplugin.bypass.cooldown")) {
                    $content .= "\n\n§e¡Atención! §7Después de activar o desactivar el vuelo, deberás esperar §f" . 
                               $this->plugin->getCooldownTime() . " segundos§7 antes de poder usar el comando nuevamente.";
                }
                
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
                    $content .= "\n\n§7Usa §f/flyparticle §7para personalizar tus partículas.";
                } else {
                    $content .= "\n\n§e¡Actualmente tienes el modo vuelo §cDESACTIVADO§e!";
                }
                
                $buttons = [
                    [
                        'text' => $flyEnabled ? "§c§lDesactivar Vuelo\n§7Haz clic para desactivar" : "§a§lActivar Vuelo\n§7Haz clic para activar"
                    ]
                ];
                
                // Añadir botón de personalización de partículas si tiene permiso
                if ($this->player->hasPermission("flyplugin.command.particle")) {
                    $buttons[] = [
                        'text' => "§b§lPersonalizar Partículas\n§7Cambia el aspecto de tus partículas"
                    ];
                }
                
                // Botón de cancelar
                $buttons[] = [
                    'text' => "§4§lCancelar\n§7Haz clic para cerrar"
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
                
                $hasParticlePermission = $player->hasPermission("flyplugin.command.particle");
                
                if ($data === 0) { // Activar/Desactivar vuelo
                    if($this->plugin->hasFlyEnabled($player)) {
                        $this->plugin->disableFly($player);
                    } else {
                        $this->plugin->enableFly($player);
                    }
                } else if ($data === 1 && $hasParticlePermission) { // Personalizar partículas
                    // Ejecutar comando de partículas
                    $this->plugin->getServer()->dispatchCommand($player, "flyparticle");
                } else { // Cancelar o último botón
                    $player->sendMessage(TF::YELLOW . "Has cerrado el menú de vuelo.");
                }
            }
        };
        
        $player->sendForm($form);
    }
}
