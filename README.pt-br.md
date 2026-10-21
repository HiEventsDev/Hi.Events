<div align="center">

<img src=".github/assets/readme-banner.png" alt="Hi.Events - Open-source event ticketing and management platform" width="100%">

# Hi.Events

### Plataforma open source de venda de ingressos e gestão de eventos

Venda ingressos online para conferências, festas, shows, baladas, workshops e festivais.<br>
Hospede você mesmo ou use a nuvem. Seus eventos, sua marca, seus dados.

[Teste o Cloud →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Demo ao vivo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Documentação](https://hi.events/docs?utm_source=gh-readme) · [Enterprise e licenciamento](https://hi.events/licensing?utm_source=gh-readme) · [Site](https://hi.events?utm_source=gh-readme)

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/hi.events/blob/develop/LICENCE)
[![GitHub Release](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![GitHub Stars](https://img.shields.io/github/stars/HiEventsDev/hi.events?style=flat)](https://github.com/HiEventsDev/hi.events)
[![Docker Pulls](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)
[![E2E Tests](https://github.com/HiEventsDev/hi.events/actions/workflows/e2e.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/e2e.yml)

<a href="https://trendshift.io/repositories/10563" target="_blank">
  <img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" width="250" height="55"/>
</a>

<p>
<a href="README.md">English</a> · <a href="README.de.md">Deutsch</a> · <a href="README.fr.md">Français</a> · <a href="README.it.md">Italiano</a> · <a href="README.nl.md">Nederlands</a> · <a href="README.es.md">Español</a> · <a href="README.pt.md">Português</a> · <a href="README.pt-br.md">Português (Brasil)</a> · <a href="README.pl.md">Polski</a> · <a href="README.sk.md">Slovenčina</a> · <a href="README.hu.md">Magyar</a> · <a href="README.sv.md">Svenska</a> · <a href="README.fi.md">Suomi</a> · <a href="README.el.md">Ελληνικά</a> · <a href="README.tr.md">Türkçe</a> · <a href="README.vi.md">Tiếng Việt</a> · <a href="README.zh-cn.md">简体中文</a> · <a href="README.zh-hk.md">繁體中文</a> · <a href="README.ja.md">日本語</a> · <a href="README.ko.md">한국어</a>
</p>

</div>

<br>

> [!NOTE]
> **O Hi.Events 2.0 chegou** com eventos recorrentes, assentos reservados, bilheteria, line-ups e programação, login
> único, permissões de equipe e chaves de API. [Confira as novidades →](https://github.com/HiEventsDev/hi.events/releases)

<br>

<img alt="Hi.Events event dashboard on desktop and an event page on mobile" src=".github/assets/readme-showcase.png" width="100%"/>

<br>

## Por que o Hi.Events?

A maioria das plataformas de ingressos cobra uma taxa por ingresso vendido e fica com os dados dos seus clientes. **O Hi.Events é uma
alternativa open source ao Eventbrite, Tickettailor, Dice.fm e outras plataformas de ingressos** que dá a você controle total sobre sua
marca, seu checkout e seus dados.

Milhares de organizadores de eventos no mundo todo confiam no Hi.Events — de produtores de festas e festivais a casas de show, grupos comunitários
e organizadores de conferências. Rode na sua própria infraestrutura ou deixe com a gente no Hi.Events Cloud.

<br>

## Recursos

**📅 Eventos**
- Eventos únicos e recorrentes, com preços, capacidade e horários por data
- Duplique um evento com um clique
- Locais salvos e eventos online
- Editor da página do evento e páginas de organizador com a sua marca
- Páginas de line-up e programação

**🎟️ Ingressos e produtos**
- Ingressos pagos, gratuitos, por doação e por lotes
- Assentos reservados <sup>Enterprise</sup>
- Complementos e produtos (merch)
- Ingressos ocultos liberados por códigos promocionais
- Limites por pedido e períodos de venda agendados
- Capacidade compartilhada e listas de espera automáticas

**🛒 Vendas**
- Embeds personalizáveis para o seu site: widgets de ingressos, botões, listas de eventos, line-ups e programação
- Códigos promocionais e descontos
- Links de rastreamento para promoters e afiliados
- Perguntas de cadastro personalizadas
- Opt-in de marketing no checkout
- Rastreamento com Meta, TikTok, Google Analytics e Tag Manager

**💳 Pagamentos**
- Repasses direto na sua própria conta Stripe
- Impostos e taxas, incluídos no preço ou cobrados no checkout
- Faturamento e pagamentos offline
- Reembolsos totais e parciais

**🚪 Participantes e portaria**
- Bilheteria para vendas na porta <sup>Enterprise</sup>
- Check-in pela câmera do celular ou leitor USB
- Listas de check-in compartilháveis com contagem em tempo real
- Mensagens para participantes e modelos de e-mail personalizados
- Ingressos em PDF com a sua marca
- Participantes adicionados manualmente
- Relatórios de vendas, impostos e códigos promocionais

<br>

## Feito para organizações

**🔐 Segurança e acesso**
- Login único
- Autenticação em duas etapas
- Funções e permissões de equipe
- Vários organizadores em uma única conta

**🔌 Integrações**
- REST API e chaves de API
- Webhooks
- Exportação em CSV e XLSX

**🗄️ Seus dados**
- Hospede você mesmo com Docker
- Exclusão e anonimização de contas
- Disponível em 20 idiomas

**✅ Testado**
- Uma suíte de testes abrangente roda a cada alteração

<br>

## Início rápido

Desenvolvido com **Laravel 13** (PHP >=8.3) · **React 19** com SSR · **TypeScript** · **PostgreSQL** · **Redis** · **Docker**.

### Deploy com um clique

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.com/button.svg)](https://railway.com/deploy/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone https://github.com/HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

cp .env.example .env

# Generate keys (Linux/macOS)
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Usuários de Windows:** veja `./docker/all-in-one/README.md` para saber como gerar as chaves.

Abra `http://localhost:8123` e crie sua conta.

📖 [Guia de instalação completo](https://hi.events/docs/getting-started?utm_source=gh-readme)

> [!IMPORTANT]
> **Vai atualizar da versão 1.x?** Leia as [notas de versão](https://github.com/HiEventsDev/hi.events/releases) antes de atualizar.

### REST API

Crie chaves de API em **Configurações → Chaves de API**, onde você também encontra a referência da API.

<br>

## Idiomas

English, Deutsch, Français, Italiano, Nederlands, Español, Português, Português (Brasil),
Polski, Slovenčina, Magyar, Svenska, Suomi, Ελληνικά, Türkçe, Tiếng Việt, 简体中文, 繁體中文, 日本語 e 한국어.

Correções e novos idiomas são bem-vindos — veja o [guia de contribuição](CONTRIBUTING.md).

<br>

## Hi.Events Cloud

Prefere não hospedar por conta própria? O **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** é a versão totalmente
gerenciada, operada pela equipe que desenvolve o Hi.Events.

[Comece agora →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Licenciamento

O Hi.Events é licenciado sob a **AGPL-3.0 com termos adicionais**. Os termos adicionais exigem que a atribuição "Powered by Hi.Events"
seja mantida nas páginas e e-mails gerados pelo software — veja a [LICENCE](LICENCE) para o texto exato.

Os assentos reservados e a bilheteria são cobertos pela [Hi.Events Enterprise Licence](backend/ee/LICENCE) e exigem uma
licença para uso em produção.

**Licenças comerciais estão disponíveis** se você quiser remover a atribuição ou precisar de termos adequados a uma implantação
white-label. [Opções de licenciamento](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Como contribuir

Contribuições são bem-vindas — veja o [guia de contribuição](CONTRIBUTING.md) para começar. Antes de começar um trabalho grande, abra uma issue ou
discussão para alinharmos a abordagem. Quem contribui assina
um [CLA](CLA.md).

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Segurança

Encontrou uma vulnerabilidade? Reporte de forma privada para [security@hi.events](mailto:security@hi.events) em vez de abrir
uma issue pública. Veja nossa [política de segurança](SECURITY.md).

<br>

## Suporte

📖 [Documentação](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [Issues no GitHub](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Discussões](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

Novos recursos e melhorias são publicados na
[página de releases](https://github.com/HiEventsDev/hi.events/releases).

<br>

<div align="center">

**[Site](https://hi.events)** · **[Documentação](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Licenciamento](https://hi.events/licensing)**

Feito com ☘️ na Irlanda

</div>
