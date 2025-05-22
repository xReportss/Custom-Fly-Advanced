# Custom-Fly-Advanced
# Para PocketMine-MP

Un plugin avanzado de vuelo para servidores PocketMine-MP que permite a los jugadores volar con efectos de partículas personalizables.

## Características

- Activar/desactivar vuelo mediante comando o menú gráfico
- Sistema de partículas personalizables mientras vuelas
- 12 tipos diferentes de partículas
- 6 animaciones distintas para las partículas
- Sistema de permisos para controlar el acceso a partículas y animaciones
- Sistema de Sonidos en el efecto de vuelo 
- Efectos de sonido al activar/desactivar el vuelo
- Sistema de cooldown configurable
- Guardado de preferencias entre reinicios del servidor

## Instalación

1. Descarga el archivo .phar de la sección de Releases
2. Coloca el archivo en la carpeta `plugins` de tu servidor PocketMine-MP
3. Reinicia el servidor
4. ¡Listo para usar!

## Comandos

- `/fly` - Abre el menú de vuelo para activar/desactivar el modo vuelo
- `/fly p` - Abre el menú de personalización de partículas
- `/fly m` - Abre el menú de personalizacion de sonidos

## Permisos

### Comandos
- `flyplugin.command.fly` - Permite usar el comando /fly
- `flyplugin.command.particle` - Permite personalizar las partículas

### Partículas
- `flyplugin.particle.heart` - Permite usar partículas de corazón
- `flyplugin.particle.portal` - Permite usar partículas de portal
- (etc...)

### Animaciones
- `flyplugin.animation.circle` - Permite usar la animación de círculo
- `flyplugin.animation.spiral` - Permite usar la animación de espiral
- (etc...)

### Otros
- `flyplugin.bypass.cooldown` - Permite omitir el tiempo de espera entre usos

## Configuración

El archivo `config.yml` permite personalizar:
- Tipos de partículas predeterminados
- Frecuencia y cantidad de partículas
- Tiempo de cooldown
- Y más...

## Licencia

Este proyecto está licenciado bajo xReportss - ver el archivo LICENSE para más detalles.
