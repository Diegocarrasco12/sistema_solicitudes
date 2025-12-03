<?php

/**
 * formulario2.php — Servicios Generales
 * Versión final corregida con estructura REAL de tus tablas.
 */

session_start();

/* ============================================================
   CONTROL DE SESIÓN E INACTIVIDAD
============================================================ */
$max_inactividad = 300;
if (isset($_SESSION['ultimo_acceso'])) {
    if (time() - $_SESSION['ultimo_acceso'] > $max_inactividad) {
        session_unset();
        session_destroy();
        header("Location: index.php?expirado=1");
        exit();
    }
}
$_SESSION['ultimo_acceso'] = time();

if (!isset($_SESSION['rut']) || empty($_SESSION['rut'])) {
    header('Location: index.php');
    exit();
}

/* ============================================================
   DATOS DEL USUARIO (tomados de sesión)
============================================================ */
$nombre   = $_SESSION['nombre'] ?? '';
$telefono = $_SESSION['telefono'] ?? '';
$correo   = $_SESSION['correo'] ?? '';
$rut      = $_SESSION['rut'] ?? '';

/* ============================================================
   IMPORTAR AMBAS CONEXIONES (TI + SG)
============================================================ */
require_once 'conexion.php';   // Base TI (usuarios/login)
require_once 'db_sg.php';      // Base SG (categorías, técnicos, admin_sg, tickets)

/* ============================================================
   VALIDAR SI ES ADMIN SG
============================================================ */
$es_admin_sg = false;

$stmt = $mysqli_sg->prepare("SELECT id FROM admin_sg WHERE rut = ? AND activo = 1 LIMIT 1");
if ($stmt) {
    $stmt->bind_param("s", $rut);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $es_admin_sg = true;
    }
    $stmt->close();
}

