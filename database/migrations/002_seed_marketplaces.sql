INSERT INTO marketplaces (codigo, nome, tipo, site_url, ativo, observacao) VALUES
(
    'mercadolivre',
    'Mercado Livre',
    'api',
    'https://www.mercadolivre.com.br',
    1,
    'Fonte prevista para a API pública de busca. Nenhuma consulta é feita até o driver da Fase 3.'
),
(
    'amazon',
    'Amazon Brasil',
    'api',
    'https://www.amazon.com.br',
    0,
    'Permanece inativa até configurar a Product Advertising API: access key, secret key, partner tag e região.'
),
(
    'shopee',
    'Shopee Brasil',
    'api',
    'https://shopee.com.br',
    0,
    'Permanece inativa até aprovação na Shopee Open Platform ou no programa de afiliados.'
),
(
    'loja_independente',
    'Loja virtual independente',
    'autorizado',
    NULL,
    0,
    'Reservada para feed, API ou página autorizada pelo próprio lojista.'
),
(
    'colecionaveis',
    'Lojas de colecionáveis',
    'autorizado',
    NULL,
    0,
    'Reservada para lojas especializadas que fornecerem uma fonte autorizada.'
);
