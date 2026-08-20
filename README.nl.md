<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - Open Source Evenemententicketplatform" width="100%">

# Hi.Events

### Open-source evenemententicket- en beheerplatform

Verkoop online tickets voor conferenties, uitgaansevenementen, concerten, clubavonden, workshops en festivals.  
Zelf-gehost of cloud. Jouw evenementen, jouw merk, jouw data.

[Probeer Cloud →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Live Demo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Documentatie](https://hi.events/docs?utm_source=gh-readme) · [Website](https://hi.events?utm_source=gh-readme)

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![GitHub Release](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![GitHub Stars](https://img.shields.io/github/stars/HiEventsDev/hi.events?style=flat)](https://github.com/HiEventsDev/hi.events/stargazers)
[![Docker Pulls](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)
[![E2E Tests](https://github.com/HiEventsDev/hi.events/actions/workflows/e2e.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/e2e.yml)

<a href="https://trendshift.io/repositories/10563" target="_blank">
  <img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" width="250" height="55"/>
</a>

<p>
<a href="README.de.md">Deutsch</a> · <a href="README.pt.md">Português</a> · <a href="README.fr.md">Français</a> · <a href="README.it.md">Italiano</a> · <a href="README.nl.md">Nederlands</a> · <a href="README.es.md">Español</a> · <a href="README.zh-cn.md">中文</a> · <a href="README.zh-hk.md">繁體中文</a> · <a href="README.ja.md">日本語</a> · <a href="README.vi.md">Tiếng Việt</a> · <a href="README.tr.md">Türkçe</a> · <a href="README.hu.md">Magyar</a> · <a href="README.pl.md">Polski</a> · <a href="README.sk.md">Slovenčina</a> · <a href="README.el.md">Ελληνικά</a>
</p>

</div>

<br>

## Waarom Hi.Events?

De meeste ticketplatforms rekenen kosten per ticket en sluiten je data op in hun eigen ecosysteem. **Hi.Events is een
modern, open-source alternatief voor Eventbrite, Tickettailor, Dice.fm en andere ticketplatforms** voor organisatoren
die volledige controle willen over branding, checkout, data en infrastructuur.

Wereldwijd gebruikt door duizenden organisatoren — van uitgaanspromotors en festivals tot venues, buurtinitiatieven en
conferentieorganisatoren. Host het zelf, of laat ons het draaien op Hi.Events Cloud.

<br>

<img alt="Hi.Events Dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Functies

**🎟️ Ticketing & verkoop** — gratis, betaalde, donatie- en gelaagde tickets · terugkerende evenementen en evenementen
met meerdere data · wachtlijsten bij uitverkoop · kortingscodes, inclusief verborgen en code-vergrendelde tickets ·
product-add-ons en -categorieën · beheer van btw, servicekosten en capaciteit

**🎨 Branding & aanpassing** — ontwerper voor de evenementpagina met omslagafbeelding, kleuren en typografie ·
organisatorpagina in je eigen huisstijl · aanpasbare PDF-tickets · insluitbare ticketwidget · SEO-metadata-instellingen

**👥 Bezoekerbeheer** — eigen checkoutvragen · geavanceerd zoeken, filteren en exporteren naar CSV/XLSX · volledige en
gedeeltelijke terugbetalingen · bulkberichten · QR-check-in met scanlogboeken en check-inlijsten met toegangsbeheer

**📊 Analytics & groei** — verkoopdashboard · affiliate-tracking · rapporten over dagomzet, productverkoop,
kortingscodes, omzet en btw · uitgaande webhooks

**⚙️ Operations** — rollen voor meerdere gebruikers · betalingen via Stripe Connect · offline betaalmethoden ·
automatische facturatie · online en fysieke evenementen · meertalige ondersteuning · volledige REST API met
[interactieve OpenAPI-documentatie](#rest-api)

<br>

## Snelle Start

Gebouwd met **Laravel 13** (PHP >=8.3) · **React 19** met SSR · **TypeScript** · **PostgreSQL** · **Redis** ·
**Docker**.

### One-Click Deploy

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# Sleutels genereren (Linux/macOS)
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Windows-gebruikers:** zie `./docker/all-in-one/README.md` voor instructies over het genereren van sleutels.

Open `http://localhost:8123` en maak je account aan.

📖 [Volledige installatiehandleiding](https://hi.events/docs/getting-started?utm_source=gh-readme)

### REST API

Hi.Events levert een gedocumenteerde REST API. Zet `API_DOCS_ENABLED=true` in je `.env` om interactieve
OpenAPI-documentatie op `/docs/api` van je eigen instantie te serveren, of exporteer de specificatie met:

```bash
php artisan scramble:export
```

<br>

## Hi.Events Cloud

Liever niet zelf hosten? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** is de volledig
beheerde versie van deze repository — geen setup, automatische updates en beheerde infrastructuur, gedraaid door het
team dat Hi.Events bouwt.

[Aan de slag →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Licenties

Hi.Events valt onder **AGPL-3.0 met aanvullende voorwaarden**. Die aanvullende voorwaarden vereisen dat de vermelding
"Powered by Hi.Events" behouden blijft op pagina's en in e-mails die door de software worden gegenereerd — zie
[LICENCE](LICENCE) voor de exacte formulering.

**Commerciële licenties zijn beschikbaar** als je de vermelding wilt verwijderen of voorwaarden nodig hebt die passen
bij een white-label-implementatie. [Licentieopties](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Bijdragen

Bijdragen zijn welkom — bekijk de [bijdragehandleiding](CONTRIBUTING.md) om te beginnen. Open een issue of discussie
voordat je aan omvangrijk werk begint, zodat we de aanpak kunnen afstemmen. Bijdragers ondertekenen een [CLA](CLA.md).

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Beveiliging

Een kwetsbaarheid gevonden? Meld deze vertrouwelijk via [security@hi.events](mailto:security@hi.events) in plaats van
een openbare issue te openen. Zie ons [beveiligingsbeleid](SECURITY.md).

<br>

## Ondersteuning

📖 [Documentatie](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Discussies](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

Nieuwe functies en verbeteringen staan op
de [releasespagina](https://github.com/HiEventsDev/hi.events/releases).

<br>

<div align="center">

**[Website](https://hi.events)** · **[Documentatie](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Licenties](https://hi.events/licensing)**

Made with ☘️ in Ireland

</div>
