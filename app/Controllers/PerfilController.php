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

final class PerfilController
{
    public function index(): void
    {
        View::render('perfil/index', [
            'title' => 'Perfil',
        ]);
    }

    public function atualizar(): void
    {
        $usuarioId = Auth::id();
        if ($usuarioId === null) {
            Redirect::to('/login');
        }

        $nome = Request::input('nome');
        $email = Request::input('email');
        $telefone = Request::input('telefone');
        $whatsapp = Request::input('whatsapp');

        $validator = (new Validator($_POST))
            ->required('nome', 'o nome')
            ->minLength('nome', 2, 'O nome')
            ->maxLength('nome', 120, 'O nome')
            ->required('email', 'o e-mail')
            ->email('email')
            ->maxLength('email', 190, 'O e-mail')
            ->maxLength('telefone', 20, 'O telefone')
            ->pattern('telefone', '/^[0-9+\-\s()]{8,20}$/', 'Informe um telefone válido.')
            ->maxLength('whatsapp', 20, 'O WhatsApp')
            ->pattern('whatsapp', '/^[0-9+\-\s()]{8,20}$/', 'Informe um WhatsApp válido.');

        if ($validator->fails()) {
            Session::flashInput([
                'nome' => $nome,
                'email' => $email,
                'telefone' => $telefone,
                'whatsapp' => $whatsapp,
            ]);
            Session::flashErrors($validator->errors());
            Redirect::to('/perfil');
        }

        if (Usuario::emailEmUso($email, $usuarioId)) {
            Session::flashInput([
                'nome' => $nome,
                'email' => $email,
                'telefone' => $telefone,
                'whatsapp' => $whatsapp,
            ]);
            Session::flashErrors(['email' => 'Este e-mail já está em uso.']);
            Redirect::to('/perfil');
        }

        Usuario::atualizarPerfil(
            $usuarioId,
            $nome,
            $email,
            $telefone === '' ? null : $telefone,
            $whatsapp === '' ? null : $whatsapp
        );
        Auth::refresh();
        Session::flash('success', 'Perfil atualizado.');
        Redirect::to('/perfil');
    }

    public function senha(): void
    {
        $usuarioId = Auth::id();
        if ($usuarioId === null) {
            Redirect::to('/login');
        }

        $validator = (new Validator($_POST))
            ->required('senha_atual', 'a senha atual')
            ->required('senha', 'a nova senha')
            ->minLength('senha', 8, 'A nova senha', false)
            ->maxBytes('senha', 72, 'A nova senha')
            ->confirmed('senha', 'senha_confirmacao', 'A confirmação da nova senha');

        if ($validator->fails()) {
            Session::flashErrors($validator->errors());
            Redirect::to('/perfil');
        }

        $novaSenha = Request::raw('senha');
        if (!Usuario::senhaConfere($usuarioId, Request::raw('senha_atual'))) {
            Session::flashErrors(['senha_atual' => 'A senha atual está incorreta.']);
            Redirect::to('/perfil');
        }

        Usuario::atualizarSenha($usuarioId, password_hash($novaSenha, PASSWORD_DEFAULT));
        Session::flash('success', 'Senha alterada.');
        Redirect::to('/perfil');
    }
}
