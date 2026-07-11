<div align="center">

<img width="313" height="234" alt="Favilla" src="https://github.com/user-attachments/assets/fc57ecac-ddfb-4c0f-8aaf-c1bff9b947b5" />

**El espacio de trabajo autoalojado que hace funcionar tu empresa — y sigue siendo tuyo.**

Proyectos · Documentos · Chat de equipo · Tareas · Calendario · Contactos · Archivos · Informes

[![CI](https://github.com/Myth70/favilla/actions/workflows/ci.yml/badge.svg)](https://github.com/Myth70/favilla/actions/workflows/ci.yml)
[![Latest release](https://img.shields.io/github/v/release/Myth70/favilla)](../../releases)
[![License: AGPL-3.0-or-later](https://img.shields.io/badge/license-AGPL--3.0--or--later-blue)](LICENSE)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](composer.json)

🌐 [English](README.md) · [Italiano](README.it.md) · [Français](README.fr.md) · [Deutsch](README.de.md) · **Español**

</div>

**Favilla** — «chispa» en italiano — es un espacio de trabajo completo y una
intranet corporativa que alojas tú mismo: proyectos con Gantt, partes de horas y
presupuestos, documentos con flujos de aprobación, mensajería de equipo, tareas
kanban, calendarios compartidos, contactos, archivos, informes listos para
imprimir, notificaciones multicanal y una suite completa de seguridad y
cumplimiento. Dieciocho módulos, cinco idiomas, una sola instalación, en tu propio
servidor — sin precios por puesto, sin telemetría, nada que «llame a casa», y la
licencia AGPL se encarga de que siga así.

En el mapa de herramientas que ya conoces, Favilla se sitúa donde se solapan un
gestor de proyectos, un sistema de gestión documental y un mensajero de equipo —
una intranet operativa en la tradición de Basecamp, no una suite ofimática.
Complementa a Nextcloud en lugar de sustituirlo: Favilla no sincroniza archivos,
hace funcionar tus proyectos, documentos y procesos.

**Lo que Favilla no es:** una suite de sincronización de archivos u ofimática (eso
es Nextcloud / OnlyOffice), un CMS público, un helpdesk de cara al cliente ni un
SaaS multiinquilino. Es un espacio de trabajo operativo interno para una sola
organización, gestionado por esa organización en su propio servidor.

![Panel de Favilla](docs/screenshots/dashboard.png)

## Qué lo hace diferente

- **Crea informes como creas diapositivas.** Un diseñador de arrastrar y soltar
  (GrapesJS) para plantillas PDF y Excel listas para imprimir, directamente en el
  navegador: componentes de datos inteligentes, estilos reutilizables, saneamiento
  en el servidor. Los informes son ciudadanos de primera clase, no una ocurrencia
  tardía.
- **Ayuda que viaja con el producto.** Cada página tiene un panel de ayuda
  contextual respaldado por una base de conocimiento integrada — más de 340
  preguntas y respuestas, cada una en los cinco idiomas, con búsqueda sensible a
  sinónimos y analíticas de administración sobre lo que los usuarios buscan y no
  encuentran. Menos tickets de «¿cómo hago…?» desde el primer día.
- **Una suite de seguridad propia de software de pago.** SSO (OIDC) con PKCE,
  vinculación de cuentas y aprovisionamiento JIT opcional; autenticación de dos
  factores TOTP; un panel de seguridad con detección de incidentes (fuerza bruta,
  CSRF); registro de auditoría completo; políticas de retención de datos; copias de
  seguridad cifradas AES-256-GCM con restauración desde la app; endurecimiento de
  sesiones, limitación de intentos de inicio de sesión, política de contraseñas.
- **Cinco idiomas de serie.** Italiano (la fuente canónica), inglés, francés,
  alemán y español, con un selector por usuario — y no solo la interfaz: también
  las notificaciones y la base de conocimiento de la ayuda están traducidas. (El
  código y la documentación están en inglés.)
- **Un solo código, tres ediciones.** Personal, Team y Developer son el mismo
  producto con ropa distinta: empieza en solitario y crece hasta una intranet
  corporativa sin reinstalar nada. Consulta las [Ediciones](#ediciones).
- **Listo para asistentes de IA.** El repositorio incluye [`CLAUDE.md`](CLAUDE.md),
  inventarios de módulos legibles por máquina (`project_context.json`, `context/`)
  y contratos de arquitectura escritos (`docs/contracts/`), para que los agentes de
  programación y los nuevos colaboradores lo naveguen igual. Gran parte de Favilla
  se construyó en pareja con agentes de IA — ese flujo de trabajo es de primera
  clase, no accidental.

Y los fundamentos están todos ahí:

- **Un panel que es realmente tuyo** — 17 proveedores de widgets en vivo (la agenda
  de hoy, las tareas abiertas, el estado de los proyectos, la salud de las copias…
  incluso el tiempo local); cada usuario elige, oculta y reordena los suyos.
- **Notificaciones basadas en plantillas** — un único despachador, tres canales
  (in-app, correo, Telegram), preferencias por usuario, entrega en cola con
  reintentos/backoff; los administradores controlan el texto y el aspecto desde la
  interfaz.
- **Rápido para moverse** — búsqueda global en todos los módulos, un menú radial
  rápido con clic derecho, actualizaciones parciales HTMX en todas partes, temas
  claro y oscuro.
- **Operación integrada** — un planificador equivalente a cron con interfaz de
  administración, comprobaciones de estado con historial y exportación, rotación de
  registros y una CLI de proyecto (`php favilla`) para la automatización.

## Tecnología aburrida, hecha para durar

Favilla toma dos decisiones deliberadamente pasadas de moda:

1. **PHP 8.2 + HTMX renderizados en el servidor.** Sin SPA, sin paso de build, sin
   `node_modules`. Se despliega en cualquier cosa, de XAMPP a Docker Compose, y
   funciona a gusto en una Raspberry Pi.
2. **Un microframework propio — sin Laravel, sin Symfony.** Una aplicación MVC
   clásica que puedes leer, auditar y ampliar de principio a fin: controladores,
   servicios, repositorios, vistas, sin magia.

Decisiones así solo se sostienen con disciplina detrás: **más de 1.800 pruebas
automatizadas**, **PHPStan nivel 6** y **PSR-12** exigidos en CI, y un **esquema de
más de 100 tablas** instalado por un asistente guiado.

## Capturas de pantalla

| | |
|---|---|
| ![Panel configurable](docs/screenshots/dashboard-configure.png) <br>*Cada widget es tuyo: arrastra para reordenar, toca para ocultar* | ![Ayuda contextual](docs/screenshots/help-online.png) <br>*Ayuda contextual con base de conocimiento buscable, en cada página* |
| ![Tablero kanban](docs/screenshots/tasks-kanban.png) <br>*Las tareas como lista, calendario o tablero kanban* | ![Ajustes de apariencia](docs/screenshots/appearance.png) <br>*Temas, colores, fuentes y estilos de diseño por usuario* |

## Ediciones

Un producto que crece contigo. Favilla nace de un único código en tres ediciones,
elegidas durante el asistente de instalación (o cambiadas después desde Admin →
Configuración):

- **Personal** — un espacio de trabajo de un solo usuario. El registro está
  desactivado y cada superficie multiusuario (roles, compartición, área de
  administración) queda recogida en un discreto rincón de Ajustes. Parece una app
  personal; por debajo sigue siendo toda Favilla.
- **Team** — la intranet corporativa multiusuario: permisos basados en roles,
  registro abierto con aprobación del administrador, y Proyectos, Teams, Documentos
  y Blog activados por defecto.
- **Developer** — para trabajar en la propia Favilla: el repositorio completo,
  incluida la documentación para colaboradores y asistentes de IA (`CLAUDE.md`,
  `docs/contracts/`, `context/`).

| | **Personal** | **Team** | **Developer** |
|---|---|---|---|
| Pensada para | Espacio de trabajo personal de un usuario | Intranet corporativa multiusuario | Contribuir a la propia Favilla |
| Interfaz multiusuario / RBAC | Oculta | Visible | Visible |
| Página de registro | Desactivada (cuenta única) | Abierta | Abierta |
| Proyectos, Teams, Documentos, Blog | Instalables desde Admin → Módulos | **Activados por defecto** | Instalables desde Admin → Módulos |
| Documentación dev e IA | No incluida | No incluida | Incluida |

Una edición cambia lo que la interfaz muestra — nunca lo que el código puede hacer.
**Oculto ≠ desactivado:** el planificador y todos los módulos base funcionan en
cada edición, así que nada de lo que dependen otras funciones (como los
recordatorios) desaparece jamás. Cuando una instalación Personal deja de ser solo
tuya, activa los cuatro módulos de equipo desde **Admin → Módulos** y cambia la
edición en **Admin → Configuración** — sin reinstalación, sin migración, sin
exportar/importar.

## Instalación y documentación completa

La instalación con Docker o XAMPP, los requisitos, la actualización, la lista
completa de funcionalidades módulo a módulo y la documentación para desarrolladores
están en el **[README en inglés](README.md)** y en **[FEATURES.md](FEATURES.md)**.

## Licencia

Favilla se distribuye bajo la **GNU Affero General Public License v3.0 o posterior
(AGPL-3.0-or-later)**. En resumen: si ejecutas una versión modificada de Favilla
como servicio de red, debes poner su código fuente modificado a disposición de sus
usuarios. Texto completo en [`LICENSE`](LICENSE).

<div align="center">
    <img width="300" height="100" alt="mobile-title" src="https://github.com/user-attachments/assets/ceeff067-98e1-4f7c-bb19-9585e501c275" />

<sub>Made in Italy 🇮🇹</sub>
</div>
