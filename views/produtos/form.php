<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Session;
use App\Core\Url;

/** @var list<array<string, mixed>> $produtos */
/** @var int|null $edicao */
/** @var array<string, string> $errors */

$isBatch = $edicao === null;
$title = $isBatch ? 'Cadastrar produtos' : 'Editar produto';
$action = $isBatch ? Url::to('/produtos') : Url::to('/produtos/' . $edicao);
$frequencias = ['1' => 'A cada hora', '6' => 'A cada 6 horas', '12' => 'A cada 12 horas', '24' => 'Diariamente'];
$labels = [
    'nome' => 'Nome do produto',
    'marca' => 'Marca',
    'modelo' => 'Modelo',
    'categoria' => 'Categoria',
    'descricao' => 'Descrição',
    'sku' => 'Código de referência ou SKU',
    'ean' => 'EAN/GTIN',
    'imagem' => 'URL da imagem (HTTPS)',
    'url_referencia' => 'Link original do produto',
    'palavras_chave' => 'Palavras-chave de pesquisa',
    'preco_desejado' => 'Preço desejado (R$)',
    'valor_maximo' => 'Valor máximo aceitável (R$)',
];
$inputTypes = [
    'nome' => 'text',
    'marca' => 'text',
    'modelo' => 'text',
    'categoria' => 'text',
    'sku' => 'text',
    'ean' => 'text',
    'imagem' => 'url',
    'url_referencia' => 'url',
    'palavras_chave' => 'text',
    'preco_desejado' => 'number',
    'valor_maximo' => 'number',
];
?>
<section class="page-head">
    <div>
        <p class="eyebrow"><?= $isBatch ? 'Novo monitoramento' : 'Catálogo pessoal' ?></p>
        <h1><?= e($title) ?></h1>
        <p class="lead">
            <?= $isBatch
                ? 'Preencha um ou mais produtos. Eles serão salvos no seu catálogo, sem disparar pesquisas.'
                : 'Atualize os dados e as preferências de acompanhamento do produto.' ?>
        </p>
    </div>
    <a class="btn btn-line" href="<?= e(Url::to('/produtos')) ?>">Voltar aos produtos</a>
</section>

