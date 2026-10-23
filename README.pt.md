<div align="center">

<img src=".github/assets/readme-banner.png" alt="Hi.Events - Open-source event ticketing and management platform" width="100%">

# Hi.Events

### Plataforma open source de bilhética e gestão de eventos

Venda bilhetes online para conferências, eventos de vida noturna, concertos, noites de discoteca, workshops e festivais.<br>
No seu próprio servidor ou na cloud. Os seus eventos, a sua marca, os seus dados.

[Experimentar a Cloud →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Demonstração](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Documentação](https://hi.events/docs?utm_source=gh-readme) · [Enterprise e licenciamento](https://hi.events/licensing?utm_source=gh-readme) · [Website](https://hi.events?utm_source=gh-readme)

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
> **Chegou o Hi.Events 2.0**, com eventos recorrentes, lugares reservados, bilheteira, cartazes e programas, início de
> sessão único, permissões de equipa e chaves de API. [Veja as novidades →](https://github.com/HiEventsDev/hi.events/releases)

<br>

<img alt="Hi.Events event dashboard on desktop and an event page on mobile" src=".github/assets/readme-showcase.png" width="100%"/>

<br>

## Porquê o Hi.Events?

A maioria das plataformas de bilhética cobra uma taxa por cada bilhete e fica com os dados dos seus clientes. **O Hi.Events é uma
alternativa open source ao Eventbrite, Tickettailor, Dice.fm e outras plataformas de bilhética** que lhe dá controlo total sobre a sua
marca, o processo de compra e os seus dados.

Milhares de organizadores de eventos em todo o mundo confiam no Hi.Events — de promotores de vida noturna e festivais a espaços, associações
e organizadores de conferências. Aloje-o por conta própria ou deixe-nos tratar de tudo no Hi.Events Cloud.

<br>

## Funcionalidades

**📅 Eventos**
- Eventos únicos e recorrentes, com preços, capacidade e horários por data
- Duplique um evento com um clique
- Locais guardados e eventos online
- Editor da página do evento e páginas de organizador com a sua marca
- Páginas de cartaz e programa

**🎟️ Bilhetes e produtos**
- Bilhetes pagos, gratuitos, por donativo e por escalões
- Lugares reservados <sup>Enterprise</sup>
- Complementos e merchandising
- Bilhetes ocultos desbloqueados com códigos promocionais
- Limites por encomenda e períodos de venda agendados
- Capacidade partilhada e listas de espera automáticas

**🛒 Vendas**
- Elementos incorporáveis e personalizáveis para o seu site: widgets de bilhetes, botões, listas de eventos, cartazes e programas
- Códigos promocionais e descontos
- Links de rastreio para promotores e afiliados
- Perguntas de registo personalizadas
- Consentimento de marketing na finalização da compra
- Rastreio com Meta, TikTok, Google Analytics e Tag Manager

**💳 Pagamentos**
- Pagamentos recebidos diretamente na sua conta Stripe
- Impostos e taxas, incluídos no preço ou acrescentados no pagamento
- Faturação e pagamentos offline
- Reembolsos totais e parciais

**🚪 Participantes e entrada**
- Bilheteira para vendas à porta <sup>Enterprise</sup>
- Check-in com a câmara do telemóvel ou um leitor USB
- Listas de check-in partilháveis com contagens em tempo real
- Mensagens para participantes e modelos de email personalizados
- Bilhetes em PDF com a sua marca
- Participantes adicionados manualmente
- Relatórios de vendas, impostos e códigos promocionais

<br>

## Pensado para organizações

**🔐 Segurança e acessos**
- Início de sessão único
- Autenticação de dois fatores
- Funções e permissões de equipa
- Vários organizadores numa só conta

**🔌 Integrações**
- REST API e chaves de API
- Webhooks
- Exportações em CSV e XLSX

**🗄️ Os seus dados**
- Alojamento próprio com Docker
- Eliminação e anonimização de contas
- Disponível em 20 idiomas

**✅ Testado**
- Um conjunto abrangente de testes é executado a cada alteração

<br>

## Início rápido

Desenvolvido com **Laravel 13** (PHP >=8.3) · **React 19** com SSR · **TypeScript** · **PostgreSQL** · **Redis** · **Docker**.

### Implementação com um clique

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
> **Utilizadores de Windows:** consulte `./docker/all-in-one/README.md` para saber como gerar as chaves.

Abra `http://localhost:8123` e crie a sua conta.

📖 [Guia de instalação completo](https://hi.events/docs/getting-started?utm_source=gh-readme)

> [!IMPORTANT]
> **Vai atualizar a partir da 1.x?** Leia as [notas de versão](https://github.com/HiEventsDev/hi.events/releases) antes de atualizar.

### REST API

Crie chaves de API em **Configurações → Chaves de API**, onde também encontra a referência da API.

<br>

## Idiomas

English, Deutsch, Français, Italiano, Nederlands, Español, Português, Português (Brasil),
Polski, Slovenčina, Magyar, Svenska, Suomi, Ελληνικά, Türkçe, Tiếng Việt, 简体中文, 繁體中文, 日本語 e 한국어.

Correções e novos idiomas são bem-vindos — consulte o [guia de contribuição](CONTRIBUTING.md).

<br>

## Hi.Events Cloud

Prefere não alojar a plataforma? O **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** é a versão totalmente
gerida, mantida pela equipa que desenvolve o Hi.Events.

[Começar agora →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Licenciamento

O Hi.Events está licenciado sob a **AGPL-3.0 com termos adicionais**. Os termos adicionais exigem que a atribuição "Powered by Hi.Events"
seja mantida nas páginas e emails gerados pelo software — consulte a [LICENCE](LICENCE) para ver a redação exata.

Os lugares reservados e a bilheteira estão abrangidos pela [Hi.Events Enterprise Licence](backend/ee/LICENCE) e exigem uma
licença para utilização em produção.

**Estão disponíveis licenças comerciais** se quiser remover a atribuição ou precisar de condições adequadas a uma implementação
white-label. [Opções de licenciamento](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Contribuir

As contribuições são bem-vindas — consulte o [guia de contribuição](CONTRIBUTING.md) para começar. Antes de iniciar um trabalho significativo, abra uma issue
ou uma discussão para alinharmos a abordagem. Os contribuidores assinam
um [CLA](CLA.md).

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Segurança

Encontrou uma vulnerabilidade? Comunique-a em privado para [security@hi.events](mailto:security@hi.events) em vez de abrir
uma issue pública. Consulte a nossa [política de segurança](SECURITY.md).

<br>

## Suporte

📖 [Documentação](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [Issues no GitHub](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Discussões](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

As novas funcionalidades e melhorias são publicadas na
[página de versões](https://github.com/HiEventsDev/hi.events/releases).

<br>

<div align="center">

**[Website](https://hi.events)** · **[Documentação](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Licenciamento](https://hi.events/licensing)**

Feito com ☘️ na Irlanda

</div>
