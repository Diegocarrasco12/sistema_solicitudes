<?php
session_start();
session_unset();       // Elimina todas las variables de sesión
session_destroy();     // Destruye la sesión anterior (por si estaba corrupta)
session_start();       // Comienza una sesión nueva limpia

// Mostrar alerta si la sesión expiró
if (isset($_GET['expirado']) && $_GET['expirado'] == '1') {
    echo "<script>alert('Tu sesión ha expirado por inactividad. Por favor, inicia sesión nuevamente.');</script>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Iniciar sesión</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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
      background-color: rgba(255, 255, 255, 0.75);
      border-radius: 10px;
      padding: 2rem;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
    }

    .form-label {
      font-weight: bold;
    }

    .input-group-text {
      background-color: white;
      border-left: none;
    }

    .form-control:focus {
      box-shadow: none;
    }

    /* Loader */
    #loader {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: white;
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
    }
  </style>
</head>


<body class="d-flex justify-content-center align-items-center">

  <!-- Loader GIF -->
  <div id="loader">
    <img src="assets/img/loader.gif" alt="Cargando..." style="max-height: 100px;">
  </div>

  <div class="bg-blur"></div>

  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-5">
        <div class="login-container">
          <div class="text-center mb-4">
            <img src="assets/img/banner.png" alt="Logo" class="img-fluid mb-3" style="max-height: 80px;">
            <h4 class="mb-0"></h4>
          </div>

          <form action="login.php" method="POST">
            <div class="mb-3">
              <label for="username" class="form-label">RUT</label>
              <input type="text" class="form-control" id="username" name="username" required>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">Contraseña</label>
              <div class="input-group">
                <input type="password" class="form-control" id="password" name="password" required>
                <span class="input-group-text">
                  <i class="bi bi-eye-slash" id="togglePassword" style="cursor: pointer;"></i>
                </span>
              </div>
            </div>

            <div class="mb-3">
              <label for="area" class="form-label">Seleccionar Área</label>
              <select class="form-select" id="area" name="area" required>
                <option value="" selected disabled>-- Seleccione un área --</option>
                <option value="Soporte Informático">Soporte Informático</option>
                <option value="Servicios Generales">Servicios Generales</option>
              </select>
            </div>

            <button type="submit" class="btn btn-primary w-100">Ingresar</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Mostrar/Ocultar contraseña -->
  <script>
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('togglePassword');

    toggleIcon.addEventListener('click', function () {
      const isPassword = passwordInput.type === 'password';
      passwordInput.type = isPassword ? 'text' : 'password';
      toggleIcon.classList.toggle('bi-eye');
      toggleIcon.classList.toggle('bi-eye-slash');
    });
  </script>

  <!-- Ocultar loader cuando cargue -->
  <script>
    window.addEventListener('load', function () {
      const loader = document.getElementById('loader');
      if (loader) loader.style.display = 'none';
    });
  </script>

  <!-- Limpiar RUT en tiempo real -->
 <script>
  document.getElementById('username').addEventListener('input', function () {
    this.value = this.value.toUpperCase().replace(/[^0-9K]/g, '');
  });
</script>

</body>
</html>
