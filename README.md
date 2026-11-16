# Price Hunter

Plataforma em PHP 8.2 e MySQL 8 para cadastrar produtos, consultar fontes autorizadas e acompanhar o menor custo confirmado. A Fase 1 entrega a estrutura, o banco, a autenticação e o painel conectado aos dados reais.

Nenhuma oferta é inventada. Enquanto um marketplace não tiver driver funcional, o painel mostra zero preços.

## Requisitos

- PHP 8.2 ou superior, com PDO MySQL, mbstring, curl, openssl e json.
- MySQL 8.
- Composer, apenas para o autoload. Não há framework.

O Apache desta máquina está com o módulo PHP 7.4. O Price Hunter não roda nesse módulo. Use o binário PHP 8.2 em `C:\php\php-8\php.exe`.

## Configuração

1. Copie `.env.example` para `.env`.
2. Preencha `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD`.
3. Gere `APP_KEY` com qualquer sequência longa e aleatória. Ela fica só no servidor.
4. Instale o autoload e crie o banco:

```powershell
composer dump-autoload
C:\php\php-8\php.exe database\migrate.php
```

A migração cria o banco `price_hunter`, as tabelas e o catálogo de marketplaces. Ela não cria usuário nem oferta.

## Executar

```powershell
C:\php\php-8\php.exe -S 127.0.0.1:8090 -t public public\router.php
```

Abra http://127.0.0.1:8090, crie uma conta e entre no painel.

Se o endereço for aberto pelo Apache em `http://localhost/PRICE%20HUNTER/`, a página informa que aquele servidor está em PHP 7.4. Os arquivos internos continuam bloqueados pelo `.htaccess`.

## Testar a Fase 1

Com o servidor da porta 8090 no ar:

```powershell
C:\php\php-8\php.exe tests\fase1_smoke.php
```

O script cria contas temporárias `@pricehunter.test`, verifica login, CSRF, isolamento entre usuários e a ausência de preços inventados. Ao final, apaga essas contas.

## O que a Fase 1 faz

- Cadastro, login, logout e perfil, com `password_hash`.
- Sessão própria, CSRF, rotas privadas e escape de HTML.
- PDO com prepared statements.
- Painel com contagens reais: produtos, lojas consultadas, ofertas e alertas de preço.
- Catálogo das fontes, sem consultar as lojas.
- Cada usuário lê somente os próprios registros.

## O que ainda não faz

- Não consulta Mercado Livre, Amazon ou Shopee: ainda não há driver real registrado.
- `cron/atualizar_precos.php` informa que não há fonte de pesquisa ativa e sai sem consultar lojas ou gravar ofertas.
- `cron/verificar_alertas.php` continua reservado para a Fase 8 e sai sem gravar notificações.

## Fase 3: arquitetura do motor de pesquisa

- `MarketplaceDriver` define a interface para registrar fontes independentes.
- `SearchEngine` coordena os drivers, normaliza as ofertas, remove duplicadas e devolve erros por fonte sem esconder falhas.
- `OfferNormalizer` valida HTTPS, preço, frete, condição, disponibilidade e moeda; calcula o total somente quando o frete é conhecido.
- `ProductOfferMatcher` prioriza EAN/GTIN e SKU exatos e, sem esses códigos, compara nome, marca e modelo. Ofertas com EAN divergente são rejeitadas.
- Nenhum driver de produção está registrado nesta etapa. O motor não faz consultas externas nem cria ofertas artificiais; os testes usam apenas fontes simuladas.

Para validar essa arquitetura:

```powershell
C:\php\php-8\php.exe tests\fase3_smoke.php
```

## Credenciais futuras

Quando a integração correspondente for implementada, as chaves entram no `.env` ou na tabela `integracoes`. Nada disso vai para o navegador.

| Fonte | O que configurar |
| --- | --- |
| Mercado Livre | Não integrado nesta etapa; é necessário implementar e registrar um driver antes de consultar. |
| Amazon Brasil | Product Advertising API: access key, secret key, partner tag e região `www.amazon.com.br`. |
| Shopee Brasil | Aprovação na Open Platform ou no programa de afiliados. |
| Lojas independentes e de colecionáveis | Feed, API ou permissão explícita do lojista. |
| E-mail | Servidor SMTP na Fase 8. |
| WhatsApp | Provedor oficial autorizado na Fase 8. |

## Estrutura

```text
app/            núcleo, controladores, modelos e contrato das integrações
config/         aplicação e banco
database/       migrations
cron/           entrada do agendador, ainda sem pesquisa
public/         único diretório publicado
routes/         rotas web
storage/        logs e sessões
views/          telas
```
