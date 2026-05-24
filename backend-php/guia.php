<?php
require_once 'config/auth.php';
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Guía de uso – ProyectoC</title>
<link rel="stylesheet" href="css/style.css?v=1777485477254">
<script src="js/theme-init.js"></script>
<style>
.guia-hero {
    background: linear-gradient(135deg, #1c1c1e 0%, #2c2c2e 100%);
    color: white;
    padding: 48px 24px;
    text-align: center;
    margin: -32px -20px 32px -20px;
    border-radius: 0 0 20px 20px;
}
.guia-hero h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 8px; letter-spacing: -.5px; }
.guia-hero p  { font-size: 1rem; opacity: .85; max-width: 560px; margin: 0 auto; font-weight: 400; }

.guia-container { max-width: 860px; margin: 0 auto; padding: 0 0 40px; }

/* Índice */
.indice {
    background: white; border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,.04), 0 8px 24px rgba(0,0,0,.06);
    padding: 24px 28px; margin-bottom: 28px;
}
.indice h2 { font-size: .95rem; color: #6e6e73; margin-bottom: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
.indice ol { padding-left: 20px; }
.indice li { margin-bottom: 8px; }
.indice a  { color: #0071e3; text-decoration: none; font-weight: 500; }
.indice a:hover { text-decoration: underline; }

/* Pasos */
.paso {
    background: white; border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,.04), 0 8px 24px rgba(0,0,0,.06);
    margin-bottom: 24px; overflow: hidden;
}
.paso-header {
    background: #f5f5f7;
    padding: 16px 20px; display: flex; align-items: center; gap: 14px;
    border-bottom: 1px solid #e0e0e5;
}
.paso-num {
    background: #0071e3; color: white;
    border-radius: 50%; width: 34px; height: 34px; min-width: 34px;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 1rem; flex-shrink: 0;
}
.paso-num.semanal { background: #6c47ff; }
.paso-header h2 { font-size: 1.05rem; margin: 0; font-weight: 700; color: #1d1d1f; }
.paso-header .paso-icon { font-size: 1.4rem; }
.paso-body { padding: 22px 24px; }

/* Sub-pasos */
.subpaso {
    display: flex; gap: 14px; margin-bottom: 18px;
    padding-bottom: 18px; border-bottom: 1px solid #f0f0f2;
}
.subpaso:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.subpaso-num {
    background: #e8f5e9; color: #2e7d32;
    border-radius: 50%; width: 26px; height: 26px; min-width: 26px;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: .85rem; flex-shrink: 0;
}
.subpaso-content h3 { font-size: .95rem; color: #1d1d1f; margin-bottom: 6px; font-weight: 600; }
.subpaso-content p  { font-size: .9rem; color: #444; line-height: 1.6; margin: 0; }

/* Tip */
.tip {
    background: #e3f2fd; border-left: 4px solid #0071e3;
    border-radius: 0 8px 8px 0; padding: 12px 14px;
    font-size: .88rem; color: #0277bd; margin-top: 16px; line-height: 1.5;
}
.tip strong { color: #01579b; font-weight: 600; }

/* Aviso */
.aviso {
    background: #fff8e1; border-left: 4px solid #f9a825;
    border-radius: 0 8px 8px 0; padding: 12px 14px;
    font-size: .88rem; color: #5d4037; margin-top: 16px; line-height: 1.5;
}
.aviso strong { color: #795548; font-weight: 600; }

/* Botón simulado */
.btn-sim {
    display: inline-block; padding: 5px 12px;
    border-radius: 6px; font-size: .8rem; font-weight: 600;
    vertical-align: middle; margin: 0 2px;
}
.btn-sim.verde  { background: #2e7d32; color: white; }
.btn-sim.azul   { background: #0071e3; color: white; }
.btn-sim.rojo   { background: #ff3b30; color: white; }
.btn-sim.gris   { background: #f5f5f7; color: #333; border: 1px solid #e0e0e5; }
.btn-sim.morado { background: #6c47ff; color: white; }

/* Tabla de campos */
.tabla-campos { width: 100%; border-collapse: collapse; font-size: .88rem; margin-top: 10px; }
.tabla-campos th { background: #fafafa; color: #6e6e73; padding: 8px 12px; text-align: left; font-weight: 600; font-size: .75rem; text-transform: uppercase; }
.tabla-campos td { padding: 8px 12px; border-bottom: 1px solid #f0f0f2; }
.obligatorio { color: #ff3b30; font-weight: 700; }

/* Flujo */
.flujo {
    display: flex; align-items: center; justify-content: center;
    flex-wrap: wrap; gap: 0; margin: 20px 0;
}
.flujo-paso {
    background: #e8f5e9; border: 2px solid #2e7d32;
    border-radius: 10px; padding: 12px 16px;
    text-align: center; font-size: .85rem; font-weight: 600; color: #2e7d32;
    min-width: 100px;
}
.flujo-flecha {
    font-size: 1.4rem; color: #2e7d32; padding: 0 8px; font-weight: 700;
}

/* Tipo cards */
.tipo-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 14px 0;
}
.tipo-card-doc {
    border-radius: 10px; padding: 14px 16px; text-align: center;
    font-size: .85rem; font-weight: 600;
}
.tipo-card-doc.general { background: #e8f5e9; border: 2px solid #2e7d32; color: #2e7d32; }
.tipo-card-doc.semanal { background: #ede7ff; border: 2px solid #6c47ff; color: #6c47ff; }
.tipo-card-doc .tipo-big { font-size: 1.8rem; display: block; margin-bottom: 4px; }

/* CTA final */
.cta-final {
    background: linear-gradient(135deg, #1c1c1e 0%, #2c2c2e 100%);
    color: white; border-radius: 16px; padding: 32px 24px;
    text-align: center; margin-top: 32px;
}
.cta-final h2 { margin-bottom: 20px; font-size: 1.3rem; font-weight: 700; }
.cta-final .btn-cta {
    display: inline-block; background: #0071e3; color: white;
    padding: 12px 24px; border-radius: 10px; font-weight: 600;
    text-decoration: none; font-size: .95rem; margin: 6px;
    transition: background .2s;
}
.cta-final .btn-cta:hover { background: #0077ed; }

@media (max-width: 600px) {
    .guia-hero { padding: 36px 16px; margin: -20px -16px 24px -16px; border-radius: 0 0 16px 16px; }
    .guia-hero h1 { font-size: 1.4rem; }
    .paso-body { padding: 18px 16px; }
    .subpaso { gap: 10px; }
    .flujo-paso { min-width: 80px; padding: 10px 12px; font-size: .8rem; }
    .tabla-campos { font-size: .82rem; }
    .tabla-campos th, .tabla-campos td { padding: 6px 8px; }
    .tipo-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<header>
    <h1>🥗 <?= htmlspecialchars(APP_NAME) ?></h1>
    <nav>
        <a href="index.php">Inicio</a>
        <a href="clientes.php">Clientes</a>
        <a href="ingredientes.php">Ingredientes</a>
        <a href="configuracion.php">Configuración</a>
        <a href="guia.php" style="color:#0071e3;font-weight:600">Guía</a>
        <a href="logout.php" style="color:var(--text-muted)">Salir</a>
    </nav>
</header>

<!-- HERO -->
<div class="guia-hero">
    <h1>📖 Manual de uso</h1>
    <p>Crea protocolos generales o dietas semanales día a día, y expórtalas en PDF.</p>
</div>

<div class="guia-container">

    <!-- FLUJO VISUAL -->
    <div class="flujo">
        <div class="flujo-paso">👤<br>Crear cliente</div>
        <div class="flujo-flecha">→</div>
        <div class="flujo-paso">📊<br>Seguimiento</div>
        <div class="flujo-flecha">→</div>
        <div class="flujo-paso">📋/📅<br>Nueva dieta</div>
        <div class="flujo-flecha">→</div>
        <div class="flujo-paso">✅<br>Configurar</div>
        <div class="flujo-flecha">→</div>
        <div class="flujo-paso">🖨<br>PDF</div>
    </div>

    <!-- ÍNDICE -->
    <div class="indice">
        <h2>Contenido</h2>
        <ol>
            <li><a href="#paso1">Crear y gestionar clientes (buscar, editar, seguimiento)</a></li>
            <li><a href="#paso2">Crear un protocolo y elegir el tipo</a></li>
            <li><a href="#paso3">Dieta General – Configurar alimentos permitidos</a></li>
            <li><a href="#paso4">Dieta General – No comer, agua, suplementos y observaciones</a></li>
            <li><a href="#paso5">Dieta Semanal – Planificar comidas, generar con IA e importar CSV</a></li>
            <li><a href="#paso6">Dieta Semanal – No comer, agua, suplementos y tomas visibles</a></li>
            <li><a href="#paso7">Imprimir o guardar en PDF</a></li>
            <li><a href="#paso8">Gestionar ingredientes y exportar para IA</a></li>
            <li><a href="#paso9">Ver dietas del cliente y activar dieta en la app</a></li>
            <li><a href="#paso10">Configuración: SMTP, franjas horarias y plantilla de correo</a></li>
            <li><a href="#paso11">Acceso al sistema y gestión de usuarios</a></li>
        </ol>
    </div>

    <!-- PASO 1 -->
    <div class="paso" id="paso1">
        <div class="paso-header">
            <div class="paso-num">1</div>
            <span class="paso-icon">👤</span>
            <h2>Crear y gestionar clientes</h2>
        </div>
        <div class="paso-body">
            <div class="subpaso">
                <div class="subpaso-num">1</div>
                <div class="subpaso-content">
                    <h3>Ir a la sección Clientes</h3>
                    <p>En el menú superior haz clic en <strong>Clientes</strong>, o desde el panel de inicio pulsa el botón <span class="btn-sim verde">Ver clientes</span>.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">2</div>
                <div class="subpaso-content">
                    <h3>Crear un cliente nuevo</h3>
                    <p>Pulsa <span class="btn-sim azul">+ Nuevo cliente</span> en la esquina superior derecha. Rellena el formulario y pulsa <span class="btn-sim verde">Guardar cliente</span>.</p>
                    <table class="tabla-campos" style="margin-top:10px">
                        <thead><tr><th>Campo</th><th>Descripción</th><th>Obligatorio</th></tr></thead>
                        <tbody>
                            <tr><td><strong>Nombre</strong></td><td>Nombre del paciente</td><td class="obligatorio">Sí</td></tr>
                            <tr><td>Apellidos</td><td>Apellidos del paciente</td><td>No</td></tr>
                            <tr><td>Fecha nacimiento / Sexo</td><td>Datos personales</td><td>No</td></tr>
                            <tr><td>Peso (kg) / Altura (cm)</td><td>Aparecen en la cabecera del PDF</td><td>No</td></tr>
                            <tr><td>Objetivo</td><td>Ej: "Perder 10 kg", "Mantenimiento"</td><td>No</td></tr>
                            <tr><td>Teléfono / Email</td><td>Datos de contacto</td><td>No</td></tr>
                            <tr><td>Observaciones</td><td>Notas internas (no aparecen en el PDF)</td><td>No</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">3</div>
                <div class="subpaso-content">
                    <h3>Código único del paciente</h3>
                    <p>Al guardar, el sistema asigna automáticamente un <strong>código de 8 caracteres</strong> (ej. <code>A3F9C12B</code>) visible en la tabla. Pulsa el botón <strong>⎘</strong> junto al código para copiarlo al portapapeles. Este código identifica al paciente de forma única y se usará en el futuro portal del paciente.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">4</div>
                <div class="subpaso-content">
                    <h3>Buscar clientes</h3>
                    <p>Sobre la tabla hay un campo de búsqueda. Escribe nombre, apellidos o teléfono y la lista se filtra en tiempo real sin recargar la página. Borra el texto para ver todos los clientes.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">5</div>
                <div class="subpaso-content">
                    <h3>Editar los datos de un cliente</h3>
                    <p>Pulsa <span class="btn-sim gris">Editar</span> en la fila del cliente. Se abre el mismo formulario precargado con sus datos actuales. Modifica lo que necesites y pulsa <span class="btn-sim verde">Guardar cambios</span>.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">6</div>
                <div class="subpaso-content">
                    <h3>Seguimiento corporal</h3>
                    <p>Pulsa <span class="btn-sim verde">Seguimiento</span> para acceder al historial de medidas del paciente. Desde ahí puedes:</p>
                    <ul style="margin-top:8px;padding-left:18px;font-size:.9rem;color:#444;line-height:1.9">
                        <li>Ver las <strong>tarjetas resumen</strong> con la última medición (peso, % grasa, % músculo) y la tendencia respecto a la anterior (↑ en rojo / ↓ en verde).</li>
                        <li><strong>Registrar una nueva medición</strong>: introduce fecha, peso, % grasa corporal, % músculo y observaciones opcionales.</li>
                        <li>Consultar el <strong>historial completo</strong> en tabla con flechas de tendencia entre mediciones consecutivas.</li>
                        <li>Eliminar una medición incorrecta con el botón <span class="btn-sim rojo">Eliminar</span>.</li>
                    </ul>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">7</div>
                <div class="subpaso-content">
                    <h3>Acciones disponibles por cliente</h3>
                    <p>Cada fila de la tabla tiene cinco botones:</p>
                    <ul style="margin-top:8px;padding-left:18px;font-size:.9rem;color:#444;line-height:1.9">
                        <li><span class="btn-sim gris">Editar</span> — modifica los datos del cliente.</li>
                        <li><span class="btn-sim verde">Seguimiento</span> — historial de medidas corporales.</li>
                        <li><span class="btn-sim azul">Nueva dieta</span> — crea un protocolo nuevo para ese cliente.</li>
                        <li><span class="btn-sim morado">Ver dietas</span> — lista todos sus protocolos.</li>
                        <li><span class="btn-sim rojo">Eliminar</span> — elimina el cliente y todas sus dietas (pide confirmación).</li>
                    </ul>
                </div>
            </div>
            <div class="tip">
                <strong>💡 Consejo:</strong> El peso y la talla del formulario del cliente aparecen en la cabecera de los protocolos impresos. El seguimiento corporal es independiente y permite registrar la evolución a lo largo del tiempo.
            </div>
        </div>
    </div>

    <!-- PASO 2 -->
    <div class="paso" id="paso2">
        <div class="paso-header">
            <div class="paso-num">2</div>
            <span class="paso-icon">📋</span>
            <h2>Crear un protocolo y elegir el tipo</h2>
        </div>
        <div class="paso-body">
            <div class="subpaso">
                <div class="subpaso-num">1</div>
                <div class="subpaso-content">
                    <h3>Localizar al cliente y pulsar "Nueva dieta"</h3>
                    <p>En la tabla de <strong>Clientes</strong>, busca al paciente y pulsa <span class="btn-sim verde">Nueva dieta</span> en su fila.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">2</div>
                <div class="subpaso-content">
                    <h3>Elegir el tipo de protocolo</h3>
                    <p>En la parte superior del formulario aparecen dos opciones:</p>
                    <div class="tipo-grid" style="margin-top:12px">
                        <div class="tipo-card-doc general">
                            <span class="tipo-big">📋</span>
                            <strong>General</strong><br>
                            <span style="font-size:.78rem;font-weight:400">Lista única de alimentos permitidos para toda la semana</span>
                        </div>
                        <div class="tipo-card-doc semanal">
                            <span class="tipo-big">📅</span>
                            <strong>Semanal</strong><br>
                            <span style="font-size:.78rem;font-weight:400">Planifica cada comida de cada día por separado</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">3</div>
                <div class="subpaso-content">
                    <h3>Rellenar los datos del protocolo</h3>
                    <table class="tabla-campos">
                        <thead><tr><th>Campo</th><th>Descripción</th></tr></thead>
                        <tbody>
                            <tr><td><strong>Nombre del protocolo</strong></td><td>Ej: "Protocolo alimentario", "Fase 1", "Semana 3"</td></tr>
                            <tr><td><strong>Fecha</strong></td><td>Fecha de inicio (hoy por defecto)</td></tr>
                            <tr><td>Observaciones internas</td><td>Notas solo para uso interno, no salen en el PDF</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">4</div>
                <div class="subpaso-content">
                    <h3>Pulsar "Crear y configurar"</h3>
                    <p>El sistema crea el protocolo y te lleva al editor correspondiente según el tipo elegido. Los alimentos restringidos base (aguacate, salmón…) se añaden automáticamente a "No comer" en ambos tipos.</p>
                </div>
            </div>
            <div class="tip">
                <strong>💡 Un paciente puede tener varios protocolos</strong> — generales, semanales, por fases. Todos se listan en <span class="btn-sim azul">Ver dietas</span> del cliente, con un badge que indica el tipo.
            </div>
        </div>
    </div>

    <!-- PASO 3 -->
    <div class="paso" id="paso3">
        <div class="paso-header">
            <div class="paso-num">3</div>
            <span class="paso-icon">📋</span>
            <h2>Dieta General – Configurar alimentos permitidos</h2>
        </div>
        <div class="paso-body">
            <div class="subpaso">
                <div class="subpaso-num">1</div>
                <div class="subpaso-content">
                    <h3>Secciones por categoría</h3>
                    <p>Verás bloques para <strong>Verduras</strong>, <strong>Carne</strong>, <strong>Pescado</strong>, <strong>Fruta</strong> y <strong>Condimentos</strong>. Haz clic en la cabecera de cada bloque para expandirlo o contraerlo.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">2</div>
                <div class="subpaso-content">
                    <h3>Marcar / desmarcar alimentos</h3>
                    <p>Cada alimento tiene una casilla de verificación. Los marcados aparecerán en el protocolo impreso. Usa los atajos <strong>"Seleccionar todos"</strong> y <strong>"Deseleccionar todos"</strong> para actuar sobre toda una categoría de golpe.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">3</div>
                <div class="subpaso-content">
                    <h3>Guardar los cambios</h3>
                    <p>Pulsa <span class="btn-sim verde">💾 Guardar protocolo</span> en la barra superior o al final de la página.</p>
                </div>
            </div>
            <div class="aviso">
                <strong>⚠️ Importante:</strong> Los checkboxes NO se guardan solos. Recuerda pulsar <strong>Guardar protocolo</strong> después de cada cambio.
            </div>
        </div>
    </div>

    <!-- PASO 4 -->
    <div class="paso" id="paso4">
        <div class="paso-header">
            <div class="paso-num">4</div>
            <span class="paso-icon">🚫</span>
            <h2>Dieta General – No comer, huevos y condimentos</h2>
        </div>
        <div class="paso-body">
            <div class="subpaso">
                <div class="subpaso-num">1</div>
                <div class="subpaso-content">
                    <h3>Sección "No comer"</h3>
                    <p>Debajo de los ingredientes hay una sección con borde rojo. Aquí se gestionan los alimentos que aparecen en la línea <strong>"No comer:"</strong> del PDF. Usa el desplegable para añadir y la <strong>✕</strong> en cada etiqueta para quitar.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">2</div>
                <div class="subpaso-content">
                    <h3>Sección Huevos</h3>
                    <p>Modifica el texto de la indicación. Por defecto: <em>"2 por comida / 6 a la semana"</em>.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">3</div>
                <div class="subpaso-content">
                    <h3>Observaciones internas</h3>
                    <p>Campo de notas privadas al final de la página. No aparecen en el PDF impreso.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">4</div>
                <div class="subpaso-content">
                    <h3>Agua diaria</h3>
                    <p>Campo de texto libre para indicar la ingesta de agua recomendada, ej: <em>"2 litros al día"</em> o <em>"1,5-2 L"</em>. Aparece en la app del paciente en la sección 💧.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">5</div>
                <div class="subpaso-content">
                    <h3>Suplementos y complementos</h3>
                    <p>Campo de texto libre para indicar suplementos del protocolo, ej: <em>"Omega-3 1g, Vitamina D 2000 UI"</em>. Aparece en la app del paciente en la sección 💊. Se guarda junto con el botón <span class="btn-sim verde">💾 Guardar protocolo</span>.</p>
                </div>
            </div>
            <div class="tip">
                <strong>💡 Los alimentos base</strong> (aguacate, maíz, zanahoria, salmón…) ya vienen en "No comer" por defecto. Si un paciente concreto <em>sí puede</em> comerlos, basta con pulsar ✕ para quitarlos de su lista.
            </div>
        </div>
    </div>

    <!-- PASO 5 -->
    <div class="paso" id="paso5">
        <div class="paso-header">
            <div class="paso-num semanal">5</div>
            <span class="paso-icon">📅</span>
            <h2>Dieta Semanal – Planificar comidas, generar con IA e importar CSV</h2>
        </div>
        <div class="paso-body">
            <div class="subpaso">
                <div class="subpaso-num">1</div>
                <div class="subpaso-content">
                    <h3>Tabs de días</h3>
                    <p>En la parte superior del editor hay una barra con los 7 días de la semana (<strong>Lunes → Domingo</strong>). Haz clic en un día para ver y editar sus comidas.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">2</div>
                <div class="subpaso-content">
                    <h3>Tarjetas de comida</h3>
                    <p>Cada día tiene <strong>6 tarjetas</strong>: <strong>🌅 Desayuno, 🍎 Media mañana, 🍽️ Almuerzo, 🍲 Comida, 🍊 Merienda, 🌙 Cena</strong>. Cada tarjeta muestra cuántos ingredientes sueltos tiene asignados.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">3</div>
                <div class="subpaso-content">
                    <h3>Añadir ingredientes sueltos a una comida</h3>
                    <p>En el desplegable de cada tarjeta, elige un ingrediente (agrupados por categoría). Al seleccionarlo aparece como una <strong>etiqueta verde</strong> y queda deshabilitado en el selector para no duplicarlo. Pulsa <strong>✕</strong> en la etiqueta para quitarlo.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">4</div>
                <div class="subpaso-content">
                    <h3>Añadir una combinación (plato principal + complemento)</h3>
                    <p>Cada tarjeta tiene el botón <span class="btn-sim azul">⊕ Añadir combinación</span> al final. Al pulsarlo se abre un modal donde puedes indicar:</p>
                    <ul style="margin-top:8px;padding-left:18px;font-size:.9rem;color:#444;line-height:1.9">
                        <li><strong>Plato principal</strong> (obligatorio) — el ingrediente principal de esa comida.</li>
                        <li><strong>Cantidad</strong> del plato principal — ej. <code>150</code> o <code>150g</code> o <code>una ración</code>. Si escribes solo el número se añade la «g» automáticamente.</li>
                        <li><strong>Complemento</strong> (opcional) — ingrediente que acompaña al plato.</li>
                        <li><strong>Cantidad</strong> del complemento — misma lógica que el plato principal.</li>
                    </ul>
                    <p style="margin-top:8px">Las combinaciones guardadas aparecen como <strong>etiquetas azules</strong> con el formato <em>Pechuga de pavo (150g) + Brócoli (200g)</em>. Pulsa <strong>✕</strong> para eliminar una combinación.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">5</div>
                <div class="subpaso-content">
                    <h3>Guardar</h3>
                    <p>Pulsa <span class="btn-sim verde">💾 Guardar</span> en la barra superior para guardar todos los días a la vez. Las combinaciones se guardan en el momento de añadirlas; los ingredientes sueltos requieren pulsar Guardar.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">6</div>
                <div class="subpaso-content">
                    <h3>Panel "🤖 Generar dieta con IA"</h3>
                    <p>Despliega el panel pulsando su cabecera. Rellena los campos:</p>
                    <ul style="margin-top:8px;padding-left:18px;font-size:.9rem;color:#444;line-height:1.9">
                        <li><strong>Objetivo del paciente</strong> — ej. "perder peso", "ganancia muscular".</li>
                        <li><strong>Nº de días</strong> — cuántos días quieres que genere la IA.</li>
                        <li><strong>Tomas a incluir</strong> — marca solo las que uses en la dieta.</li>
                        <li><strong>Indicaciones adicionales</strong> — restricciones, preferencias, etc.</li>
                    </ul>
                    <p style="margin-top:8px">Pulsa <span class="btn-sim azul">📋 Copiar prompt</span> para copiar el texto generado al portapapeles. Pégalo en ChatGPT o Claude y pídele que genere la dieta en formato CSV. El prompt ya incluye la lista de ingredientes permitidos de la base de datos.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">7</div>
                <div class="subpaso-content">
                    <h3>Panel "Importar CSV semanal"</h3>
                    <p>Cuando la IA te devuelva el CSV, guárdalo como archivo <code>.csv</code>. Despliega el panel "Importar CSV semanal", selecciona el archivo y pulsa <span class="btn-sim verde">⬆ Importar</span>. El sistema valida cada fila y evita duplicados automáticamente. Al terminar muestra cuántas filas se importaron y cuántas se omitieron por ya existir.</p>
                    <p style="margin-top:8px">Formato esperado: <code>dia,toma,tipo,ingrediente,cantidad_principal,complemento,cantidad_complemento</code></p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">8</div>
                <div class="subpaso-content">
                    <h3>Vaciar toda la dieta semanal</h3>
                    <p>Si la importación salió mal o quieres empezar de cero, usa el botón <span class="btn-sim rojo">🗑️ Vaciar todo</span> de la barra de acciones. Pide confirmación y borra todos los ingredientes sueltos y combinaciones de todos los días. Esta acción <strong>no se puede deshacer</strong>.</p>
                </div>
            </div>
            <div class="tip">
                <strong>💡 Diferencia entre ingredientes sueltos y combinaciones:</strong> Los <strong>ingredientes sueltos</strong> (etiquetas verdes) aparecen como viñetas en el PDF. Las <strong>combinaciones</strong> (etiquetas azules) aparecen con la etiqueta "Plato principal / Complemento" y permiten indicar cantidades concretas.
            </div>
            <div class="aviso">
                <strong>⚠️ Importante:</strong> Al cambiar de pestaña de día los ingredientes sueltos quedan en memoria hasta que pulses <strong>Guardar</strong>. No cierres la página sin guardar.
            </div>
        </div>
    </div>

    <!-- PASO 6 -->
    <div class="paso" id="paso6">
        <div class="paso-header">
            <div class="paso-num semanal">6</div>
            <span class="paso-icon">🚫</span>
            <h2>Dieta Semanal – No comer y tomas visibles en el informe</h2>
        </div>
        <div class="paso-body">
            <div class="subpaso">
                <div class="subpaso-num">1</div>
                <div class="subpaso-content">
                    <h3>Sección "No comer" en el editor semanal</h3>
                    <p>Justo encima de los tabs de días hay una sección con borde rojo que funciona igual que en la dieta general: desplegable agrupado por categoría para añadir alimentos restringidos, y etiquetas con ✕ para quitarlos. Aparecen en la línea <strong>"No comer:"</strong> de la cabecera del PDF.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">2</div>
                <div class="subpaso-content">
                    <h3>Panel "Mostrar en informe" — activar o desactivar tomas</h3>
                    <p>Encima de los tabs de día hay una barra con los 6 toggles de toma. Por defecto todas están <strong>activas</strong> (en verde). Si una toma no se usa en esta dieta, desactívala pulsando su toggle — quedará tachada en gris y <strong>no aparecerá como fila en el PDF</strong>.</p>
                    <p style="margin-top:8px">Este ajuste se guarda junto con el botón <span class="btn-sim verde">💾 Guardar</span>.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">3</div>
                <div class="subpaso-content">
                    <h3>Agua diaria y suplementos</h3>
                    <p>Al final del editor semanal hay dos campos de texto libre:</p>
                    <ul style="margin-top:8px;padding-left:18px;font-size:.9rem;color:#444;line-height:1.9">
                        <li><strong>💧 Agua diaria</strong> — ej. <em>"2 litros al día"</em>.</li>
                        <li><strong>💊 Suplementos/complementos</strong> — ej. <em>"Omega-3 1g, Vitamina D 2000 UI"</em>.</li>
                    </ul>
                    <p style="margin-top:8px">Ambos campos se guardan al pulsar <span class="btn-sim verde">💾 Guardar</span> y aparecen en la app del paciente.</p>
                </div>
            </div>
            <div class="tip">
                <strong>💡</strong> Los alimentos restringidos base se añaden automáticamente al crear la dieta semanal, igual que en la general.
            </div>
            <div class="tip" style="margin-top:8px">
                <strong>💡 Ejemplo de uso de los toggles:</strong> Si el paciente no hace merienda, desactiva <em>🍊 Merienda</em> para que el PDF no muestre esa fila vacía y quede más limpio.
            </div>
        </div>
    </div>

    <!-- PASO 7 -->
    <div class="paso" id="paso7">
        <div class="paso-header">
            <div class="paso-num">7</div>
            <span class="paso-icon">🖨</span>
            <h2>Imprimir o guardar en PDF</h2>
        </div>
        <div class="paso-body">
            <div class="subpaso">
                <div class="subpaso-num">1</div>
                <div class="subpaso-content">
                    <h3>Abrir la vista de impresión</h3>
                    <p>Pulsa <span class="btn-sim azul">🖨️ Guardar e Imprimir</span> en la barra del editor (guarda y abre el PDF a la vez), o pulsa <span class="btn-sim azul">Ver / PDF</span> desde la lista de dietas del cliente.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">2</div>
                <div class="subpaso-content">
                    <h3>Formato según tipo</h3>
                    <p>
                        <strong>📋 General:</strong> formato vertical con columnas de alimentos por categoría.<br>
                        <strong>📅 Semanal:</strong> tabla <strong>A4 apaisada</strong> con 7 columnas (días) y una fila por cada toma activa. En cada celda aparecen primero las <strong>combinaciones</strong> (con etiquetas "PLATO PRINCIPAL" y "COMPLEMENTO") y luego los ingredientes sueltos como viñetas.
                    </p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">3</div>
                <div class="subpaso-content">
                    <h3>Guardar como PDF</h3>
                    <p>Pulsa el botón <strong>"🖨️ Imprimir / Guardar PDF"</strong> de la vista. En el diálogo del navegador, en <strong>Destino / Impresora</strong>, elige <strong>"Guardar como PDF"</strong>.</p>
                </div>
            </div>
            <div class="tip">
                <strong>💡 Consejo:</strong> Para la dieta semanal activa la opción <strong>"Gráficos de fondo"</strong> en más ajustes del diálogo de impresión para que los fondos oscuros de la cabecera se impriman correctamente.
            </div>
        </div>
    </div>

    <!-- PASO 8 -->
    <div class="paso" id="paso8">
        <div class="paso-header">
            <div class="paso-num">8</div>
            <span class="paso-icon">🥦</span>
            <h2>Gestionar ingredientes y categorías</h2>
        </div>
        <div class="paso-body">
            <div class="subpaso">
                <div class="subpaso-num">1</div>
                <div class="subpaso-content">
                    <h3>Ver y filtrar ingredientes</h3>
                    <p>Ve a <strong>Ingredientes</strong> en el menú. Puedes filtrar por categoría pulsando los botones de la fila superior (Todos, Verduras, Carne, etc.).</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">2</div>
                <div class="subpaso-content">
                    <h3>Añadir un ingrediente nuevo</h3>
                    <p>Pulsa <span class="btn-sim verde">+ Añadir ingrediente</span>. Rellena el nombre, elige la categoría y marca si es <strong>"No permitido"</strong> para que aparezca en "No comer" por defecto al crear nuevas dietas.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">3</div>
                <div class="subpaso-content">
                    <h3>Editar un ingrediente</h3>
                    <p>Pulsa <span class="btn-sim azul">Editar</span> en la fila del ingrediente. Se abre un formulario inline para cambiar nombre, categoría o estado. Confirma con <strong>"💾 Guardar cambios"</strong>.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">4</div>
                <div class="subpaso-content">
                    <h3>Gestionar categorías</h3>
                    <p>Despliega el panel <strong>"🗂️ Gestionar categorías"</strong> haciendo clic en su cabecera. Desde ahí puedes:</p>
                    <ul style="margin-top:8px;padding-left:18px;font-size:.9rem;color:#444;line-height:1.8">
                        <li><strong>Crear</strong> una categoría nueva: nombre interno (sin espacios), emoji opcional y etiqueta visible.</li>
                        <li><strong>Eliminar</strong> una categoría existente seleccionándola del desplegable (los ingredientes que la usen conservan el valor).</li>
                    </ul>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">5</div>
                <div class="subpaso-content">
                    <h3>Desactivar un ingrediente</h3>
                    <p>Pulsa <span class="btn-sim rojo">Quitar</span>. El ingrediente se desactiva (no se borra) y deja de aparecer en nuevos protocolos. Los ya guardados no cambian.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">6</div>
                <div class="subpaso-content">
                    <h3>Exportar ingredientes para IA</h3>
                    <p>Despliega el panel <strong>"📤 Exportar ingredientes"</strong>. Desde ahí puedes:</p>
                    <ul style="margin-top:8px;padding-left:18px;font-size:.9rem;color:#444;line-height:1.8">
                        <li><span class="btn-sim azul">⬇ Descargar CSV</span> — descarga todos los ingredientes activos en formato CSV con nombre, categoría y macronutrientes.</li>
                        <li><span class="btn-sim gris">Copiar lista</span> — copia la lista al portapapeles agrupada por categoría, lista para pegar en ChatGPT o Claude y pedir sugerencias de ingredientes nuevos.</li>
                    </ul>
                </div>
            </div>
            <div class="aviso">
                <strong>⚠️ Nota:</strong> Los cambios en ingredientes solo afectan a los protocolos que se creen a partir de ese momento. Las dietas ya guardadas no cambian.
            </div>
        </div>
    </div>

    <!-- PASO 9 -->
    <div class="paso" id="paso9">
        <div class="paso-header">
            <div class="paso-num">9</div>
            <span class="paso-icon">📱</span>
            <h2>Ver dietas del cliente y activar dieta en la app</h2>
        </div>
        <div class="paso-body">
            <div class="subpaso">
                <div class="subpaso-num">1</div>
                <div class="subpaso-content">
                    <h3>Acceder a la lista de dietas</h3>
                    <p>Desde la tabla de clientes, pulsa <span class="btn-sim morado">Ver dietas</span> en la fila del paciente. Verás todas sus dietas ordenadas de más reciente a más antigua, con su nombre, fecha y tipo (📋 General / 📅 Semanal).</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">2</div>
                <div class="subpaso-content">
                    <h3>Dieta activa en la app del paciente</h3>
                    <p>La columna <strong>"App"</strong> indica qué dieta ve el paciente en su móvil:</p>
                    <ul style="margin-top:8px;padding-left:18px;font-size:.9rem;color:#444;line-height:1.9">
                        <li><strong>Modo automático (por defecto):</strong> la app muestra siempre la dieta más reciente. Aparece un banner azul informativo y el badge <span style="background:#e8f5e9;color:#2e7d32;padding:2px 8px;border-radius:10px;font-size:.8rem;font-weight:600">✓ En app</span> en la primera fila.</li>
                        <li><strong>Fijar una dieta concreta:</strong> pulsa <span class="btn-sim verde">Activar en app</span> en cualquier fila. El badge se mueve a esa dieta y aparece un banner verde con el botón <span class="btn-sim gris">Usar más reciente</span> para volver al modo automático.</li>
                    </ul>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">3</div>
                <div class="subpaso-content">
                    <h3>Eliminar una dieta</h3>
                    <p>Pulsa <span class="btn-sim rojo">Eliminar</span> en la fila. El sistema borra la dieta y todos sus datos asociados (ingredientes, combinaciones, no permitidos). Si era la dieta activa en la app, vuelve al modo automático automáticamente.</p>
                </div>
            </div>
            <div class="tip">
                <strong>💡 Uso típico:</strong> Fija una dieta concreta cuando el paciente está en una fase de tratamiento y no quieres que la app salte automáticamente a un protocolo nuevo que aún estás preparando.
            </div>
        </div>
    </div>

    <!-- PASO 10 -->
    <div class="paso" id="paso10">
        <div class="paso-header">
            <div class="paso-num">10</div>
            <span class="paso-icon">⚙️</span>
            <h2>Configuración: SMTP, franjas horarias y plantilla de correo</h2>
        </div>
        <div class="paso-body">
            <div class="subpaso">
                <div class="subpaso-num">1</div>
                <div class="subpaso-content">
                    <h3>Franjas horarias por toma</h3>
                    <p>La primera sección de Configuración permite ajustar la hora de inicio y fin de cada toma del día (Desayuno, Media mañana, Almuerzo, Comida, Merienda, Cena). Estas franjas determinan qué toma está activa en cada momento en la app del paciente. Guarda con <span class="btn-sim verde">💾 Guardar franjas</span>.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">2</div>
                <div class="subpaso-content">
                    <h3>Configuración SMTP para envío de correos</h3>
                    <p>En la sección <strong>Configuración de correo</strong> puedes ajustar el servidor SMTP, puerto, usuario, contraseña y el nombre y dirección del remitente. Usa el botón <strong>"Enviar correo de prueba"</strong> para verificar que la configuración funciona antes de usar el correo de bienvenida con clientes reales.</p>
                    <p style="margin-top:8px"><em>Nota: la contraseña solo se actualiza si escribes algo nuevo en el campo; si lo dejas vacío se conserva la anterior.</em></p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">3</div>
                <div class="subpaso-content">
                    <h3>Plantilla de correo de bienvenida</h3>
                    <p>Edita los 6 campos de la plantilla: asunto, saludo, intro, etiqueta del código, instrucciones y pie. Puedes usar las variables <code>{nombre}</code> y <code>{codigo}</code> que se sustituyen automáticamente por los datos del cliente al enviar. El panel de la derecha muestra una <strong>preview en tiempo real</strong> del correo según escribes.</p>
                    <p style="margin-top:8px">Para enviar el correo al crear un cliente, marca el checkbox <em>"Enviar correo de bienvenida"</em> en el formulario de creación.</p>
                </div>
            </div>
            <div class="tip">
                <strong>💡 Gmail:</strong> Si usas Gmail como servidor SMTP, activa la verificación en dos pasos en tu cuenta y genera una <strong>Contraseña de aplicación</strong> desde la configuración de seguridad. No uses tu contraseña normal de Gmail.
            </div>
        </div>
    </div>

    <!-- PASO 11 -->
    <div class="paso" id="paso11">
        <div class="paso-header">
            <div class="paso-num">11</div>
            <span class="paso-icon">🔐</span>
            <h2>Acceso al sistema y gestión de usuarios</h2>
        </div>
        <div class="paso-body">
            <div class="subpaso">
                <div class="subpaso-num">1</div>
                <div class="subpaso-content">
                    <h3>Inicio de sesión</h3>
                    <p>El backend está protegido por contraseña. Al acceder a cualquier página sin sesión activa, el sistema redirige automáticamente a la pantalla de <strong>acceso</strong> donde se piden usuario y contraseña.</p>
                    <p style="margin-top:8px">Las <strong>credenciales por defecto</strong> la primera vez que se instala son:<br>
                    <code style="background:#f5f5f7;padding:2px 8px;border-radius:6px">Usuario: admin</code> &nbsp;
                    <code style="background:#f5f5f7;padding:2px 8px;border-radius:6px">Contraseña: admin123</code><br>
                    <em style="font-size:.85rem;color:#666">Cámbialas desde Configuración en cuanto entres por primera vez.</em></p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">2</div>
                <div class="subpaso-content">
                    <h3>Cerrar sesión</h3>
                    <p>Pulsa el enlace <strong>Salir</strong> en la esquina superior derecha del menú de navegación. La sesión se destruye y el sistema vuelve a la pantalla de acceso.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">3</div>
                <div class="subpaso-content">
                    <h3>Ver y gestionar usuarios</h3>
                    <p>Ve a <strong>Configuración</strong> y despliega el panel <strong>👤 Gestión de usuarios</strong>. Verás la lista de todos los usuarios con su estado (activo / inactivo) y los botones de acción:</p>
                    <ul style="margin-top:8px;padding-left:18px;font-size:.9rem;color:#444;line-height:1.9">
                        <li><span class="btn-sim azul">Editar</span> — modifica el nombre o la contraseña del usuario.</li>
                        <li><span class="btn-sim gris">Desactivar</span> / <span class="btn-sim verde">Activar</span> — bloquea o reactiva el acceso sin eliminar el usuario.</li>
                        <li><span class="btn-sim rojo">Eliminar</span> — elimina permanentemente el usuario.</li>
                    </ul>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">4</div>
                <div class="subpaso-content">
                    <h3>Crear un usuario nuevo</h3>
                    <p>Dentro del mismo panel, rellena el formulario inferior con <strong>Nombre completo</strong>, <strong>Nombre de usuario</strong> (sin espacios) y <strong>Contraseña</strong>, y pulsa <span class="btn-sim verde">Crear usuario</span>.</p>
                </div>
            </div>
            <div class="subpaso">
                <div class="subpaso-num">5</div>
                <div class="subpaso-content">
                    <h3>Cambiar la contraseña</h3>
                    <p>Pulsa <span class="btn-sim azul">Editar</span> en tu usuario. Escribe la nueva contraseña en el campo correspondiente y guarda. Si dejas el campo vacío, la contraseña actual se mantiene sin cambios.</p>
                </div>
            </div>
            <div class="aviso">
                <strong>⚠️ Seguridad:</strong> No se puede eliminar ni desactivar el último usuario activo, ni tampoco tu propia cuenta mientras tienes sesión abierta. Esto evita quedarse sin acceso al sistema.
            </div>
            <div class="tip">
                <strong>💡 Primer uso:</strong> Entra con <code>admin</code> / <code>admin123</code>, ve a Configuración → Gestión de usuarios, edita el usuario <em>admin</em> y cambia la contraseña por una segura.
            </div>
        </div>
    </div>

    <!-- CTA FINAL -->
    <div class="cta-final">
        <h2>¿Listo para empezar?</h2>
        <a href="clientes.php?nuevo=1" class="btn-cta">👤 Crear primer cliente</a>
        <a href="clientes.php" class="btn-cta">📋 Ver clientes</a>
        <a href="ingredientes.php" class="btn-cta">🥦 Ver ingredientes</a>
        <a href="configuracion.php" class="btn-cta">⚙️ Configuración</a>
    </div>

</div>
<script src="js/theme.js"></script>
</body>
</html>
