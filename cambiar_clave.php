<?php
session_start();
include("conexion.php");

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nueva = trim($_POST['nueva']);
    $confirmar = trim($_POST['confirmar']);
    $rut = $_SESSION['user'];

    if ($nueva !== $confirmar) {
        $mensaje = "❌ Las contraseñas no coinciden.";
    } elseif (strlen($nueva) < 6 || !preg_match('/[A-Z]/', $nueva)) {
        $mensaje = "❌ La contraseña debe tener al menos 6 caracteres y una letra mayúscula.";
    } else {
        $sql = "UPDATE usuarios SET clave = ?, cambio_clave = 1 WHERE rut = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ss", $nueva, $rut);

        if ($stmt->execute()) {
            $mensaje = "<span style='color: green;'>✅ Clave actualizada correctamente</span>";
            session_destroy();
            echo "<script>setTimeout(() => { window.location.href = 'index.php'; }, 2000);</script>";
        } else {
            $mensaje = "❌ Error al actualizar la clave.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cambiar Clave</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body, html {
      height: 100%;
      margin: 0;
    }

    .bg-blur {
      background-image: url('assets/img/tkt.jpg');
      background-size: cover;
      background-position: center;
      position: fixed;
      width: 100%;
      height: 100%;
      z-index: -1;
      filter: blur(8px);
    }

    .login-container {
      backdrop-filter: blur(5px);
      background-color: rgba(255, 255, 255, 0.85);
      border-radius: 10px;
      padding: 2rem;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
    }

    .form-label {
      font-weight: bold;
    }

    #coincidencia {
      font-size: 14px;
      margin-bottom: 8px;
    }

    .input-group-text {
      background-color: white;
      border-left: none;
    }
  </style>
</head>
<body class="d-flex justify-content-center align-items-center">

  <div class="bg-blur"></div>

  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-5">
        <div class="login-container">
          <div class="text-center mb-4">
            <h4 class="mb-0">Cambio de contraseña requerido</h4>
          </div>

          <?php if ($mensaje): ?>
            <div class="alert alert-info text-center"><?= $mensaje ?></div>
          <?php endif; ?>

          <form method="POST">
            <div class="mb-3">
              <label for="nueva" class="form-label">Nueva clave</label>
              <div class="input-group">
                <input type="password" class="form-control" id="nueva" name="nueva" required>
                <span class="input-group-text">
                  <i class="bi bi-eye-slash" id="toggleNueva" style="cursor:pointer;"></i>
                </span>
              </div>
            </div>

            <div class="mb-3">
              <label for="confirmar" class="form-label">Confirmar clave</label>
              <div class="input-group">
                <input type="password" class="form-control" id="confirmar" name="confirmar" required>
                <span class="input-group-text">
                  <i class="bi bi-eye-slash" id="toggleConfirmar" style="cursor:pointer;"></i>
                </span>
              </div>
            </div>

            <div id="coincidencia" class="text-start ps-1"></div>
            <div class="form-text mb-3">Debe contener al menos 6 caracteres y una letra mayúscula.</div>

            <button type="submit" class="btn btn-primary w-100">Actualizar clave</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Mostrar/Ocultar Contraseña -->
  <script>
    function togglePassword(inputId, iconId) {
      const input = document.getElementById(inputId);
      const icon = document.getElementById(iconId);
      const isPassword = input.type === "password";

      input.type = isPassword ? "text" : "password";
      icon.classList.toggle("bi-eye");
      icon.classList.toggle("bi-eye-slash");
    }

    document.getElementById('toggleNueva').addEventListener('click', () => togglePassword('nueva', 'toggleNueva'));
    document.getElementById('toggleConfirmar').addEventListener('click', () => togglePassword('confirmar', 'toggleConfirmar'));

    // Validación de coincidencia
    const nueva = document.getElementById('nueva');
    const confirmar = document.getElementById('confirmar');
    const coincidencia = document.getElementById('coincidencia');

    function validarCoincidencia() {
      if (nueva.value && confirmar.value) {
        if (nueva.value === confirmar.value) {
          coincidencia.textContent = "✅ Las contraseñas coinciden";
          coincidencia.style.color = "green";
        } else {
          coincidencia.textContent = "❌ Las contraseñas no coinciden";
          coincidencia.style.color = "red";
        }
      } else {
        coincidencia.textContent = "";
      }
    }

    nueva.addEventListener('input', validarCoincidencia);
    confirmar.addEventListener('input', validarCoincidencia);
  </script>

</body>
</html>
