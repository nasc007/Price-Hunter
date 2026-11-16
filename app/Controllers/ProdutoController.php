<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\Produto;

final class ProdutoController
{
    private const MAX_LOTE = 10;

    private const FREQUENCIAS = ['1', '6', '12', '24'];

    private const CAMPOS = [
        'nome',
        'marca',
        'modelo',
        'categoria',
        'descricao',
        'sku',
        'ean',
        'imagem',
        'url_referencia',
        'palavras_chave',
        'preco_desejado',
        'valor_maximo',
        'status',
        'frequencia_horas',
    ];

    public function index(): void
    {
        $status = Request::query('status');
        if (!in_array($status, ['', 'ativo', 'pausado', 'arquivado'], true)) {
            $status = '';
        }

        View::render('produtos/index', [
            'title' => 'Produtos',
            'produtos' => Produto::listarDoUsuario(
                $this->usuarioId(),
                $status,
                mb_substr(Request::query('q'), 0, 100)
            ),
            'filtros' => [
                'status' => $status,
                'q' => mb_substr(Request::query('q'), 0, 100),
            ],
        ]);
    }

    public function novo(): void
    {
        View::render('produtos/form', [
            'title' => 'Cadastrar produtos',
            'produtos' => [$this->produtoVazio()],
            'edicao' => null,
        ]);
    }

    public function criar(): void
    {
        $submitted = $_POST['produtos'] ?? null;
        if (!is_array($submitted) || $submitted === []) {
            Session::flash('error', 'Adicione ao menos um produto para continuar.');
            Redirect::to('/produtos/novo');
        }

        if (count($submitted) > self::MAX_LOTE) {
            Session::flash('error', 'É possível cadastrar até ' . self::MAX_LOTE . ' produtos por vez.');
            Redirect::to('/produtos/novo');
        }

        $produtos = [];
        $errors = [];
        $oldInput = [];

        foreach (array_values($submitted) as $index => $submittedProduct) {
            $data = $this->normalizar(is_array($submittedProduct) ? $submittedProduct : []);
            $produtos[] = $data;
            $prefix = 'produtos_' . $index;
            foreach ($data as $field => $value) {
                if ($field !== 'status' && $field !== 'frequencia_horas') {
                    $oldInput[$prefix . '_' . $field] = $value;
                } else {
                    $oldInput[$prefix . '_' . $field] = (string) $value;
                }
            }
            $errors += $this->validar($data, $prefix);
        }

        if ($errors !== []) {
            Session::flashInput($oldInput);
            Session::flashErrors($errors);
            Redirect::to('/produtos/novo');
        }

        Produto::criarVarios(
            $this->usuarioId(),
            array_map(fn (array $produto): array => $this->prepararPersistencia($produto), $produtos)
        );
        $quantidade = count($produtos);
        Session::flash(
            'success',
            $quantidade === 1
                ? 'Produto cadastrado. Nenhuma pesquisa foi executada.'
                : $quantidade . ' produtos cadastrados. Nenhuma pesquisa foi executada.'
        );
        Redirect::to('/produtos');
    }

    public function editar(string $id): void
    {
        $produtoId = $this->parseId($id);
        $produto = $produtoId === null
            ? null
            : Produto::encontrarDoUsuario($produtoId, $this->usuarioId());
        if ($produto === null) {
            $this->naoEncontrado();
        }

        View::render('produtos/form', [
            'title' => 'Editar produto',
            'produtos' => [$produto],
            'edicao' => $produtoId,
        ]);
    }

    public function atualizar(string $id): void
    {
        $produtoId = $this->parseId($id);
        $usuarioId = $this->usuarioId();
        $produto = $produtoId === null ? null : Produto::encontrarDoUsuario($produtoId, $usuarioId);
        if ($produto === null) {
            $this->naoEncontrado();
        }

        $data = $this->normalizar(is_array($_POST['produto'] ?? null) ? $_POST['produto'] : []);
        $errors = $this->validar($data, 'produto');
        if ($errors !== []) {
            $this->flashEdicao($data, $errors);
            Redirect::to('/produtos/' . $produtoId . '/editar');
        }

        if (!Produto::atualizarDoUsuario($produtoId, $usuarioId, $this->prepararPersistencia($data))) {
            $this->naoEncontrado();
        }

        Session::flash('success', 'Produto atualizado. Nenhuma pesquisa foi executada.');
        Redirect::to('/produtos');
    }

    public function alterarStatus(string $id): void
    {
        $produtoId = $this->parseId($id);
        $status = Request::input('status');
        if ($produtoId === null || !in_array($status, ['ativo', 'pausado', 'arquivado'], true)) {
            $this->naoEncontrado();
        }

        if (!Produto::alterarStatus($produtoId, $this->usuarioId(), $status)) {
            $this->naoEncontrado();
        }

        $mensagem = [
            'ativo' => 'Produto ativado. Nenhuma pesquisa foi executada.',
            'pausado' => 'Monitoramento pausado.',
            'arquivado' => 'Produto arquivado.',
        ][$status];
        Session::flash('success', $mensagem);
        Redirect::to('/produtos');
    }

    public function excluir(string $id): void
    {
        $produtoId = $this->parseId($id);
        if ($produtoId === null || !Produto::excluirDoUsuario($produtoId, $this->usuarioId())) {
            $this->naoEncontrado();
        }

        Session::flash('success', 'Produto excluído.');
        Redirect::to('/produtos');
    }

    /** @param array<string, mixed> $input
     *  @return array<string, string> */
    private function normalizar(array $input): array
    {
        $data = [];
        foreach (self::CAMPOS as $field) {
            $value = $input[$field] ?? '';
            $data[$field] = is_string($value) ? trim($value) : '';
        }
        $data['status'] = $data['status'] !== '' ? $data['status'] : 'ativo';
        $data['frequencia_horas'] = $data['frequencia_horas'] !== '' ? $data['frequencia_horas'] : '24';

        return $data;
    }

