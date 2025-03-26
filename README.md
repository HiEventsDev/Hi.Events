<div align="center">
<div align="center">

💖 **Found Hi.Events helpful?**  
⭐ Please consider giving us a star to support the project! ⭐

</div>

<p>
  <img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/hi-events-rainbow.png?v=1" alt="Hi.Events Logo" width="200px">
</p>

<h1>Hi.Events</h1>
<h3>Open-source event management and ticketing platform to sell tickets online for events of all sizes</h3>

[![Share on AddToAny](https://img.shields.io/badge/Share%20Hi.Events-blue)](https://www.addtoany.com/share?linkurl=https://github.com/HiEventsDev/hi.events)
[![X (formerly Twitter) Follow](https://img.shields.io/twitter/follow/HiEventsTickets)](https://x.com/HiEventsTickets)
[![Hi.Events docs](https://img.shields.io/badge/docs-hi.events-blue)](https://hi.events/docs)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![GitHub Release](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![Run Unit Tests](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml)
[![Docker Pulls](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)

<p>
<a href="https://app.hi.events/auth/register?utm_source=gh-readme&utm_content=try-cloud-link">Try Cloud ☁️</a> •
<a href="https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme&utm_content=demo-link">Demo Event 🌟</a> • 
<a href="https://hi.events?utm_source=gh-readme&utm_content=website-link">Website 🌎</a> • 
<a href="https://hi.events/docs?utm_source=gh-readme&utm_content=documentation-link">Documentation 📄</a> • 
<a href="https://hi.events/docs/getting-started?utm_source=gh-readme&utm_content=installation=link">Installation ⚙️</a>
</p>

<a href="https://trendshift.io/repositories/10563" target="_blank"><img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" style="width: 250px; height: 55px;" width="250" height="55"/></a>
</div>

<hr/>
<p align="center">
<a href="README.de.md">Deutsch</a> |
<a href="README.pt.md">Português</a> |
<a href="README.fr.md">Français</a> |
<a href="README.es.md">Español</a> |
<a href="README.zh-cn.md">中文 (Zhōngwén)</a> |
<a href="README.ja.md">日本語</a>
</p>
<hr/>

## 📚 Introduction

<a href="https://hi.events">Hi.Events</a> is a feature-rich, self-hosted event management and ticketing platform that helps you sell tickets online for all types of events. From conferences and workshops to club nights and concerts, Hi.Events provides everything you need to create, manage, and monetize your events with ease.

<img alt="Hi.Events self-hosted ticket selling dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-readme-screenshot.png"/>
<div align="center">
<caption>Generated using <a href="https://screenshot.rocks?utm_source=hi.events-readme">Screenshot Rocks</a></caption>
</div>

## ⚡ Quick Deploy

Get started in minutes with our one-click deployment options:

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

## 🌟 Key Features

<a href="https://hi.events">Hi.Events</a> offers comprehensive tools to streamline your event management:

### 🎟 Ticketing & Sales
- **Multiple Ticket Types:** Create free, paid, donation-based, and tiered tickets
- **Capacity Management:** Set limits per event or ticket type
- **Promo Codes & Discounts:** Drive early sales with special offers
- **Product Upsells:** Sell merchandise and add-ons alongside tickets
- **Custom Pricing:** Apply taxes and fees per product or entire order

### 🏆 Event Management
- **Real-time Dashboard:** Track sales, revenue, and attendee metrics
- **Visual Page Editor:** Design beautiful event pages with live preview
- **Website Integration:** Embed ticketing widgets on your existing site
- **SEO Optimization:** Customize metadata for better search visibility
- **Offline Event Support:** Provide location details and instructions

### 📱 Attendee Experience
- **Custom Registration Forms:** Collect exactly the information you need
- **QR Code Check-In:** Fast, mobile-friendly entry verification
- **Multi-language Support:** Reach global audiences with localized interfaces
- **Bulk Communication:** Send targeted messages to specific ticket holders
- **Refund Management:** Process full or partial refunds when needed

### 🔧 For Organizers
- **Team Collaboration:** Role-based access for staff members
- **Webhook Integration:** Connect with Zapier, IFTTT, Make, or your CRM
- **Stripe Connect:** Receive instant payouts for ticket sales
- **Comprehensive API:** Build custom integrations with full API access
- **Advanced Reporting:** Generate sales, tax, and usage reports

## 🚀 Getting Started

### 🐳 Quick Start with Docker

> [!IMPORTANT]  
> Please ensure you have Docker and Docker Compose installed on your system. If not, you can download them from the
> official Docker website: [Docker](https://www.docker.com/get-started).

1. **Clone the Repository:**
   ```bash
   git clone git@github.com:HiEventsDev/hi.events.git
   ```

2. **Navigate to the Docker Directory:**
   ```bash
   cd hi.events/docker/all-in-one
   ```

3. **Generate the `APP_KEY` and `JWT_SECRET`**

   Generate the keys using the following commands:

   **Unix/Linux/MacOS:**
   ```bash
   echo base64:$(openssl rand -base64 32)  # For APP_KEY
   openssl rand -base64 32                 # For JWT_SECRET
   ```

   **Windows:**
   Check the instructions in *./docker/all-in-one/README.md* for generating the keys on Windows.

   Add the generated values to the `.env` file located in `./docker/all-in-one/.env`:

4. **Start the Docker Containers:**
   ```bash
   docker compose up -d
   ```
5. **Create an account:**
   ```
   Open your browser and navigate to http://localhost:8123/auth/register
   ```

ℹ️ For detailed setup instructions including production deployment, please refer to our [getting started guide](https://hi.events/docs/getting-started).

## 💜 Sponsors

### Support the Project

If you find Hi.Events valuable for your organization, please consider supporting ongoing development:

<a href="https://www.buymeacoffee.com/hi.events" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me A Coffee" style="height: 60px !important;width: 217px !important;"></a>

Or support us on: <a href="https://github.com/sponsors/HiEventsDev" target="_blank">GitHub Sponsors</a> | <a href="https://opencollective.com/hievents" target="_blank">Open Collective</a>

## 📝 Change Log

Stay updated with our latest features and improvements on our [GitHub releases page](https://github.com/HiEventsDev/hi.events/releases).

## 🤝 Contributing

We welcome contributions from the community! Please see our [contributing guidelines](CONTRIBUTING.md) for details on how to get involved.

## ❓ FAQ

Have questions? Our [documentation](https://hi.events/docs?utm_source=gh-readme&utm_content=faq-docs-link) has answers. For additional support, contact us at [hello@hi.events](mailto:hello@hi.events).

## 📜 License

Hi.Events is licensed under the [AGPL-3.0](https://github.com/HiEventsDev/hi.events/blob/main/LICENCE) license.

For more licensing information, including commercial licencing options, please visit our licensing page [here](https://hi.events/licensing).
