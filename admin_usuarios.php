<?php
session_start();
if (!isset($_SESSION['rut']) || empty($_SESSION['rut'])) {
    die("Acceso denegado.");
}

include("conexion.php");

$rut = $_SESSION['rut'];
$query = "SELECT nivel_admin FROM usuarios WHERE rut = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("s", $rut);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) die("Acceso denegado.");
$nivel_actual = (int)$row['nivel_admin'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administración de Usuarios</title>
    <style>
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: url('tkt.jpg') no-repeat center center fixed;
            background-size: cover;
            filter: blur(8px);
            z-index: -1;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0 15px;
            background-color: rgba(255, 255, 255, 0.8);
        }

        header {
            text-align: center;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 20px 0;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .container {
            max-width: 1200px;
            margin: auto;
            background: rgba(255,255,255,0.95);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }

        .search-box {
            margin-bottom: 20px;
            text-align: right;
        }

        input[type="text"] {
            padding: 8px;
            font-size: 14px;
            border-radius: 5px;
            border: 1px solid #ccc;
            width: 220px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th, td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #007BFF;
            color: white;
            text-align: left;
        }

        tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .btn {
            padding: 6px 10px;
            font-size: 13px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            color: white;
        }

        .btn-danger { background-color: #dc3545; }
        .btn-danger:hover { background-color: #c82333; }

        .btn-warning { background-color: #ffc107; color: #000; }
        .btn-warning:hover { background-color: #e0a800; }
                .btn-info { background-color: #0d6efd; }
        .btn-info:hover { background-color: #0b5ed7; }

        /* Modal para editar usuario */
        .modal {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .modal-contenido {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            max-width: 400px;
            width: 95%;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            font-size: 14px;
        }

        .modal-contenido h2 {
            margin-top: 0;
            margin-bottom: 15px;
        }

        .modal-contenido label {
            display: block;
            margin-bottom: 4px;
            font-weight: bold;
        }

        .modal-contenido input[type="text"],
        .modal-contenido input[type="email"] {
            width: 100%;
            padding: 7px;
            margin-bottom: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        .modal-botones {
            text-align: right;
            margin-top: 10px;
        }

        .btn-secondary {
            background-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }


        select {
            padding: 6px;
            font-size: 14px;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<header>
    <h1>Administración de Usuarios</h1>
</header>

<div style="text-align: left; margin: 20px 0;">
    <a href="admin.php" style="
        display: inline-block;
        background-color: #007BFF;
        color: white;
        padding: 8px 16px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        font-size: 14px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        transition: background-color 0.3s;
    ">
        ← Volver
    </a>
</div>

<div class="container">
    <div class="search-box">
        <input type="text" id="buscarInput" placeholder="Buscar por nombre, correo o rut..." onkeyup="filtrarUsuarios()">
    </div>

    <table id="tablaUsuarios">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Correo</th>
                <th>RUT</th>
                <th>Teléfono</th>
                <th>¿Es Administrador?</th>
                <th>Cambiar Nivel</th>
                <th>Editar</th>
                <th>Eliminar</th>
                <th>Resetear Clave</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $sql = "SELECT id, nombre_completo, correo_electronico, rut, telefono, nivel_admin, es_admin FROM usuarios ORDER BY nombre_completo";
        $result = $conexion->query($sql);

        if (!$result) {
            echo "<tr><td colspan='9'>Error en la consulta: " . $conexion->error . "</td></tr>";
        } else {
            while ($row = $result->fetch_assoc()) {
                $id = $row['id'];
                $nivel_objetivo = (int)$row['nivel_admin'];
                $admin_actual = (int)$row['es_admin'];

                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['nombre_completo']) . "</td>";
                echo "<td>" . htmlspecialchars($row['correo_electronico']) . "</td>";
                echo "<td>" . htmlspecialchars($row['rut']) . "</td>";
                echo "<td>" . htmlspecialchars($row['telefono']) . "</td>";

                echo "<td>";
                if ($nivel_actual == 1 || ($nivel_actual == 2 && $nivel_objetivo >= 2)) {
                    echo "<select onchange=\"cambiarAdmin($id, this.value)\" id='admin_$id'>
                        <option value='1'" . ($admin_actual == 1 ? " selected" : "") . ">Sí</option>
                        <option value='0'" . ($admin_actual == 0 ? " selected" : "") . ">No</option>
                    </select>";
                } else {
                    echo $admin_actual ? "Sí" : "No";
                }
                echo "</td>";

                echo "<td>";
                if ($nivel_actual == 1 || ($nivel_actual == 2 && $nivel_objetivo >= 2)) {
                    echo "<select id='nivel_$id' onchange=\"cambiarNivel($id, this.value)\">";
                    for ($i = 1; $i <= 3; $i++) {
                        if ($nivel_actual == 2 && $i == 1) continue;
                        $selected = ($nivel_objetivo == $i) ? "selected" : "";
                        echo "<option value='$i' $selected>$i</option>";
                    }
                    echo "</select>";
                } else {
                    echo $nivel_objetivo;
                }
                echo "</td>";
                                // Botón Editar
                echo "<td>";
                echo "<button class='btn btn-info' onclick=\"abrirModalEditar($id, '"
                    . htmlspecialchars($row['nombre_completo'], ENT_QUOTES) . "', '"
                    . htmlspecialchars($row['correo_electronico'], ENT_QUOTES) . "', '"
                    . htmlspecialchars($row['rut'], ENT_QUOTES) . "', '"
                    . htmlspecialchars($row['telefono'], ENT_QUOTES)
                    . "')\">Editar</button>";
                echo "</td>";


                echo "<td>";
                if ($nivel_actual == 1 || ($nivel_actual == 2 && $nivel_objetivo >= 2)) {
                    echo "<button class='btn btn-danger' onclick='eliminarUsuario($id)'>Eliminar</button>";
                } else {
                    echo "<span style='color:#aaa;'>No permitido</span>";
                }
                echo "</td>";

                echo "<td>";
                if ($nivel_actual == 1 || ($nivel_actual == 2 && $nivel_objetivo >= 2)) {
                    echo "<button class='btn btn-warning' onclick='resetearClave($id)'>Resetear</button>";
                } else {
                    echo "<span style='color:#aaa;'>No permitido</span>";
                }
                echo "</td>";

                echo "</tr>";
            }
        }
        ?>
        </tbody>
    </table>
</div>

<script>
function cambiarNivel(id, nivel) {
    if (!confirm("¿Deseas cambiar el nivel de administrador?")) return;
    fetch("asignar_nivel_admin.php", {
        method: "POST",
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: "id=" + id + "&nivel=" + nivel
    })
    .then(res => res.text())
    .then(alert);
}

function cambiarAdmin(id, valor) {
    fetch("actualizar_admin_estado.php", {
        method: "POST",
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: "id=" + id + "&es_admin=" + valor
    })
    .then(res => res.text())
    .then(msg => {
        const comboNivel = document.getElementById('nivel_' + id);
        comboNivel.disabled = (valor === "0");
        alert(msg);
    });
}

function eliminarUsuario(id) {
    if (!confirm("¿Estás seguro de eliminar este usuario?")) return;
    fetch("eliminar_admin.php", {
        method: "POST",
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: "id=" + id
    })
    .then(res => res.text())
    .then(msg => {
        alert(msg);
        location.reload();
    });
}

function resetearClave(id) {
    if (!confirm("¿Deseas resetear la contraseña de este usuario a 'solicitudes123'?")) return;
    fetch("resetear_clave.php", {
        method: "POST",
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: "id=" + id
    })
    .then(res => res.text())
    .then(alert);
}

function filtrarUsuarios() {
    const filtro = document.getElementById('buscarInput').value.toLowerCase();
    const filas = document.querySelectorAll('#tablaUsuarios tbody tr');

    filas.forEach(fila => {
        const texto = fila.innerText.toLowerCase();
        fila.style.display = texto.includes(filtro) ? '' : 'none';
    });

}
    function abrirModalEditar(id, nombre, correo, rut, telefono) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nombre').value = nombre;
    document.getElementById('edit_correo').value = correo;
    document.getElementById('edit_rut').value = rut;
    document.getElementById('edit_telefono').value = telefono;

    document.getElementById('modalEditar').style.display = 'flex';
}

function cerrarModalEditar() {
    document.getElementById('modalEditar').style.display = 'none';
}

function guardarCambiosUsuario() {
    const id       = document.getElementById('edit_id').value;
    const nombre   = document.getElementById('edit_nombre').value.trim();
    const correo   = document.getElementById('edit_correo').value.trim();
    const rut      = document.getElementById('edit_rut').value.trim();
    const telefono = document.getElementById('edit_telefono').value.trim();

    if (!nombre || !correo || !rut) {
        alert("Nombre, correo y RUT son obligatorios.");
        return;
    }

    // 🔹 Por ahora ya dejamos preparado el fetch al backend
    fetch("actualizar_usuario.php", {
        method: "POST",
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body:
            "id=" + encodeURIComponent(id) +
            "&nombre_completo=" + encodeURIComponent(nombre) +
            "&correo_electronico=" + encodeURIComponent(correo) +
            "&rut=" + encodeURIComponent(rut) +
            "&telefono=" + encodeURIComponent(telefono)
    })
    .then(res => res.text())
    .then(msg => {
        alert(msg);
        cerrarModalEditar();
        location.reload();
    })
    .catch(err => {
        console.error(err);
        alert("Ocurrió un error al actualizar el usuario.");
    });
}

</script>
<!-- Modal Editar Usuario -->
<div id="modalEditar" class="modal">
    <div class="modal-contenido">
        <h2>Editar Usuario</h2>

        <input type="hidden" id="edit_id">

        <label for="edit_nombre">Nombre completo</label>
        <input type="text" id="edit_nombre">

        <label for="edit_correo">Correo electrónico</label>
        <input type="email" id="edit_correo">

        <label for="edit_rut">RUT</label>
        <input type="text" id="edit_rut">

        <label for="edit_telefono">Teléfono</label>
        <input type="text" id="edit_telefono">

        <div class="modal-botones">
            <button class="btn btn-secondary" type="button" onclick="cerrarModalEditar()">Cancelar</button>
            <button class="btn btn-info" type="button" onclick="guardarCambiosUsuario()">Guardar</button>
        </div>
    </div>
</div>

</body>
</html>