<form method="post" action="<?= e($action) ?>" id="productForm">
    <?= Csrf::field() ?>
    <div id="productRows" data-next-index="<?= count($produtos) ?>">
        <?php foreach ($produtos as $index => $produto): ?>
            <?php
            $prefix = $isBatch ? 'produtos_' . $index : 'produto';
            $fieldName = static fn (string $field): string => $isBatch
                ? 'produtos[' . $index . '][' . $field . ']'
                : 'produto[' . $field . ']';
            $fieldId = static fn (string $field): string => $field . '-' . $index;
            $fieldValue = static function (string $field) use ($prefix, $produto): string {
                $default = (string) ($produto[$field] ?? '');
                return Session::old($prefix . '_' . $field, $default);
            };
            ?>
            <fieldset class="panel product-form-row" data-product-row data-index="<?= (int) $index ?>">
                <div class="product-row-heading">
                    <legend><?= $isBatch ? 'Produto ' . ((int) $index + 1) : 'Dados do produto' ?></legend>
                    <button class="text-button text-danger" type="button" data-remove-product<?= count($produtos) === 1 ? ' disabled' : '' ?>>Remover</button>
                </div>
                <div class="product-form-grid">
                    <?php foreach (['nome', 'marca', 'modelo', 'categoria'] as $field): ?>
                        <?php $errorKey = $prefix . '_' . $field; ?>
                        <div>
                            <label class="form-label" for="<?= e($fieldId($field)) ?>"><?= e($labels[$field]) ?><?= $field === 'nome' ? ' *' : '' ?></label>
                            <input class="form-control<?= invalid_class($errors, $errorKey) ?>" type="text" id="<?= e($fieldId($field)) ?>" name="<?= e($fieldName($field)) ?>" value="<?= e($fieldValue($field)) ?>" maxlength="<?= $field === 'nome' ? '180' : '120' ?>"<?= $field === 'nome' ? ' required' : '' ?> data-product-field="<?= e($field) ?>">
                            <?= field_error($errors, $errorKey) ?>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach (['sku', 'ean'] as $field): ?>
                        <?php $errorKey = $prefix . '_' . $field; ?>
                        <div>
                            <label class="form-label" for="<?= e($fieldId($field)) ?>"><?= e($labels[$field]) ?></label>
                            <input class="form-control<?= invalid_class($errors, $errorKey) ?>" type="text" id="<?= e($fieldId($field)) ?>" name="<?= e($fieldName($field)) ?>" value="<?= e($fieldValue($field)) ?>" maxlength="<?= $field === 'sku' ? '80' : '20' ?>" aria-describedby="<?= e($fieldId($field)) ?>-help" data-product-field="<?= e($field) ?>">
                            <small class="form-text" id="<?= e($fieldId($field)) ?>-help"><?= $field === 'sku'
                                ? 'Código interno do fabricante, da loja ou seu para identificar o produto; pode variar entre lojas.'
                                : 'Número do código de barras (8, 12, 13 ou 14 dígitos), incluindo dígito verificador válido, que ajuda a identificar o mesmo produto em várias lojas.' ?></small>
                            <?= field_error($errors, $errorKey) ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="product-form-wide">
                        <?php $errorKey = $prefix . '_descricao'; ?>
                        <label class="form-label" for="<?= e($fieldId('descricao')) ?>"><?= e($labels['descricao']) ?></label>
                        <textarea class="form-control<?= invalid_class($errors, $errorKey) ?>" id="<?= e($fieldId('descricao')) ?>" name="<?= e($fieldName('descricao')) ?>" rows="3" maxlength="5000" data-product-field="descricao"><?= e($fieldValue('descricao')) ?></textarea>
                        <?= field_error($errors, $errorKey) ?>
                    </div>

                    <?php foreach (['url_referencia', 'imagem', 'palavras_chave'] as $field): ?>
                        <?php $errorKey = $prefix . '_' . $field; ?>
                        <div>
                            <label class="form-label" for="<?= e($fieldId($field)) ?>"><?= e($labels[$field]) ?></label>
                            <input class="form-control<?= invalid_class($errors, $errorKey) ?>" type="<?= e($inputTypes[$field]) ?>" id="<?= e($fieldId($field)) ?>" name="<?= e($fieldName($field)) ?>" value="<?= e($fieldValue($field)) ?>" maxlength="<?= $field === 'imagem' ? '255' : ($field === 'url_referencia' ? '1000' : '500') ?>"<?= in_array($field, ['imagem', 'url_referencia'], true) ? ' placeholder="https://exemplo.com/..."' : '' ?> data-product-field="<?= e($field) ?>">
                            <?= field_error($errors, $errorKey) ?>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach (['preco_desejado', 'valor_maximo'] as $field): ?>
                        <?php $errorKey = $prefix . '_' . $field; ?>
                        <div>
                            <label class="form-label" for="<?= e($fieldId($field)) ?>"><?= e($labels[$field]) ?></label>
                            <input class="form-control<?= invalid_class($errors, $errorKey) ?>" type="number" id="<?= e($fieldId($field)) ?>" name="<?= e($fieldName($field)) ?>" value="<?= e($fieldValue($field)) ?>" min="0.01" max="9999999999.99" step="0.01" inputmode="decimal" data-product-field="<?= e($field) ?>">
                            <?= field_error($errors, $errorKey) ?>
                        </div>
                    <?php endforeach; ?>

                    <?php $statusError = $prefix . '_status'; ?>
                    <div>
                        <label class="form-label" for="<?= e($fieldId('status')) ?>">Status</label>
                        <select class="form-select<?= invalid_class($errors, $statusError) ?>" id="<?= e($fieldId('status')) ?>" name="<?= e($fieldName('status')) ?>" data-product-field="status">
                            <?php foreach (['ativo' => 'Ativo', 'pausado' => 'Pausado', 'arquivado' => 'Arquivado'] as $value => $label): ?>
                                <option value="<?= e($value) ?>"<?= $fieldValue('status') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error($errors, $statusError) ?>
                    </div>

                    <?php $frequencyError = $prefix . '_frequencia_horas'; ?>
                    <div>
                        <label class="form-label" for="<?= e($fieldId('frequencia_horas')) ?>">Frequência</label>
                        <select class="form-select<?= invalid_class($errors, $frequencyError) ?>" id="<?= e($fieldId('frequencia_horas')) ?>" name="<?= e($fieldName('frequencia_horas')) ?>" data-product-field="frequencia_horas">
                            <?php foreach ($frequencias as $value => $label): ?>
                                <option value="<?= e((string) $value) ?>"<?= $fieldValue('frequencia_horas') === (string) $value ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error($errors, $frequencyError) ?>
                    </div>
                </div>
            </fieldset>
        <?php endforeach; ?>
    </div>

    <?php if ($isBatch): ?>
        <div class="product-form-actions">
            <button class="btn btn-line" type="button" id="addProductRow">
                <i class="bi bi-plus-lg"></i> Adicionar outro produto
            </button>
            <span class="muted-cell">Até 10 produtos por envio.</span>
        </div>
    <?php endif; ?>

    <div class="product-submit">
        <button class="btn btn-gold" type="submit"><?= $isBatch ? 'Salvar produtos' : 'Salvar alterações' ?></button>
        <a class="btn btn-line" href="<?= e(Url::to('/produtos')) ?>">Cancelar</a>
    </div>
</form>
