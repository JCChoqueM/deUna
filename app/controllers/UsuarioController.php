<?php
/**
 * DeUna - Usuario Controller
 * Gestión de usuarios (admin only - RF-01)
 */

class UsuarioController extends Controller
{
    /**
     * List all users
     */
    public function index()
    {
        $model = $this->model('Usuario');
        $usuarios = $model->all('fecha_creacion DESC');

        $this->view('usuarios/index', [
            'usuarios' => $usuarios,
        ]);
    }

    /**
     * Create new user (POST)
     */
    public function crear()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('usuarios');
        }

        $model = $this->model('Usuario');
        $errors = [];

        $nombre = trim($_POST['nombre'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol = $_POST['rol'] ?? 'operador';

        if (empty($nombre)) $errors[] = 'El nombre es obligatorio';
        if (empty($usuario)) $errors[] = 'El usuario es obligatorio';
        if (strlen($password) < 4) $errors[] = 'La contraseña debe tener al menos 4 caracteres';

        if (empty($errors) && $model->getByUsuario($usuario)) {
            $errors[] = 'El nombre de usuario ya existe';
        }

        if (!empty($errors)) {
            Session::flash('error', implode(', ', $errors));
        } else {
            $model->create([
                'nombre' => $nombre,
                'usuario' => $usuario,
                'password_hash' => $password,
                'rol' => $rol,
                'estado' => 'activo',
                'fecha_creacion' => date('Y-m-d H:i:s'),
            ]);
            Session::flash('success', 'Usuario creado correctamente');
        }

        $this->redirect('usuarios');
    }

    /**
     * Edit user
     */
    public function editar($id = null)
    {
        $model = $this->model('Usuario');
        $usuario = $model->find($id);

        if (!$usuario) {
            Session::flash('error', 'Usuario no encontrado');
            $this->redirect('usuarios');
        }

        $this->view('usuarios/editar', [
            'usuario' => $usuario,
        ]);
    }

    /**
     * Update user (POST)
     */
    public function actualizar($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('usuarios/editar/' . $id);
        }

        $model = $this->model('Usuario');
        $usuario = $model->find($id);
        if (!$usuario) {
            Session::flash('error', 'Usuario no encontrado');
            $this->redirect('usuarios');
        }

        $nombre = trim($_POST['nombre'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol = $_POST['rol'] ?? 'operador';
        $estado = $_POST['estado'] ?? 'activo';

        $data = [
            'nombre' => $nombre,
            'rol' => $rol,
            'estado' => $estado,
        ];

        if (!empty($password) && strlen($password) >= 4) {
            $data['password_hash'] = $password;
        }

        $model->update($id, $data);
        Session::flash('success', 'Usuario actualizado correctamente');
        $this->redirect('usuarios');
    }

    /**
     * Activate/deactivate user
     */
    public function cambiarEstado($id = null)
    {
        $model = $this->model('Usuario');
        $usuario = $model->find($id);
        if (!$usuario) {
            Session::flash('error', 'Usuario no encontrado');
            $this->redirect('usuarios');
        }

        $nuevoEstado = $usuario['estado'] === 'activo' ? 'inactivo' : 'activo';
        $model->update($id, ['estado' => $nuevoEstado]);

        Session::flash('success', 'Estado del usuario actualizado');
        $this->redirect('usuarios');
    }

    /**
     * Delete user (deactivate)
     */
    public function eliminar($id = null)
    {
        $model = $this->model('Usuario');
        $model->update($id, ['estado' => 'inactivo']);
        Session::flash('success', 'Usuario desactivado correctamente');
        $this->redirect('usuarios');
    }
}
