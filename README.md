<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - Open Source Event Ticketing Platform" width="100%">

# Hi.Events

### Open-source event ticketing and management platform

Sell tickets online for conferences, nightlife events, concerts, club nights, workshops, and festivals.  
Self-hosted or cloud. Your events, your brand, your data.

[Try Cloud →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Live Demo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Documentation](https://hi.events/docs?utm_source=gh-readme) · [Website](https://hi.events?utm_source=gh-readme)

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![GitHub Release](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![Run Unit Tests](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml)
[![Docker Pulls](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)

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

Built for nightlife promoters, festival organizers, venues, community groups, and conference hosts.

<br>

<img alt="Hi.Events Dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Features

<table>
<tr>
<td width="50%" valign="top">

### 🎟️ Ticketing & Sales

- Flexible ticket types (free, paid, donation, tiered)
- Hidden and locked tickets behind promo codes
- Promo codes and pre-sale access
- Product add-ons (merch, upgrades, extras)
- Product categories for organization
- Full tax and fee support (VAT, service fees)
- Capacity management and shared limits

</td>
<td width="50%" valign="top">

### 🎨 Branding & Customization

- Beautiful, conversion-optimized checkout
- Customizable PDF ticket designs
- Branded organizer homepage
- Drag-and-drop event page builder
- Embeddable ticket widget
- SEO tools (meta tags, Open Graph)

</td>
</tr>
<tr>
<td width="50%" valign="top">

### 👥 Attendee Management

- Custom checkout questions
- Advanced search, filtering, and export (CSV/XLSX)
- Full and partial refunds
- Bulk messaging by ticket type
- QR code check-in with scan logs
- Access-controlled check-in lists

</td>
<td width="50%" valign="top">

### 📊 Analytics & Growth

- Real-time sales dashboard
- Affiliate and referral tracking
- Advanced reporting (sales, tax, promos)
- Webhooks (Zapier, Make, CRMs)

</td>
</tr>
<tr>
<td colspan="2" valign="top">

### ⚙️ Operations

Multi-user roles and permissions · Stripe Connect instant payouts · Offline payment methods · Offline event support ·
Automatic invoicing · Event archive · Multi-language support · Full REST API

</td>
</tr>
</table>

<br>

## Compare

| Feature                          | Hi.Events | Eventbrite | Tickettailor | Dice    |
|:---------------------------------|:----------|:-----------|:-------------|:--------|
| Self-hosted option               | ✅         | ❌          | ❌            | ❌       |
| Open source                      | ✅         | ❌          | ❌            | ❌       |
| No per-ticket fees (self-hosted) | ✅         | ❌          | ❌            | ❌       |
| Full custom branding             | ✅         | Limited    | ✅            | Limited |
| Affiliate tracking               | ✅         | ✅          | ❌            | ❌       |
| API access                       | ✅         | ✅          | ✅            | Limited |
| Own your data                    | ✅         | ❌          | ❌            | ❌       |

<br>

## Quick Start

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

<br>

## Hi.Events Cloud

Prefer not to self-host? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** is a fully
managed option with zero setup, automatic updates, and managed infrastructure.

[Get started →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Contributing

We welcome contributions. See the [contributing guide](CONTRIBUTING.md) for details.

<br>

## Support

📖 [Documentation](https://hi.events/docs?utm_source=gh-readme) · 📧 [hello@hi.events](mailto:hello@hi.events) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues)

<br>

## Changelog

Stay updated with new features and improvements on
the [releases page](https://github.com/HiEventsDev/hi.events/releases).

<br>

## License

Hi.Events is licensed under **AGPL-3.0 with additional terms**. Commercial licensing
available. [Learn more](https://hi.events/licensing).

<br>

<div align="center">

**[Website](https://hi.events)** · **[Documentation](https://hi.events/docs)** · *
*[Twitter/X](https://x.com/HiEventsTickets)**

Made with ☘️ in Ireland

</div>
