<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - Plataforma Open Source de Venda de Ingressos para Eventos" width="100%">

# Hi.Events

### Plataforma open-source de venda de ingressos e gestão de eventos

Venda ingressos online para conferências, eventos noturnos, shows, festas em clubes, workshops e festivais.  
Autohospedado ou na nuvem. Seus eventos, sua marca, seus dados.

[Experimente na Nuvem →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Demo ao Vivo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Documentação](https://hi.events/docs?utm_source=gh-readme) · [Site](https://hi.events?utm_source=gh-readme)

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![GitHub Release](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![GitHub Stars](https://img.shields.io/github/stars/HiEventsDev/hi.events?style=flat)](https://github.com/HiEventsDev/hi.events/stargazers)
[![Docker Pulls](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)
[![E2E Tests](https://github.com/HiEventsDev/hi.events/actions/workflows/e2e.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/e2e.yml)

<a href="https://trendshift.io/repositories/10563" target="_blank">
  <img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" width="250" height="55"/>
</a>

<p>
<a href="README.de.md">Deutsch</a> · <a href="README.pt.md">Português</a> · <a href="README.fr.md">Français</a> · <a href="README.it.md">Italiano</a> · <a href="README.nl.md">Nederlands</a> · <a href="README.es.md">Español</a> · <a href="README.zh-cn.md">中文</a> · <a href="README.zh-hk.md">繁體中文</a> · <a href="README.ja.md">日本語</a> · <a href="README.vi.md">Tiếng Việt</a> · <a href="README.tr.md">Türkçe</a> · <a href="README.hu.md">Magyar</a> · <a href="README.pl.md">Polski</a> · <a href="README.sk.md">Slovenčina</a> · <a href="README.el.md">Ελληνικά</a> · <a href="README.fi.md">Suomi</a>
</p>

</div>

<br>

## Por que Hi.Events?

A maioria das plataformas de venda de ingressos cobra taxas por ingresso e prende os seus dados ao ecossistema delas.
**O Hi.Events é uma alternativa moderna e open-source ao Eventbrite, Tickettailor, Dice.fm e a outras plataformas de
ticketing** para organizadores que querem controle total sobre marca, checkout, dados e infraestrutura.

Usado por milhares de organizadores no mundo todo — de promotores de vida noturna e festivais a casas de espetáculo,
grupos comunitários e organizadores de conferências. Hospede você mesmo ou deixe que cuidemos disso na Hi.Events Cloud.

<br>

<img alt="Hi.Events Dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Funcionalidades

**🎟️ Venda de ingressos** — ingressos gratuitos, pagos, por doação e em níveis · eventos recorrentes e com várias
datas · listas de espera quando esgotam · códigos promocionais, incluindo ingressos ocultos e liberados por código ·
adicionais e categorias de produtos · gestão de impostos, taxas e capacidade

**🎨 Marca e personalização** — editor da página do evento para imagem de capa, cores e tipografia · página do
organizador com a sua marca · ingressos em PDF personalizáveis · widget de venda incorporável · controle de metadados
de SEO

**👥 Gestão de participantes** — perguntas personalizadas no checkout · busca, filtros e exportação CSV/XLSX avançados ·
reembolsos totais e parciais · mensagens em massa · check-in por QR code com registros de leitura e listas de check-in
com acesso controlado

**📊 Análise e crescimento** — painel de vendas · rastreamento de afiliados · relatórios de vendas diárias, vendas por
produto, códigos promocionais, receita e impostos · webhooks de saída

**⚙️ Operações** — perfis multiusuário · pagamentos via Stripe Connect · métodos de pagamento offline · faturamento
automático · eventos online e presenciais · suporte multilíngue · API REST completa com
[documentação OpenAPI interativa](#rest-api)

<br>

## Início Rápido

Desenvolvido com **Laravel 13** (PHP >=8.3) · **React 19** com SSR · **TypeScript** · **PostgreSQL** · **Redis** ·
**Docker**.

### Implantação com Um Clique

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# Gerar chaves (Linux/macOS)
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Usuários Windows:** veja `./docker/all-in-one/README.md` para instruções de geração de chaves.

Abra `http://localhost:8123` e crie a sua conta.

📖 [Guia completo de instalação](https://hi.events/docs/getting-started?utm_source=gh-readme)

### REST API

O Hi.Events inclui uma API REST documentada. Defina `API_DOCS_ENABLED=true` no seu `.env` para servir a documentação
OpenAPI interativa em `/docs/api` na sua própria instância, ou exporte a especificação com:

```bash
php artisan scramble:export
```

<br>

## Hi.Events Cloud

Prefere não hospedar por conta própria? O **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)**
é a versão totalmente gerenciada deste repositório — sem configuração, com atualizações automáticas e infraestrutura
gerenciada pela equipe que desenvolve o Hi.Events.

[Começar →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Licenciamento

O Hi.Events é licenciado sob **AGPL-3.0 com termos adicionais**. Os termos adicionais exigem que a atribuição "Powered
by Hi.Events" seja mantida nas páginas e e-mails gerados pelo software — consulte a [LICENCE](LICENCE) para o texto
exato.

**Licenças comerciais estão disponíveis** caso queira remover a atribuição ou precise de termos adequados a uma
implantação white-label. [Opções de licenciamento](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Contribuindo

Contribuições são bem-vindas — veja o [guia de contribuição](CONTRIBUTING.md) para começar. Abra uma issue ou discussão
antes de iniciar um trabalho significativo, para alinharmos a abordagem. Quem contribui assina um [CLA](CLA.md).

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Segurança

Encontrou uma vulnerabilidade? Reporte-a em privado para [security@hi.events](mailto:security@hi.events) em vez de abrir
uma issue pública. Veja a nossa [política de segurança](SECURITY.md).

<br>

## Suporte

📖 [Documentação](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Discussões](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

Novas funcionalidades e melhorias são listadas na
[página de releases](https://github.com/HiEventsDev/hi.events/releases).

<br>

<div align="center">

**[Site](https://hi.events)** · **[Documentação](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Licenciamento](https://hi.events/licensing)**

Made with ☘️ in Ireland

</div>
