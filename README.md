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

- Não consulta o Mercado Livre: a importação automática foi removida.
- Amazon e feeds JSON têm drivers preparados, mas ficam inativos sem configuração válida; Shopee ainda não tem driver.
- `cron/atualizar_precos.php` informa que o agendador ainda não está integrado aos conectores e sai sem consultar lojas ou gravar ofertas.
- `cron/verificar_alertas.php` continua reservado para a Fase 8 e sai sem gravar notificações.

## Fase 3: arquitetura do motor de pesquisa

- `MarketplaceDriver` define a interface para registrar fontes independentes.
- `SearchEngine` coordena os drivers, normaliza as ofertas, remove duplicadas e devolve erros por fonte sem esconder falhas.
- `OfferNormalizer` valida HTTPS, preço, frete, condição, disponibilidade e moeda; calcula o total somente quando o frete é conhecido.
- `ProductOfferMatcher` prioriza EAN/GTIN e SKU exatos e, sem esses códigos, compara nome, marca e modelo. Ofertas com EAN divergente são rejeitadas.
- Os testes desta fase usam fontes simuladas; nenhum marketplace é consultado por eles.

Para validar essa arquitetura:

```powershell
C:\php\php-8\php.exe tests\fase3_smoke.php
```

## Fase 4: fontes autorizadas

- `AmazonCreatorsApiDriver` implementa busca pela Creators API, sucessora da PA-API 5. Ele só fica disponível com credenciais válidas de uma conta Associates aprovada e Partner Tag. A elegibilidade e o acesso são controlados pela Amazon e podem exigir vendas qualificadas; confira os requisitos atuais na [documentação oficial](https://affiliate-program.amazon.com/creatorsapi/docs/en-us/onboarding/register-for-creators-api).
- Para solicitar acesso, participe do [Amazon Associates](https://affiliate-program.amazon.com/), consulte a seção Creators API no Associates Central e obtenha as credenciais e o Partner Tag por lá. O `.env.example` mostra as variáveis; mantenha os valores reais apenas no `.env`, nunca no repositório. A API ainda não é chamada pelo cron e as ofertas não são persistidas.
- `AuthorizedJsonFeedDriver` lê um arquivo JSON local de uma fonte cuja utilização foi autorizada. Configure `AUTHORIZED_JSON_FEED_PATH` para ativá-lo; caminhos relativos são resolvidos a partir da raiz do projeto. O arquivo deve ser uma lista JSON de ofertas no formato comum aceito por `MarketplaceDriver`, por exemplo:

```json
[
  {
    "titulo": "Produto anunciado",
    "url": "https://loja.example/produto",
    "preco": "129.90",
    "frete_status": "desconhecido",
    "disponibilidade": "disponivel"
  }
]
```

- Shopee permanece pendente: a Open Platform exige aplicativo e autorização conforme o tipo de catálogo/loja. Sem acesso concedido e sem escopo confirmado, não há driver, scraping nem busca pública simulada.
- O cron continua deliberadamente sem executar consultas nem gravar ofertas. Credenciais configuradas, por si só, não iniciam pesquisas.

| Fonte | O que configurar |
| --- | --- |
| Mercado Livre | Não integrado nesta etapa; é necessário implementar e registrar um driver antes de consultar. |
| Amazon Brasil | Creators API: Credential ID, Credential Secret, versão e Partner Tag no `.env`. A antiga PA-API 5 não é usada. |
| Shopee Brasil | Aplicativo aprovado e autorização para o catálogo adequado; integração pendente. |
| Lojas independentes e de colecionáveis | Feed JSON local ou API, com autorização explícita do lojista. |
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
