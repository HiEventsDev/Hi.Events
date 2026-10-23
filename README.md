<div align="center">

<img src=".github/assets/readme-banner.png" alt="Hi.Events - Open-source event ticketing and management platform" width="100%">

# Hi.Events

### Open-source event ticketing and management platform

Sell tickets online for conferences, nightlife events, concerts, club nights, workshops, and festivals.<br>
Self-hosted or cloud. Your events, your brand, your data.

[Try Cloud →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Live Demo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Documentation](https://hi.events/docs?utm_source=gh-readme) · [Enterprise & licensing](https://hi.events/licensing?utm_source=gh-readme) · [Website](https://hi.events?utm_source=gh-readme)

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
> **Hi.Events 2.0 is here** with recurring events, reserved seating, a box office, lineups and schedules, single
> sign-on, team permissions and API keys. [See what's new →](https://github.com/HiEventsDev/hi.events/releases)

<br>

<img alt="Hi.Events event dashboard on desktop and an event page on mobile" src=".github/assets/readme-showcase.png" width="100%"/>

<br>

## Why Hi.Events?

Most ticketing platforms take a fee on every ticket and keep your customer data. **Hi.Events is an open-source
alternative to Eventbrite, Tickettailor, Dice.fm and other ticketing platforms** that gives you full control over your
branding, checkout and data.

Trusted by thousands of event organizers worldwide — from nightlife promoters and festivals to venues, community groups,
and conference hosts. Run it yourself, or let us run it for you on Hi.Events Cloud.

<br>

## Features

**📅 Events**
- One-off and recurring events, with per-date prices, capacity and times
- Duplicate an event in one click
- Saved venues and online events
- Event homepage designer and branded organizer pages
- Lineup and schedule pages

**🎟️ Tickets & products**
- Paid, free, donation and tiered tickets
- Reserved seating <sup>Enterprise</sup>
- Add-ons and merchandise
- Hidden tickets unlocked by promo codes
- Per-order limits and scheduled sale windows
- Shared capacity and automatic waitlists

**🛒 Selling**
- Customisable embeds for your own site: ticket widgets, buttons, event lists, lineups and schedules
- Promo codes and discounts
- Tracking links for promoters and affiliates
- Custom registration questions
- Marketing opt-in at checkout
- Meta, TikTok, Google Analytics and Tag Manager tracking

**💳 Payments**
- Payouts to your own Stripe account
- Taxes and fees, included or added at checkout
- Invoicing and offline payments
- Full and partial refunds

**🚪 Attendees & the door**
- Box office for door sales <sup>Enterprise</sup>
- Check-in by phone camera or USB scanner
- Shareable check-in lists with live counts
- Attendee messaging and custom email templates
- Branded PDF tickets
- Manually added attendees
- Sales, tax and promo code reports

<br>

## Built for organisations

**🔐 Security & access**
- Single sign-on
- Two-factor authentication
- Team roles and permissions
- Multiple organizers under one account

**🔌 Integrations**
- REST API and API keys
- Webhooks
- CSV and XLSX exports

**🗄️ Your data**
- Self-host with Docker
- Account deletion and anonymisation
- Available in 20 languages

**✅ Tested**
- A comprehensive test suite is run on every change

<br>

## Quick Start

Built with **Laravel 13** (PHP >=8.3) · **React 19** with SSR · **TypeScript** · **PostgreSQL** · **Redis** · **Docker**.

### One-Click Deploy

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
> **Windows users:** See `./docker/all-in-one/README.md` for key generation instructions.

Open `http://localhost:8123` and create your account.

📖 [Full installation guide](https://hi.events/docs/getting-started?utm_source=gh-readme)

> [!IMPORTANT]
> **Upgrading from 1.x?** Read the [release notes](https://github.com/HiEventsDev/hi.events/releases) before upgrading.

### REST API

Create API keys under **Settings → API keys**, where you'll also find the API reference.

<br>

## Languages

English, Deutsch, Français, Italiano, Nederlands, Español, Português, Português (Brasil),
Polski, Slovenčina, Magyar, Svenska, Suomi, Ελληνικά, Türkçe, Tiếng Việt, 简体中文, 繁體中文, 日本語 and 한국어.

Corrections and new languages are welcome — see the [contributing guide](CONTRIBUTING.md).

<br>

## Hi.Events Cloud

Prefer not to self-host? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** is the fully
managed version, run by the team that builds Hi.Events.

[Get started →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Licensing

Hi.Events is licensed under **AGPL-3.0 with additional terms**. The additional terms require the "Powered by Hi.Events"
attribution to be retained on pages and emails generated by the software — see [LICENCE](LICENCE) for the exact wording.

Reserved seating and the box office are covered by the [Hi.Events Enterprise Licence](backend/ee/LICENCE) and need a
licence for production use.

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
