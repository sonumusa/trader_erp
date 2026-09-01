<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Database;
use app\Core\Request;
use app\Core\Response;
use app\Core\Validator;
use app\Repositories\UserRepository;
use app\Services\AuditService;
use app\Services\AuthService;
use app\Services\PermissionService;

/**
 * User management — list, create, edit, activate/deactivate, reset password.
 * All actions are permission-checked server-side.
 */
final class UserController extends Controller
{
    private UserRepository $users;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->users = new UserRepository();
    }

    public function index(): string
    {
        $this->requirePermission('users', 'user', 'view');

        $data = $this->users->paginate(
            $this->request->page(),
            15,
            (string) $this->request->query('q', ''),
            (string) $this->request->query('sort', 'id'),
            (string) $this->request->query('dir', 'DESC')
        );

        return $this->view('users/index', [
            'title'    => 'Users',
            'data'     => $data,
            'search'   => (string) $this->request->query('q', ''),
            'sort'     => (string) $this->request->query('sort', 'id'),
            'dir'      => (string) $this->request->query('dir', 'DESC'),
            'canCreate' => PermissionService::can('users', 'user', 'create'),
            'canEdit'   => PermissionService::can('users', 'user', 'edit'),
        ])->render();
    }

    public function createForm(): string
    {
        $this->requirePermission('users', 'user', 'create');
        \app\Core\Session::forget('_old_input'); // start each form clean
        return $this->view('users/form', $this->formData(null))->render();
    }

    public function store(): mixed
    {
        $this->requirePermission('users', 'user', 'create');

        $v = new Validator();
        $valid = $v->validate($this->request->all(), [
            'name'     => 'required|max:120',
            'email'    => 'required|email|max:190|unique:users,email',
            'role_id'  => 'required|integer',
            'password' => 'required|min:' . (int) config('security.password_min_length', 8) . '|strong_password',
            'status'   => 'required|in:active,inactive',
        ]);

        if (!$valid) {
            flash('error', $v->firstError() ?? 'Please check the form and try again.');
            session_set('_old_input', $this->request->all());
            return $this->response->back();
        }

        $d = $v->data();
        $id = $this->users->create([
            'role_id'       => (int) $d['role_id'],
            'company_id'    => !empty($d['company_id']) ? (int) $d['company_id'] : null,
            'name'          => $d['name'],
            'email'         => $d['email'],
            'password_hash' => password_hash($d['password'], PASSWORD_DEFAULT),
            'status'        => $d['status'],
        ]);

        AuditService::log('create', 'users', 'user', $id, "Created user {$d['name']} ({$d['email']})");
        flash('success', 'User created successfully.');
        return $this->response->redirect($this->request->url('/users'));
    }

    public function editForm(int|string $id): string
    {
        $this->requirePermission('users', 'user', 'edit');
        $id = (int) $id;
        $user = Database::row('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$user) {
            flash('error', 'User not found.');
            return $this->redirectToUsers();
        }
        \app\Core\Session::forget('_old_input');
        return $this->view('users/form', $this->formData($user))->render();
    }

    public function update(int|string $id): mixed
    {
        $this->requirePermission('users', 'user', 'edit');
        $id = (int) $id;

        $email = (string) $this->request->input('email');
        $dup = Database::row('SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL', [$email, $id]);
        if ($dup) {
            flash('error', 'That email address is already in use.');
            return $this->response->back();
        }

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name'    => 'required|max:120',
            'email'   => 'required|email|max:190',
            'role_id' => 'required|integer',
            'status'  => 'required|in:active,inactive',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form and try again.');
            session_set('_old_input', $this->request->all());
            return $this->response->back();
        }

        $d = $v->data();
        Database::execute(
            'UPDATE users SET role_id = ?, company_id = ?, name = ?, email = ?, status = ?, updated_at = ? WHERE id = ?',
            [(int) $d['role_id'], !empty($d['company_id']) ? (int) $d['company_id'] : null, $d['name'], $d['email'], $d['status'], date('Y-m-d H:i:s'), $id]
        );

        $password = (string) $this->request->input('password', '');
        if ($password !== '') {
            $min = (int) config('security.password_min_length', 8);
            if (mb_strlen($password) < $min) {
                flash('error', "Password must be at least {$min} characters.");
                return $this->response->back();
            }
            Database::execute('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), date('Y-m-d H:i:s'), $id]);
            AuditService::log('password_reset', 'users', 'user', $id, 'Password reset by administrator');
        }

        AuditService::log('update', 'users', 'user', $id, "Updated user {$d['name']}");
        flash('success', 'User updated successfully.');
        return $this->response->redirect($this->request->url('/users'));
    }

    public function destroy(int|string $id): mixed
    {
        $this->requirePermission('users', 'user', 'delete');
        $id = (int) $id;

        if ((int) AuthService::id() === $id) {
            flash('error', 'You cannot delete your own account.');
            return $this->response->back();
        }
        $user = Database::row('SELECT name FROM users WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$user) {
            flash('error', 'User not found.');
            return $this->response->back();
        }
        Database::execute(
            'UPDATE users SET deleted_at = ?, status = \'inactive\', updated_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $id]
        );
        AuditService::log('delete', 'users', 'user', $id, "Deleted user {$user['name']}");
        flash('success', 'User deleted.');
        return $this->response->redirect($this->request->url('/users'));
    }

    private function formData(?array $user): array
    {
        return [
            'title'     => $user ? 'Edit User' : 'New User',
            'user'      => $user,
            'roles'     => Database::query('SELECT id, name FROM roles WHERE deleted_at IS NULL ORDER BY id'),
            'companies' => Database::query('SELECT id, name FROM companies WHERE deleted_at IS NULL ORDER BY id'),
            'editing'   => $user !== null,
        ];
    }

    private function redirectToUsers(): string
    {
        return (string) $this->response->redirect($this->request->url('/users'))->body();
    }

}
