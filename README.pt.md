<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - Plataforma Open Source de Venda de Ingressos para Eventos" width="100%">

# Hi.Events

### Plataforma open-source de venda de ingressos e gestão de eventos

Venda ingressos online para conferências, eventos noturnos, shows, festas em clubes, workshops e festivais.
Autohospedado ou na nuvem. Seus eventos, sua marca, seus dados.

[Experimente na Nuvem →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Demo ao Vivo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Documentação](https://hi.events/docs?utm_source=gh-readme) · [Site](https://hi.events?utm_source=gh-readme)

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![GitHub Release](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![Run Unit Tests](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml)
[![Docker Pulls](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)

<a href="https://trendshift.io/repositories/10563" target="_blank">
  <img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" width="250" height="55"/>
</a>

<p>
<a href="README.de.md">Deutsch</a> · <a href="README.pt.md">Português</a> · <a href="README.pt-br.md">Português do Brasil</a> · <a href="README.fr.md">Français</a> · <a href="README.it.md">Italiano</a> · <a href="README.nl.md">Nederlands</a> · <a href="README.es.md">Español</a> · <a href="README.zh-cn.md">中文</a> · <a href="README.zh-hk.md">繁體中文</a> · <a href="README.ja.md">日本語</a> · <a href="README.vi.md">Tiếng Việt</a> · <a href="README.tr.md">Türkçe</a> · <a href="README.hu.md">Magyar</a> · <a href="README.pl.md">Polski</a> · <a href="README.sk.md">Slovenčina</a>
</p>

</div>

<br>

## Por que Hi.Events?

A maioria das plataformas de venda de ingressos cobra taxas por ingresso e mantém seus dados presos em seus ecossistemas. **Hi.Events é uma alternativa moderna e open-source ao Eventbrite, Tickettailor, Dice.fm e outras plataformas de venda de ingressos** para organizadores que desejam controle total sobre branding, checkout, dados e infraestrutura.

Desenvolvido para promotores de vida noturna, organizadores de festivais, locais de eventos, grupos comunitários e organizadores de conferências.

<br>

<img alt="Dashboard do Hi.Events" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Funcionalidades

<table>
<tr>
<td width="50%" valign="top">

### 🎟️ Venda de Ingressos

- Tipos de ingressos flexíveis (gratuitos, pagos, doação, em camadas)
- Ingressos ocultos e bloqueados por códigos promocionais
- Códigos promocionais e acesso antecipado
- Produtos complementares (merchandising, upgrades, extras)
- Categorias de produtos para organização
- Suporte completo a impostos e taxas (IVA, taxas de serviço)
- Gestão de capacidade e limites compartilhados

</td>
<td width="50%" valign="top">

### 🎨 Branding e Personalização

- Checkout bonito e otimizado para conversão
- Designs de ingressos PDF personalizáveis
- Página inicial do organizador com marca própria
- Editor de página de eventos com arrastar e soltar
- Widget de ingressos incorporável
- Ferramentas de SEO (meta tags, Open Graph)

</td>
</tr>
<tr>
<td width="50%" valign="top">

### 👥 Gestão de Participantes

- Perguntas personalizadas no checkout
- Busca avançada, filtragem e exportação (CSV/XLSX)
- Reembolsos totais e parciais
- Mensagens em massa por tipo de ingresso
- Check-in com código QR e registros de digitalização
- Listas de check-in com controle de acesso

</td>
<td width="50%" valign="top">

### 📊 Análise e Crescimento

- Dashboard de vendas em tempo real
- Rastreamento de afiliados e referências
- Relatórios avançados (vendas, impostos, promoções)
- Webhooks (Zapier, Make, CRMs)

</td>
</tr>
<tr>
<td colspan="2" valign="top">

### ⚙️ Operações

Funções e permissões multiusuário · Pagamentos instantâneos via Stripe Connect · Métodos de pagamento offline · Suporte para eventos offline ·
Faturamento automático · Arquivo de eventos · Suporte multilíngue · API REST completa

</td>
</tr>
</table>

<br>

## Comparação

| Funcionalidade                           | Hi.Events | Eventbrite | Tickettailor | Dice     |
|:-----------------------------------------|:----------|:-----------|:-------------|:---------|
| Opção autohospedada                      | ✅         | ❌          | ❌            | ❌        |
| Código aberto                            | ✅         | ❌          | ❌            | ❌        |
| Sem taxas por ingresso (autohospedado)  | ✅         | ❌          | ❌            | ❌        |
| Branding completamente personalizado     | ✅         | Limitado   | ✅            | Limitado |
| Rastreamento de afiliados                | ✅         | ✅          | ❌            | ❌        |
| Acesso à API                             | ✅         | ✅          | ✅            | Limitado |
| Proprietário dos seus dados              | ✅         | ❌          | ❌            | ❌        |

<br>

## Início Rápido

### Implantação com Um Clique

[![Deploy no DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy no Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy no Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy no Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

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
> **Usuários Windows:** Consulte `./docker/all-in-one/README.md` para instruções de geração de chaves.

Abra `http://localhost:8123` e crie sua conta.

📖 [Guia completo de instalação](https://hi.events/docs/getting-started?utm_source=gh-readme)

<br>

## Hi.Events Cloud

Prefere não autohospedar? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** é uma
opção totalmente gerenciada com configuração zero, atualizações automáticas e infraestrutura gerenciada.

[Comece agora →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Documentação

| Recurso                 | Link                                                                                          |
|:------------------------|:----------------------------------------------------------------------------------------------|
| Primeiros Passos        | [hi.events/docs/getting-started](https://hi.events/docs/getting-started?utm_source=gh-readme) |
| Configuração            | [hi.events/docs/configuration](https://hi.events/docs/configuration?utm_source=gh-readme)     |
| Referência da API       | [hi.events/docs/api](https://hi.events/docs/api?utm_source=gh-readme)                         |
| Webhooks                | [hi.events/docs/webhooks](https://hi.events/docs/webhooks?utm_source=gh-readme)               |

<br>

## Contribuindo

Contribuições são bem-vindas. Consulte o [guia de contribuição](CONTRIBUTING.md) para mais detalhes.

<br>

## Suporte

📖 [Documentação](https://hi.events/docs?utm_source=gh-readme) · 📧 [hello@hi.events](mailto:hello@hi.events) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues)

<br>

## Registro de Alterações

Mantenha-se atualizado com novos recursos e melhorias na
[página de releases](https://github.com/HiEventsDev/hi.events/releases).

<br>

## Licença

Hi.Events está licenciado sob **AGPL-3.0 com termos adicionais**. Licenciamento comercial
disponível. [Saiba mais](https://hi.events/licensing).

<br>

<div align="center">

**[Site](https://hi.events)** · **[Documentação](https://hi.events/docs)** · **[Twitter/X](https://x.com/HiEventsTickets)**

Feito com ☘️ na Irlanda

</div>
