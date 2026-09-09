<?php
/**
 * DeUna - Auth Controller
 * Manejo de autenticación (login, logout, registro)
 */

class AuthController extends Controller
{
    /**
     * Show login form / handle login
     */
    public function login()
    {
        // If already logged in, redirect to dashboard
        if (Auth::isAuthenticated()) {
            $this->redirect('dashboard');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario = trim($_POST['usuario'] ?? '');
            $password = $_POST['password'] ?? '';
            $errors = [];

            if (empty($usuario) || empty($password)) {
                $errors[] = 'Usuario y contraseña son obligatorios';
            } elseif (!Auth::login($usuario, $password)) {
                $errors[] = 'Credenciales incorrectas. Verifique su usuario y contraseña.';
            }

            if (empty($errors)) {
                $this->redirect('dashboard');
            }

            $this->view('auth/login', ['errors' => $errors, 'usuario' => $usuario]);
            return;
        }

        $this->view('auth/login');
    }

    /**
     * Logout
     */
    public function logout()
    {
        Auth::logout();
        Session::flash('success', 'Sesión cerrada correctamente');
        $this->redirect('login');
    }

    /**
     * Show registration form (admin only)
     */
    public function register()
    {
        if (!Auth::isAdmin()) {
            $this->redirect('dashboard');
        }

        $model = $this->model('Usuario');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre = trim($_POST['nombre'] ?? '');
            $usuario = trim($_POST['usuario'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol = $_POST['rol'] ?? 'operador';
            $errors = [];

            if (empty($nombre)) $errors[] = 'El nombre es obligatorio';
            if (empty($usuario)) $errors[] = 'El usuario es obligatorio';
            if (empty($password) || strlen($password) < 4) $errors[] = 'La contraseña debe tener al menos 4 caracteres';

            if (empty($errors)) {
                // Check if user exists
                if ($model->getByUsuario($usuario)) {
                    $errors[] = 'El nombre de usuario ya existe';
                } else {
                    $model->create([
                        'nombre' => $nombre,
                        'usuario' => $usuario,
                        'password_hash' => $password,
                        'rol' => $rol,
                        'estado' => 'activo',
                        'fecha_creacion' => date('Y-m-d H:i:s'),
                    ]);
                    Session::flash('success', 'Usuario registrado correctamente');
                    $this->redirect('login');
                    return;
                }
            }

            $this->view('auth/register', ['errors' => $errors, 'data' => $_POST]);
            return;
        }

        $this->view('auth/register');
    }
}
