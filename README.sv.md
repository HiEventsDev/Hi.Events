<div align="center">

<img src=".github/assets/readme-banner.png" alt="Hi.Events - Open-source event ticketing and management platform" width="100%">

# Hi.Events

### Biljettförsäljning och eventhantering med öppen källkod

Sälj biljetter online till konferenser, nattklubbsevent, konserter, klubbkvällar, workshops och festivaler.<br>
På egen server eller i molnet. Dina evenemang, ditt varumärke, din data.

[Testa Cloud →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Livedemo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Dokumentation](https://hi.events/docs?utm_source=gh-readme) · [Enterprise och licenser](https://hi.events/licensing?utm_source=gh-readme) · [Webbplats](https://hi.events?utm_source=gh-readme)

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
> **Hi.Events 2.0 är här** med återkommande evenemang, reserverade platser, biljettkassa, line-ups och scheman, enkel
> inloggning, teambehörigheter och API-nycklar. [Se vad som är nytt →](https://github.com/HiEventsDev/hi.events/releases)

<br>

<img alt="Hi.Events event dashboard on desktop and an event page on mobile" src=".github/assets/readme-showcase.png" width="100%"/>

<br>

## Varför Hi.Events?

De flesta biljettplattformar tar en avgift på varje biljett och behåller din kunddata. **Hi.Events är ett alternativ med öppen källkod
till Eventbrite, Tickettailor, Dice.fm och andra biljettplattformar** som ger dig full kontroll över ditt
varumärke, din kassa och din data.

Tusentals arrangörer världen över litar på Hi.Events – från nattklubbsarrangörer och festivaler till scener, föreningar
och konferensarrangörer. Driv det själv, eller låt oss driva det åt dig i Hi.Events Cloud.

<br>

## Funktioner

**📅 Evenemang**
- Enstaka och återkommande evenemang, med priser, kapacitet och tider per datum
- Duplicera ett evenemang med ett klick
- Sparade platser och onlineevenemang
- Designverktyg för evenemangssidor och arrangörssidor med ditt varumärke
- Sidor för line-up och schema

**🎟️ Biljetter och produkter**
- Betalda biljetter, gratisbiljetter, donationsbiljetter och biljetter i prisnivåer
- Reserverade platser <sup>Enterprise</sup>
- Tillägg och merch
- Dolda biljetter som låses upp med kampanjkoder
- Gränser per beställning och schemalagda försäljningsperioder
- Delad kapacitet och automatiska väntelistor

**🛒 Försäljning**
- Anpassningsbara inbäddningar för din egen webbplats: biljettwidgets, knappar, evenemangslistor, line-ups och scheman
- Kampanjkoder och rabatter
- Spårningslänkar för promotorer och partners
- Egna registreringsfrågor
- Marknadsföringssamtycke i kassan
- Spårning med Meta, TikTok, Google Analytics och Tag Manager

**💳 Betalningar**
- Utbetalningar till ditt eget Stripe-konto
- Skatter och avgifter, inkluderade eller tillagda i kassan
- Fakturering och offlinebetalningar
- Hela och delvisa återbetalningar

**🚪 Deltagare och insläpp**
- Biljettkassa för försäljning i dörren <sup>Enterprise</sup>
- Incheckning med mobilkameran eller USB-skanner
- Delbara incheckningslistor med siffror i realtid
- Meddelanden till deltagare och egna e-postmallar
- PDF-biljetter med ditt varumärke
- Manuellt tillagda deltagare
- Rapporter för försäljning, moms och kampanjkoder

<br>

## Byggt för organisationer

**🔐 Säkerhet och åtkomst**
- Enkel inloggning
- Tvåfaktorsautentisering
- Teamroller och behörigheter
- Flera arrangörer under ett konto

**🔌 Integrationer**
- REST API och API-nycklar
- Webhooks
- Export till CSV och XLSX

**🗄️ Din data**
- Kör på egen server med Docker
- Kontoradering och anonymisering
- Finns på 20 språk

**✅ Testat**
- En omfattande testsvit körs vid varje ändring

<br>

## Kom igång

Byggt med **Laravel 13** (PHP >=8.3) · **React 19** med SSR · **TypeScript** · **PostgreSQL** · **Redis** · **Docker**.

### Driftsättning med ett klick

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
> **Windows-användare:** Se `./docker/all-in-one/README.md` för instruktioner om hur du genererar nycklar.

Öppna `http://localhost:8123` och skapa ditt konto.

📖 [Fullständig installationsguide](https://hi.events/docs/getting-started?utm_source=gh-readme)

> [!IMPORTANT]
> **Uppgraderar du från 1.x?** Läs [versionsinformationen](https://github.com/HiEventsDev/hi.events/releases) innan du uppgraderar.

### REST API

Skapa API-nycklar under **Inställningar → API-nycklar**, där du även hittar API-referensen.

<br>

## Språk

English, Deutsch, Français, Italiano, Nederlands, Español, Português, Português (Brasil),
Polski, Slovenčina, Magyar, Svenska, Suomi, Ελληνικά, Türkçe, Tiếng Việt, 简体中文, 繁體中文, 日本語 och 한국어.

Rättelser och nya språk är välkomna – se [bidragsguiden](CONTRIBUTING.md).

<br>

## Hi.Events Cloud

Vill du hellre slippa drifta själv? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** är den helt
förvaltade versionen, som drivs av teamet bakom Hi.Events.

[Kom igång →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Licenser

Hi.Events är licensierat under **AGPL-3.0 med ytterligare villkor**. De ytterligare villkoren kräver att attributionen "Powered by Hi.Events"
behålls på sidor och i e-postmeddelanden som programvaran genererar – se [LICENCE](LICENCE) för exakt formulering.

Reserverade platser och biljettkassan omfattas av [Hi.Events Enterprise Licence](backend/ee/LICENCE) och kräver en
licens för produktionsbruk.

**Kommersiella licenser finns** om du vill ta bort attributionen eller behöver villkor anpassade för en
white label-lösning. [Licensalternativ](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Bidra

Bidrag är välkomna – se [bidragsguiden](CONTRIBUTING.md) för att komma igång. Öppna gärna ett issue eller en
diskussion innan du påbörjar större arbeten, så att vi kan enas om tillvägagångssättet. Bidragsgivare skriver under
ett [CLA](CLA.md).

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Säkerhet

Har du hittat en sårbarhet? Rapportera den privat till [security@hi.events](mailto:security@hi.events) i stället för att öppna
ett offentligt issue. Se vår [säkerhetspolicy](SECURITY.md).

<br>

## Support

📖 [Dokumentation](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Diskussioner](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

Nya funktioner och förbättringar listas på
[releasesidan](https://github.com/HiEventsDev/hi.events/releases).

<br>

<div align="center">

**[Webbplats](https://hi.events)** · **[Dokumentation](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Licenser](https://hi.events/licensing)**

Skapat med ☘️ på Irland

</div>