    /** @param array<string, string> $data
     *  @return array<string, string> */
    private function validar(array $data, string $prefix): array
    {
        $validator = (new Validator($data))
            ->required('nome', 'o nome do produto')
            ->maxLength('nome', 180, 'O nome')
            ->maxLength('marca', 120, 'A marca')
            ->maxLength('modelo', 120, 'O modelo')
            ->maxLength('categoria', 120, 'A categoria')
            ->maxLength('descricao', 5000, 'A descrição')
            ->maxLength('sku', 80, 'O SKU')
            ->maxLength('ean', 20, 'O EAN/GTIN')
            ->pattern('ean', '/^(?:\d{8}|\d{12,14})$/', 'Informe um EAN/GTIN com 8, 12, 13 ou 14 dígitos.')
            ->maxLength('imagem', 255, 'A URL da imagem')
            ->maxLength('url_referencia', 1000, 'O link do produto')
            ->maxLength('palavras_chave', 500, 'As palavras-chave')
            ->pattern(
                'preco_desejado',
                '/^\d{1,10}(?:\.\d{1,2})?$/',
                'Informe um preço positivo com até 2 casas decimais usando ponto.'
            )
            ->pattern(
                'valor_maximo',
                '/^\d{1,10}(?:\.\d{1,2})?$/',
                'Informe um valor máximo positivo com até 2 casas decimais usando ponto.'
            );
        $errors = $validator->errors();

        foreach (['preco_desejado', 'valor_maximo'] as $field) {
            if ($data[$field] !== '' && preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $data[$field]) === 1
                && $this->centavos($data[$field]) === 0) {
                $errors[$field] = 'O valor precisa ser maior que zero.';
            }
        }

        if (!in_array($data['status'], ['ativo', 'pausado', 'arquivado'], true)) {
            $errors['status'] = 'Selecione um status válido.';
        }
        if (!in_array($data['frequencia_horas'], self::FREQUENCIAS, true)) {
            $errors['frequencia_horas'] = 'Selecione uma frequência válida.';
        }

        if ($data['imagem'] !== '') {
            $image = filter_var($data['imagem'], FILTER_VALIDATE_URL);
            $parts = parse_url($data['imagem']);
            if (
                $image === false
                || !is_array($parts)
                || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                || empty($parts['host'])
                || isset($parts['user'])
                || isset($parts['pass'])
            ) {
                $errors['imagem'] = 'Informe uma URL HTTPS válida para a imagem.';
            }
        }
        if ($data['url_referencia'] !== '') {
            $reference = filter_var($data['url_referencia'], FILTER_VALIDATE_URL);
            $parts = parse_url($data['url_referencia']);
            if (
                $reference === false
                || !is_array($parts)
                || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                || empty($parts['host'])
                || isset($parts['user'])
                || isset($parts['pass'])
            ) {
                $errors['url_referencia'] = 'Informe um link HTTPS válido para o produto.';
            }
        }

        $target = $this->centavos($data['preco_desejado']);
        $maximum = $this->centavos($data['valor_maximo']);
        if ($target !== null && $maximum !== null && $maximum < $target) {
            $errors['valor_maximo'] = 'O valor máximo não pode ser menor que o preço desejado.';
        }

        $prefixed = [];
        foreach ($errors as $field => $message) {
            $prefixed[$prefix . '_' . $field] = $message;
        }

        return $prefixed;
    }

    /** @param array<string, string> $data
     *  @return array<string, string|null|int> */
    private function prepararPersistencia(array $data): array
    {
        foreach ([
            'marca',
            'modelo',
            'categoria',
            'descricao',
            'sku',
            'ean',
            'imagem',
            'url_referencia',
            'palavras_chave',
            'preco_desejado',
            'valor_maximo',
        ] as $field) {
            $data[$field] = $data[$field] === '' ? null : $data[$field];
        }
        $data['frequencia_horas'] = (int) $data['frequencia_horas'];

        return $data;
    }

    private function centavos(string $value): ?int
    {
        if ($value === '' || preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $value) !== 1) {
            return null;
        }

        $parts = explode('.', $value, 2);
        $whole = (int) $parts[0];
        $fraction = (int) str_pad($parts[1] ?? '', 2, '0');

        return $whole * 100 + $fraction;
    }

    /** @return array<string, string> */
    private function produtoVazio(): array
    {
        $produto = array_fill_keys(self::CAMPOS, '');
        $produto['status'] = 'ativo';
        $produto['frequencia_horas'] = '24';

        return $produto;
    }

    /** @param array<string, string> $data
     *  @param array<string, string> $errors */
    private function flashEdicao(array $data, array $errors): void
    {
        $oldInput = [];
        foreach ($data as $field => $value) {
            if (!in_array($field, ['status', 'frequencia_horas'], true)) {
                $oldInput['produto_' . $field] = $value;
            } else {
                $oldInput['produto_' . $field] = (string) $value;
            }
        }

        Session::flashInput($oldInput);
        Session::flashErrors($errors);
    }

    private function parseId(string $id): ?int
    {
        $parsed = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($parsed) ? $parsed : null;
    }

    private function usuarioId(): int
    {
        $id = Auth::id();
        if ($id === null) {
            Redirect::to('/login');
        }

        return $id;
    }

    private function naoEncontrado(): never
    {
        http_response_code(404);
        View::render('errors/404', [
            'title' => 'Produto não encontrado',
            'message' => 'O produto não existe ou não está disponível para esta conta.',
        ]);
        exit;
    }
}
