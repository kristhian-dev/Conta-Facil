<?php

declare(strict_types=1);

namespace App\Controllers;

defined('APP_ROOT') || exit;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Core\Validator;

/**
 * Login e logout.
 *
 * Regras de acesso (usuário inativo, perfil não permitido, senha) ficam em
 * App\Core\Auth. Aqui ficam apenas a validação do formulário e o fluxo de telas.
 */
final class AuthController extends Controller
{
    /** Mensagem única para qualquer falha de credencial (não revela o motivo). */
    private const ERRO_CREDENCIAIS = 'E-mail ou senha inválidos. Verifique os dados e tente novamente.';

    /**
     * GET  auth/login -> exibe o formulário
     * POST auth/login -> valida e autentica
     */
    public function login(): void
    {
        // Quem já está logado não precisa ver o login novamente.
        if (Auth::verificar()) {
            $this->redirecionar(config('app.rota_inicial'));
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->autenticar();
            return;
        }

        $this->formulario();
    }

    /**
     * POST auth/logout (CSRF validado pelo Router).
     */
    public function logout(): void
    {
        Auth::logout();
        Session::flash('sucesso', 'Você saiu do sistema.');

        $this->redirecionar(config('auth.rota_login'));
    }

    private function autenticar(): void
    {
        $validador = new Validator($_POST);
        $valido = $validador->validar([
            'email' => ['obrigatorio', 'email', 'max:160'],
            'senha' => ['obrigatorio', 'max:255'],
        ]);
        $email = $validador->texto('email');

        if (!$valido) {
            http_response_code(422);
            $this->formulario($email, $validador->erros());
            return;
        }

        // A senha é usada exatamente como digitada (sem trim).
        $senha = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';

        if (!Auth::tentarLogin($email, $senha)) {
            http_response_code(422);
            $this->formulario($email, [], self::ERRO_CREDENCIAIS);
            return;
        }

        Session::flash('sucesso', 'Olá, ' . Auth::usuario()['nome'] . '!');
        $this->redirecionar(config('app.rota_inicial'));
    }

    /**
     * @param array<string, string> $erros
     */
    private function formulario(string $email = '', array $erros = [], ?string $erroGeral = null): void
    {
        $this->view('auth/login', [
            'titulo'    => 'Entrar',
            'email'     => $email,
            'erros'     => $erros,
            'erroGeral' => $erroGeral,
        ], 'layouts/auth');
    }
}