/* ============================================================
   LISTA DE USUARIOS (SOLO ADMIN SG)
============================================================ */
$usuarios_registrados = [];
if ($es_admin_sg) {
    $rs = $conexion->query("
        SELECT rut, nombre_completo, correo_electronico, telefono
        FROM usuarios
        ORDER BY nombre_completo ASC
    ");
    while ($u = $rs->fetch_assoc()) {
        $usuarios_registrados[] = $u;
    }
}

/* ============================================================
   CATEGORÍAS SG
============================================================ */
$categorias = [];
$resCat = $mysqli_sg->query("
    SELECT id, nombre 
    FROM categorias_sg 
    WHERE activo = 1 
    ORDER BY nombre ASC
");
while ($c = $resCat->fetch_assoc()) {
    $categorias[] = $c;
}

/* ============================================================
   TÉCNICOS SG (estructura REAL: id, nombre, rut, telefono, activo)
============================================================ */
$tecnicos = [];
$resTec = $mysqli_sg->query("
    SELECT nombre, rut, telefono 
    FROM tecnicos_sg 
    WHERE activo = 1 
    ORDER BY nombre ASC
");
while ($t = $resTec->fetch_assoc()) {
    $tecnicos[] = $t;
}

/* ============================================================
   CONFIG NOTIFICACIONES SG
============================================================ */
$emails_csv = '';
$stmtCfg = $mysqli_sg->query("SELECT emails_csv FROM config_notificaciones_sg LIMIT 1");
if ($stmtCfg && $rowCfg = $stmtCfg->fetch_assoc()) {
    $emails_csv = $rowCfg['emails_csv'] ?? '';
}

/* ============================================================
   ADMINISTRADORES SG (estructura REAL: id, nombre, rut, telefono, activo)
============================================================ */
$admins_sg = [];
$q = $mysqli_sg->query("
    SELECT nombre, rut, telefono
    FROM admin_sg
    WHERE activo = 1
    ORDER BY nombre ASC
");
while ($a = $q->fetch_assoc()) {
    $admins_sg[] = $a;
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Servicios Generales</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            font-family: Arial;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: url('assets/img/tkt.jpg') center/cover no-repeat;
            filter: blur(8px);
            z-index: -1;
        }

        body {
            color: white;
            min-height: 100vh;
        }

        .logo {
            width: 50%;
            max-width: 380px;
            margin: 20px auto;
            display: block;
        }

        @media (max-width:768px) {
            .logo {
                width: 90%;
                background: white;
                padding: 10px;
                border-radius: 10px;
            }
        }

        .ticket-container {
            max-width: 700px;
            margin: 0 auto;
            padding: 25px;
            background: rgba(0, 0, 0, 0.75);
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.5);
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        input,
        select,
        textarea {
            padding: 8px;
            border: 2px solid #ccc;
            border-radius: 6px;
            font-size: 16px;
        }

        input[readonly] {
            background: #e6e6e6;
            color: #000;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        button[type=submit] {
            background: #007BFF;
            color: white;
            font-weight: bold;
            border: none;
            padding: 10px;
            border-radius: 6px;
            cursor: pointer;
        }

        button[type=submit]:hover {
            background: #0056b3;
        }

        .info-section {
            max-width: 700px;
            margin: 40px auto;
            padding: 20px;
            background: white;
            color: #222;
            border-radius: 10px;
        }
    </style>
</head>

<body>

    <!-- Menú usuario -->
    <div class="user-menu" style="position:absolute; top:10px; right:20px;">
        <button onclick="toggleUsuarioMenu()" style="background:none; border:none; color:white; font-weight:bold;">
            Bienvenid@ <?= htmlspecialchars($nombre) ?> ▼
        </button>
        <div id="menuUsuario" style="display:none; background:#222; padding:10px; border-radius:5px;">
            <form action="logout.php" method="post">
                <?php if ($es_admin_sg): ?>
                    <a href="sg_admin.php" class="btn btn-primary w-100 mb-2">Panel SG</a>
                <?php endif; ?>
                <button type="submit" class="btn btn-danger w-100">Cerrar sesión</button>
            </form>
        </div>
    </div>


    <img src="assets/img/logo.png" class="logo">

    <div class="ticket-container">
        <h2 class="text-center mb-3">Crear ticket de Servicios Generales</h2>

        <form method="POST" action="procesar_ticket_sg.php" enctype="multipart/form-data">

            <?php if ($es_admin_sg): ?>
                <label>Usuario</label>
                <select name="nombre" id="nombreAdmin" class="form-select" onchange="actualizarUsuario()" required>
                    <option value="">Seleccionar usuario</option>
                    <?php foreach ($usuarios_registrados as $u): ?>
                        <option
                            value="<?= htmlspecialchars($u['nombre_completo']) ?>"
                            data-rut="<?= htmlspecialchars($u['rut']) ?>"
                            data-correo="<?= htmlspecialchars($u['correo_electronico']) ?>"
                            data-telefono="<?= htmlspecialchars($u['telefono']) ?>">
                            <?= htmlspecialchars($u['nombre_completo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            <?php else: ?>
                <label>Nombre</label>
                <input type="text" name="nombre" value="<?= htmlspecialchars($nombre) ?>" readonly>
            <?php endif; ?>

            <label>RUT</label>
            <input type="text" id="rut" name="rut" value="<?= htmlspecialchars($rut) ?>" <?= $es_admin_sg ? '' : 'readonly' ?>>

            <label>Teléfono</label>
            <input type="text" id="telefono" name="telefono" value="<?= htmlspecialchars($telefono) ?>" required>

            <label>Correo</label>
            <input type="email" id="correo" name="correo" value="<?= htmlspecialchars($correo) ?>" required>

            <label>Tipo</label>
            <select name="tipo" required>
                <option value="">Seleccionar tipo</option>
                <option value="Solicitud">Solicitud</option>
                <option value="Incidencia">Incidencia</option>
                <option value="Reclamo">Reclamo</option>
            </select>

            <label>Categoría</label>
            <select name="categoria_id" required>
                <option value="">Seleccionar categoría</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Empresa</label>
            <select name="empresa" required>
                <option value="">Seleccionar Empresa</option>
                <option value="Faret">Faret</option>
                <option value="Innpack">Innpack</option>
                <option value="SFM">SFM</option>
            </select>

            <label>Detalle</label>
            <textarea name="detalle" maxlength="500" required></textarea>

            <label>Adjuntar archivo (max 5MB)</label>
            <input type="file" name="archivo" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">

            <button type="submit">Crear ticket</button>

        </form>
    </div>

    <div class="info-section">
        <h4>Técnicos SG</h4>
        <ul>
            <?php foreach ($tecnicos as $t): ?>
                <li><strong><?= htmlspecialchars($t['nombre']) ?></strong>
                    <?php if ($t['telefono']) echo " – Tel: " . htmlspecialchars($t['telefono']); ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <h4>Categorías</h4>
        <ul>
            <?php foreach ($categorias as $c): ?>
                <li><?= htmlspecialchars($c['nombre']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <script>
        function toggleUsuarioMenu() {
            let m = document.getElementById("menuUsuario");
            m.style.display = m.style.display === "block" ? "none" : "block";
        }

        function actualizarUsuario() {
            const op = document.querySelector("#nombreAdmin").selectedOptions[0];
            document.getElementById("rut").value = op.dataset.rut;
            document.getElementById("correo").value = op.dataset.correo;
            document.getElementById("telefono").value = op.dataset.telefono;
        }
    </script>

</body>

</html>