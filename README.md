<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - Open Source Event Ticketing Platform" width="100%">

# Hi.Events

### Open-source event ticketing and management platform

Sell tickets online for conferences, nightlife events, concerts, club nights, workshops, and festivals.  
Self-hosted or cloud. Your events, your brand, your data.

[Try Cloud →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Live Demo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Documentation](https://hi.events/docs?utm_source=gh-readme) · [Website](https://hi.events?utm_source=gh-readme)

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

## Why Hi.Events?

Most ticketing platforms charge per-ticket fees and lock your data into their ecosystem. **Hi.Events is a modern,
open-source alternative to Eventbrite, Tickettailor, Dice.fm, and other ticketing platforms** for organizers who want
full control over branding, checkout, data, and infrastructure.

Trusted by thousands of event organizers worldwide — from nightlife promoters and festivals to venues, community groups,
and conference hosts. Run it yourself, or let us run it for you on Hi.Events Cloud.

<br>

<img alt="Hi.Events Dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Features

**🎟️ Ticketing & sales** — free, paid, donation and tiered tickets · recurring and multi-date events · sold-out
waitlists · promo codes, including promo-gated and hidden tickets · product add-ons and categories · tax, fee and
capacity management

**🎨 Branding & customization** — event homepage designer for cover image, colors and typography · branded organizer
homepage · customizable PDF tickets · embeddable ticket widget · SEO metadata controls

**👥 Attendee management** — custom checkout questions · advanced search, filtering and CSV/XLSX export · full and
partial refunds · bulk messaging · QR check-in with scan logs and access-controlled check-in lists

**📊 Analytics & growth** — sales dashboard · affiliate tracking · daily sales, product sales, promo code, revenue and
tax reports · outgoing webhooks

**⚙️ Operations** — multi-user roles · Stripe Connect payments · offline payment methods · automatic
invoicing · online and in-person events · multi-language support · full REST API with
[interactive OpenAPI docs](#rest-api)

<br>

## Quick Start

Built with **Laravel 13** (PHP >=8.3) · **React 19** with SSR · **TypeScript** · **PostgreSQL** · **Redis** · **Docker**.

### One-Click Deploy

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# Generate keys (Linux/macOS)
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Windows users:** See `./docker/all-in-one/README.md` for key generation instructions.

Open `http://localhost:8123` and create your account.

📖 [Full installation guide](https://hi.events/docs/getting-started?utm_source=gh-readme)

### REST API

Hi.Events ships a documented REST API. Set `API_DOCS_ENABLED=true` in your `.env` to serve interactive OpenAPI
documentation at `/docs/api` on your own instance, or export the spec with:

```bash
php artisan scramble:export
```

<br>

## Hi.Events Cloud

Prefer not to self-host? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** is the fully
managed version of this repository — zero setup, automatic updates and managed infrastructure, run by the team that
builds Hi.Events.

[Get started →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Licensing

Hi.Events is licensed under **AGPL-3.0 with additional terms**. The additional terms require the "Powered by Hi.Events"
attribution to be retained on pages and emails generated by the software — see [LICENCE](LICENCE) for the exact wording.

**Commercial licences are available** if you'd like to remove the attribution or need terms that suit a white-labelled
deployment. [Licensing options](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Contributing

Contributions are welcome — see the [contributing guide](CONTRIBUTING.md) to get started. Please open an issue or
discussion before starting significant work so we can align on the approach. Contributors sign
a [CLA](CLA.md).

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Security

Found a vulnerability? Please report it privately to [security@hi.events](mailto:security@hi.events) rather than opening
a public issue. See our [security policy](SECURITY.md).

<br>

## Support

📖 [Documentation](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Discussions](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

New features and improvements are listed on
the [releases page](https://github.com/HiEventsDev/hi.events/releases).

<br>

<div align="center">

**[Website](https://hi.events)** · **[Documentation](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Licensing](https://hi.events/licensing)**

Made with ☘️ in Ireland

</div>
