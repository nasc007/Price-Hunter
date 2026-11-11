<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\Usuario;
use PDOException;

final class AuthController
{
    public function formularioLogin(): void
    {
        View::render('auth/login', [
            'title' => 'Entrar',
        ], 'layouts/guest');
    }

    public function entrar(): void
    {
        $email = Request::input('email');
        $senha = Request::raw('senha');
        $validator = (new Validator($_POST))
            ->required('email', 'o e-mail')
            ->email('email')
            ->maxLength('email', 190, 'O e-mail')
            ->required('senha', 'a senha');

        if ($validator->fails()) {
            Session::flashInput(['email' => $email]);
            Session::flashErrors($validator->errors());
            Redirect::to('/login');
        }

        $erro = Auth::attempt($email, $senha);
        if ($erro !== null) {
            Session::flashInput(['email' => $email]);
            Session::flash('error', $erro);
            Redirect::to('/login');
        }

        Redirect::to('/');
    }

    public function formularioCadastro(): void
    {
        View::render('auth/cadastro', [
            'title' => 'Criar conta',
        ], 'layouts/guest');
    }

    public function cadastrar(): void
    {
        $nome = Request::input('nome');
        $email = Request::input('email');
        $validator = (new Validator($_POST))
            ->required('nome', 'o nome')
            ->minLength('nome', 2, 'O nome')
            ->maxLength('nome', 120, 'O nome')
            ->required('email', 'o e-mail')
            ->email('email')
            ->maxLength('email', 190, 'O e-mail')
            ->required('senha', 'a senha')
            ->minLength('senha', 8, 'A senha', false)
            ->maxLength('senha', 72, 'A senha', false)
            ->confirmed('senha', 'senha_confirmacao', 'A confirmação da senha');

        if ($validator->fails()) {
            Session::flashInput(['nome' => $nome, 'email' => $email]);
            Session::flashErrors($validator->errors());
            Redirect::to('/cadastro');
        }

        if (Usuario::emailEmUso($email)) {
            Session::flashInput(['nome' => $nome, 'email' => $email]);
            Session::flashErrors(['email' => 'Já existe uma conta com este e-mail.']);
            Redirect::to('/cadastro');
        }

        try {
            $id = Usuario::criar($nome, $email, Request::raw('senha'));
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                Session::flashInput(['nome' => $nome, 'email' => $email]);
                Session::flashErrors(['email' => 'Já existe uma conta com este e-mail.']);
                Redirect::to('/cadastro');
            }

            throw $exception;
        }

        $user = Usuario::findById($id);
        if ($user === null) {
            Session::flash('error', 'A conta foi criada, mas não foi possível iniciar a sessão.');
            Redirect::to('/login');
        }

        Auth::login($user);
        Session::flash('success', 'Conta criada. Seu painel já está pronto.');
        Redirect::to('/');
    }

    public function sair(): void
    {
        Auth::logout();
        Session::flash('success', 'Sessão encerrada.');
        Redirect::to('/login');
    }
}
