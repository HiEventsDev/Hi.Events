<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - Open Source Event Ticketing Platform" width="100%">

# Hi.Events

### Open-Source-Plattform für Event-Ticketing und -Management

Verkaufen Sie Tickets online für Konferenzen, Nachtleben-Events, Konzerte, Club-Nights, Workshops und Festivals.  
Self-hosted oder Cloud. Ihre Events, Ihre Marke, Ihre Daten.

[Cloud testen →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Live-Demo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Dokumentation](https://hi.events/docs?utm_source=gh-readme) · [Website](https://hi.events?utm_source=gh-readme)

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

## Warum Hi.Events?

Die meisten Ticketing-Plattformen erheben Gebühren pro Ticket und sperren Ihre Daten in ihr Ökosystem. **Hi.Events ist
eine moderne, quelloffene Alternative zu Eventbrite, Tickettailor, Dice.fm und anderen Ticketing-Plattformen** für
Veranstalter, die volle Kontrolle über Branding, Checkout, Daten und Infrastruktur wollen.

Weltweit von Tausenden Veranstaltern eingesetzt – von Nachtleben-Promotern und Festivals bis zu Veranstaltungsorten,
Community-Gruppen und Konferenz-Gastgebern. Hosten Sie es selbst, oder überlassen Sie den Betrieb uns in der Hi.Events
Cloud.

<br>

<img alt="Hi.Events Dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Features

**🎟️ Ticketing & Verkauf** – kostenlose, bezahlte, Spenden- und gestaffelte Tickets · wiederkehrende Events und Events
mit mehreren Terminen · Wartelisten bei ausverkauften Terminen · Promo-Codes, inklusive Promo-gesteuerter und
versteckter Tickets · Produkt-Add-ons und -Kategorien · Steuer-, Gebühren- und Kapazitätsverwaltung

**🎨 Branding & Anpassung** – Event-Homepage-Designer für Titelbild, Farben und Typografie · gebrandete
Veranstalter-Homepage · anpassbare PDF-Tickets · einbettbares Ticket-Widget · SEO-Metadaten-Steuerung

**👥 Teilnehmerverwaltung** – individuelle Checkout-Fragen · erweiterte Suche, Filterung und CSV/XLSX-Export ·
vollständige und teilweise Rückerstattungen · Massen-Nachrichten · QR-Check-in mit Scan-Logs und zugriffskontrollierten
Check-in-Listen

**📊 Analytics & Wachstum** – Verkaufs-Dashboard · Affiliate-Tracking · Berichte zu Tagesumsatz, Produktverkäufen,
Promo-Codes, Umsatz und Steuern · ausgehende Webhooks

**⚙️ Betrieb** – Multi-User-Rollen · Stripe-Connect-Zahlungen · Offline-Zahlungsmethoden · automatische
Rechnungsstellung · Online- und Präsenz-Events · Mehrsprachigkeit · vollständige REST-API mit
[interaktiver OpenAPI-Dokumentation](#rest-api)

<br>

## Schnellstart

Entwickelt mit **Laravel 13** (PHP >=8.3) · **React 19** mit SSR · **TypeScript** · **PostgreSQL** · **Redis** ·
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

# Schlüssel generieren (Linux/macOS)
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Windows-Benutzer:** Siehe `./docker/all-in-one/README.md` für Anweisungen zur Schlüsselgenerierung.

Öffnen Sie `http://localhost:8123` und erstellen Sie Ihr Konto.

📖 [Vollständige Installationsanleitung](https://hi.events/docs/getting-started?utm_source=gh-readme)

### REST API

Hi.Events bringt eine dokumentierte REST-API mit. Setzen Sie `API_DOCS_ENABLED=true` in Ihrer `.env`, um die interaktive
OpenAPI-Dokumentation unter `/docs/api` auf Ihrer eigenen Instanz bereitzustellen, oder exportieren Sie die Spezifikation
mit:

```bash
php artisan scramble:export
```

<br>

## Hi.Events Cloud

Möchten Sie nicht selbst hosten? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** ist die
vollständig verwaltete Version dieses Repositorys – ohne Setup, mit automatischen Updates und verwalteter Infrastruktur,
betrieben von dem Team, das Hi.Events entwickelt.

[Jetzt starten →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Lizenzierung

Hi.Events ist unter **AGPL-3.0 mit zusätzlichen Bedingungen** lizenziert. Die zusätzlichen Bedingungen verlangen, dass
der Hinweis „Powered by Hi.Events“ auf Seiten und in E-Mails, die von der Software erzeugt werden, erhalten bleibt – den
genauen Wortlaut finden Sie in der [LICENCE](LICENCE).

**Kommerzielle Lizenzen sind verfügbar**, wenn Sie den Hinweis entfernen möchten oder Bedingungen für ein
White-Label-Deployment benötigen. [Lizenzierungsoptionen](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Mitwirken

Beiträge sind willkommen – der [Beitragsleitfaden](CONTRIBUTING.md) hilft beim Einstieg. Bitte eröffnen Sie ein Issue
oder eine Discussion, bevor Sie größere Arbeiten beginnen, damit wir das Vorgehen abstimmen können. Mitwirkende
unterzeichnen ein [CLA](CLA.md).

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Sicherheit

Eine Schwachstelle gefunden? Bitte melden Sie sie vertraulich an [security@hi.events](mailto:security@hi.events), statt
ein öffentliches Issue zu eröffnen. Siehe unsere [Sicherheitsrichtlinie](SECURITY.md).

<br>

## Support

📖 [Dokumentation](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Discussions](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

Neue Features und Verbesserungen finden Sie auf
der [Releases-Seite](https://github.com/HiEventsDev/hi.events/releases).

<br>

<div align="center">

**[Website](https://hi.events)** · **[Dokumentation](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Lizenzierung](https://hi.events/licensing)**

Made with ☘️ in Ireland

</div>
