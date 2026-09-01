<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Csrf;
use app\Core\Request;
use app\Core\Response;
use app\Core\Session;
use app\Core\Validator;
use app\Services\AuthService;
use app\Services\AuditService;
use app\Services\PasswordResetService;
use app\Services\SecurityService;

/**
 * Authentication controller — login/logout and self-service password reset.
 */
final class AuthController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function showLogin(): string
    {
        if (AuthService::check()) {
            return redirect($this->request->url('/'));
        }
        return $this->view('auth/login', [
            'title' => 'Sign in',
        ])->render();
    }

    public function login(): mixed
    {
        $email = (string) $this->request->input('email', '');
        $password = (string) $this->request->input('password', '');
        $ip = $this->request->ip();

        if ($email === '' || $password === '') {
            flash('error', 'Please enter your email and password.');
            return $this->response->back();
        }

        if (SecurityService::isLocked($email, $ip)) {
            $minutes = (int) config('security.login_lock_minutes', 15);
            AuditService::log('login_locked', 'auth', null, null, "Locked sign-in attempt for: {$email}");
            flash('error', 'Too many failed attempts. Please try again in ' . $minutes . ' minutes.');
            return $this->response->back();
        }

        if (AuthService::attempt($email, $password, $ip)) {
            AuditService::log('login', 'auth', null, null, "User signed in: {$email}");
            flash('success', 'Welcome back!');
            return $this->response->redirect($this->request->url('/public/'));
        }

        AuditService::log('failed_login', 'auth', null, null, "Failed sign-in attempt for: {$email}");
        flash('error', 'Invalid email or password.');
        return $this->response->back();
    }

    public function logout(): mixed
    {
        AuditService::log('logout', 'auth', null, null, 'User signed out');
        AuthService::logout();
        flash('success', 'You have been signed out.');
        return $this->response->redirect($this->request->url('/login'));
    }

    /* ---------------- Password reset ---------------- */

    public function showForgot(): string
    {
        return $this->view('auth/forgot', [
            'title' => 'Forgot password',
        ])->render();
    }

    public function sendReset(): mixed
    {
        $email = (string) $this->request->input('email', '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid email address.');
            return $this->response->back();
        }

        $token = PasswordResetService::request($email);

        // Try to email the reset link (shared-hosting mail()); on failure the
        // generic message is still shown — anti-enumeration.
        $sent = @mail(
            $email,
            'TradeERP — Password reset',
            "Use this link to reset your password (valid 30 minutes):\n\n" . PasswordResetService::resetUrl($email, $token)
                . "\n\nIf you did not request this, ignore this email.",
            'From: no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n"
        );

        AuditService::log('password_reset_requested', 'users', 'user', null, "Reset requested for: {$email}");

        // Anti-enumeration: identical response whether or not the email exists.
        $message = 'If that email exists, a password reset link has been sent.';

        // Local/testing environments only: surface the link so the flow can be
        // exercised without a mail server. NEVER shown in production.
        // (mail() may return true even when no MTA exists, so local env wins.)
        if (config('app.env') === 'local') {
            $message .= ' (local: ' . PasswordResetService::resetUrl($email, $token) . ')';
        }

        flash('success', $message);
        return $this->response->redirect($this->request->url('/login'));
    }

    public function showResetForm(): mixed
    {
        $email = (string) $this->request->input('email', '');
        $token = (string) $this->request->input('token', '');
        if ($email === '' || $token === '') {
            flash('error', 'This reset link is incomplete.');
            return $this->response->redirect($this->request->url('/forgot-password'));
        }
        return $this->view('auth/reset', [
            'title' => 'Reset password',
            'email' => $email,
            'token' => $token,
        ])->render();
    }

    public function doReset(): mixed
    {
        $email = (string) $this->request->input('email', '');
        $token = (string) $this->request->input('token', '');
        $password = (string) $this->request->input('password', '');
        $confirm = (string) $this->request->input('password_confirmation', '');

        if ($password !== $confirm) {
            flash('error', 'The passwords do not match.');
            return $this->response->back();
        }

        try {
            PasswordResetService::reset($email, $token, $password);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        flash('success', 'Your password has been reset. Please sign in.');
        return $this->response->redirect($this->request->url('/login'));
    }
}
