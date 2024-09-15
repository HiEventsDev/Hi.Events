<div align="center">
 🌟 A star would be much appreciated! 🌟
</div>

<p align="center">
  <img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/hi-events-rainbow.png?v=1" alt="Hi.Events Logo" width="200px">
</p>


<h3 align="center">Hi.Events</h3>

<p align="center">
<a href="https://demo.hi.events/event/1/dog-conf-2030">Demo Event 🌟</a> • <a href="https://hi.events?utm_source=gh-readme">Website 🌎</a> • <a href="https://hi.events/docs">Documentation 📄</a> • <a href="https://hi.events/docs/getting-started?utm_source=gh-readme">Installation ⚙️</a>
</p>

<h3 align="center">
 Open-source event management and ticketing platform.
</h3>

<div align="center">

[![Share on AddToAny](https://img.shields.io/badge/Share%20Hi.Events-blue)](https://www.addtoany.com/share?linkurl=https://github.com/HiEventsDev/hi.events)
[![X (formerly Twitter) Follow](https://img.shields.io/twitter/follow/HiEventsTickets)](https://x.com/HiEventsTickets)
<br/>
[![Hi.Events docs](https://img.shields.io/badge/docs-hi.events-blue)](https://hi.events/docs)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![GitHub Release](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![Run Unit Tests](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml)
[![Docker Pulls](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)

</div>

<div align="center">
 <a href="https://trendshift.io/repositories/10563" target="_blank"><img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" style="width: 250px; height: 55px;" width="250" height="55"/></a>
</div>

<hr/>
<p align="center" style="text-align: center;">
<a href="README.de.md">Deutsch</a> |
<a href="README.pt.md">Português</a> |
<a href="README.fr.md">Français</a> |
<a href="README.es.md">Español</a> |
<a href="README.zh-cn.md">中文 (Zhōngwén)</a> |
<a href="README.ja.md">日本語</a>
</p>
<hr/>

## Table of Contents

- [Introduction](#-introduction)
- [Features](#-features)
- [Getting Started](#-getting-started)
- [Change Log](#-change-log)
- [Contributing](#-contributing)
- [FAQ](#-faq)

## 📚 Introduction

<a href="https://hi.events">Hi.Events</a> is a feature-rich, self-hosted event management and ticketing platform. From conferences to club nights, 
Hi.Events is designed to help you create, manage, and sell tickets for events of all sizes.

<img alt="Hi.Events self-hosted ticket selling dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/dashboard-screenshot.png"/>

## 🌟 Features

<a href="https://hi.events">Hi.Events</a> is packed with features to streamline your event management and ticketing:

- 📊 **Event Analytics:** Gain deep insights into event performance and ticket sales.
- 🎟 **Embeddable Ticket Widget:** Easily integrate ticket sales into any website.
- 🖥 **Customizable Event Homepages:** Create eye-catching event pages with flexible design options.
- 🔑 **Intuitive Check-In Tools:** Easily check in attendees at the door with Hi.Events' QR code check-in tool.
- 💬 **Event Messaging Tools:** Message attendees with important updates and reminders.
- 📝 **Custom Order Forms:** Collect attendee information with tailored questions at checkout.
- 🎫 **Multiple Ticket Types:** Free, paid, donation, or tiered ticket types.
- 💸 **Versatile Promo Codes:** Highly versatile discount codes. Pre-sale access, multiple discount options.
- 💰 **Instant Payouts:** Enjoy instant payouts with seamless Stripe integration.
- 🧾 **Tax and Fee Configuration:** Add tax and fees on a per-ticket basis.
- 📦 **Data Exports:** Export attendee and order data to XLSX or CSV.
- 💻 **REST API:** Full-featured REST API for custom integrations.
- 🔍 **SEO Tools:** Customize SEO settings for each event.
- 🛒 **Beautiful Checkout Process:** Ensure a smooth, beautiful checkout experience.
- 🔐 **Role-Based Access:** Support for multiple user roles.
- 💻 **Online Event Support:** Offer online event instructions and links.
- ⏪ **Full and Partial Refund Support:** Manage full and partial refunds with ease.
- 📧 **Email Notifications:** Keep attendees informed with automated email notifications.
- 📱 **Mobile-Responsive:** Enjoy a seamless experience on any device.
- 🌐 **Multi-Language Support:** Support for multiple languages (English, Português, Español, 中文 (Zhōngwén), Deutsch, Français)
- 🔋 **Advanced Capacity Management:** Set capacity limits across multiple ticket types.
- 🎉 **And much more!**

## 🚀 Getting Started

For detailed installation instructions, please refer to our [documentation](https://hi.events/docs/getting-started). For
a quick start, follow these steps:

### One-Click Deployments

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)

[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)

[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)

[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

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
   ```bash
   Open your browser and navigate to http://localhost:8123/auth/register.
   ```

ℹ️ Please refer to the [getting started guide](https://hi.events/docs/getting-started) for other installation methods, and
for setting up a production or local development environment.

## 💜 Support Hi.Events

If you find Hi.Events useful, it would be massively appreciated if you made a small donation to help support the project.

<a href="https://www.buymeacoffee.com/hi.events" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me A Coffee" style="height: 60px !important;width: 217px !important;" ></a>
<br/>
or
<a href="https://github.com/sponsors/HiEventsDev" target="_blank"> Sponsor on GitHub</a>

## 📝 Change Log

Stay updated with our ongoing improvements and feature additions at
our [GitHub releases page](https://github.com/HiEventsDev/hi.events/releases).

## 🤝 Contributing

We welcome contributions, suggestions, and bug reports! Before proposing a new feature or extension,
please open an issue to discuss it.

## ❓ FAQ

Have questions? Our [Docs](https://hi.events/docs) have answers. If you can't find what you're looking for, feel free to
reach out to us at [hello@hi.events](mailto:hello@hi.events).

## 📜 License

Hi.Events is licensed under the terms of the [AGPL-3.0](https://github.com/HiEventsDev/hi.events/blob/main/LICENCE) license.

For more licensing information, including commercial licencing options, please visit our licensing page [here](https://hi.events/licensing).
