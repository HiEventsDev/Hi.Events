<p align="center">
  <img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/hi-events-rainbow.png?v=1" alt="Hi.Events Logo" width="200px">
</p>
<h3 align="center">Hi.Events</h3>
<p align="center">
<a href="https://hi.events?utm_source=gh-readme">Website 🌎</a> | <a href="https://hi.events/docs">Documentation 📄</a> | <a href="https://demo.hi.events/event/1/dog-conf-2030">Demo Event 🌟</a> | <a href="https://hi.events/docs/getting-started?utm_source=gh-readme">Installation ⚙️</a>
</p>

<h3 align="center">
 Effortlesly manage events and sell tickets online.
</h3>

<div align="center">

[![Hi.Events docs](https://img.shields.io/badge/docs-hi.events-blue)](https://hi.events/docs)
[![Hi.Events licence](https://img.shields.io/badge/licence-el2)](https://github.com/HiEventsDev/hi.events/LICENCE)
[![GitHub Release](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![Run Unit Tests](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml)

[//]: # ([![Docker Pulls]&#40;https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one&#41;]&#40;https://hub.docker.com/r/daveearley/hi.events-all-in-one&#41;)

</div>

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

- 📊 **Analytics:** Gain deep insights into event performance and ticket sales.
- 🎟 **Embeddable Widget:** Easily integrate ticket sales into any website.
- 🖥 **Customizable Homepages:** Create eye-catching event pages with flexible design options.
- 🔑 **Check-In:** Easily check in attendees at the door with Hi.Events' QR code check-in tool.
- 💬 **Messaging:** Message attendees with important updates and reminders.
- 📝 **Order Forms:** Collect attendee information with tailored questions at checkout.
- 🎫 **Ticket Options:** Free, paid, donation, or tiered ticket types.
- 💸 **Promo Codes:** Highly versatile discount codes. Pre-sale access, multiple discount options.
- 💰 **Instant Payouts:** Enjoy instant payouts with seamless Stripe integration.
- 🧾 **Tax and Fee Configuration:** Add tax and fees on a per-ticket basis.
- 📦 **Data Exports:**  Export attendee and order data to XLSX or CSV.
- 💻 **REST API:** Full-featured REST API for custom integrations.
- 🔍 **SEO Tools:** Customize SEO settings for each event.
- 🛒 **Sleek Checkout:** Ensure a smooth, beautiful checkout experience.
- 🔐 **Role-Based Access:** Support for multiple user roles.
- 💻 **Online Event Support:** Offer online event instructions and links.
- ⏪ **Refund Support:** Manage full and partial refunds with ease.
- 📧 **Email Notifications:** Keep attendees informed with automated email notifications.
- 📱 **Mobile-Responsive:** Enjoy a seamless experience on any device.
- 🌐 **Multi-Language Support:** Support for multiple languages - `Coming Soon!`
- 🎉 **And much more!**

## 🚀 Getting Started

For detailed installation instructions, please refer to our [documentation](https://hi.events/docs/getting-started). For a quick start, follow these steps:

### One-Click Deployments

   [![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)

   [![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)

   [![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)

### 🐳 Local development - using Docker
> [!IMPORTANT]  
> Please ensure you have Docker and Docker Compose installed on your system. If not, you can download them from the official Docker website: [Docker](https://www.docker.com/get-started).

1. **Clone the Repository:**
   ```bash
   git clone git@github.com:HiEventsDev/hi.events.git
   ```

2. **Navigate to the Docker Directory:**
   ```bash
   cd hi.events/docker/development
   ```

3. **Run the set-up script:**
   ```bash
   ./start-dev.sh
   ```
4. **Create an account:**
    ```
    Open your browser and navigate to https://localhost:8443/auth/register.
    ```

## 📝 Change Log

Stay updated with our ongoing improvements and feature additions at our [GitHub releases page](https://github.com/HiEventsDev/hi.events/releases).

## 🤝 Contributing

We welcome contributions, suggestions, and bug reports! Before proposing a new feature or extension,
please open an issue to discuss it. We're excited to see your ideas!

## ❓ FAQ

Have questions? Our [Docs](https://hi.events/docs) has answers. If you can't find what you're looking for, feel free to reach out to us at [hello@hi.events](mailto:hello@hi.events).

## 📜 License

TLDR: Do what you like with Hi.Events, but don't remove the Hi.Events branding without a license.

Hi.Events code is licensed under the terms of the Elastic License 2.0 (ELv2), which means you can use it freely for commercial and non-commercial purposes, as long as you respect the terms of the license.

We also offer a commercial license for those who wish to white-label Hi.Events or use it in a proprietary product. For more information, please contact us at [licence@hi.events](mailto:licence@hi.events).
