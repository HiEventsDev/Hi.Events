<p align="center">
  <img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/hi-events-rainbow.png?v=1" alt="Hi.Events Logo" width="200px">
</p>
<h3 align="center">Hi.Events 🚀</h3>
<p align="center">
<a href="https://hi.events">Website</a> | <a href="https://hi.events/docs">Documentation</a> | <a href="https://demo.hi.events">Demo Event</a> | <a href="https://hi.events/docs/installation">Installation</a> | <a href="https://hi.events/docs/faq">FAQ</a>
</p>

<h3 align="center">
 Self-hosted event management and ticketing platform  🎫
</h3>

<div align="center">

![hi.events docs](https://img.shields.io/badge/docs-docs.hi.events-blue?style=for-the-badge)
![hi.events version](https://img.shields.io/badge/version-v0.0.2alpha-green?style=for-the-badge)
![hi.events licence](https://img.shields.io/badge/licence-el2?style=for-the-badge)

</div>

<hr/>

## Table of Contents 📚

- [Introduction](#introduction)
- [Features](#features)
- [Getting Started](#getting-started)
- [Change Log](#change-log)
- [Contributing](#contributing)
- [FAQ](#faq)

## Introduction

<a href="https://hi.events">Hi.Events</a> is a comprehensive, self-hosted event management and ticketing platform designed for ease of use and deployment. Perfect for event organizers who need a robust, customizable solution.

<img alt="Hi.Event self-hosted ticket selling dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/dashboard-screenshot.png"/>

## Features 🌟

<a href="https://hi.events">Hi.Events</a> is packed with features to streamline your event management and ticketing:

- 📊 **Analytics:** Gain deep insights into event performance and ticket sales.
- 🎟 **Embeddable Widget:** Easily integrate ticket sales into any website.
- 🖥 **Customizable Homepages:** Create eye-catching event pages with flexible design options.
- 🔑 **Check-In:** Manage attendees efficiently at the door.
- 💬 **Messaging:** Utilize tools for timely updates and reminders.
- 📝 **Order Forms:** Collect attendee information with tailored forms.
- 🎫 **Ticket Options:** Offer diverse ticket types — free, paid, donation, or tiered.
- 💸 **Promo Codes:** Drive sales with customizable discount codes, including pre-sale codes and multiple discount
  options.
- 💰 **Instant Payouts:** Enjoy quick payouts with seamless Stripe integration.
- 🧾 **Flexible Tax and Fee Configuration:** Tailor taxes and service fees as needed.
- 📦 **Easy Export:** Conveniently export attendee and order data.
- 🔍 **SEO Tools:** Boost your event's online visibility.
- 🛒 **Sleek Checkout:** Ensure a smooth, beautiful checkout experience.
- 🔐 **Role-Based Access:** Implement role-based permissions for team members.
- 💻 **Online Event Support:** Get tailored tools for virtual events.
- ⏪ **Refund Support:** Manage full and partial refunds with ease.
- 🌟 **Beautiful Checkout Experience:** Delight purchasers with an elegantly designed process.

## Getting Started 🚀

For detailed installation instructions, please refer to our [documentation](https://hi.events/docs/installation). For a
quick start, follow these steps:

**Prerequisites:**

> [!IMPORTANT]  
> Please ensure you have Docker and Docker Compose installed on your system. If not, you can download them from the
> official Docker website: [Docker](https://www.docker.com/get-started).

1. **Clone the Repository:**
   ```bash
   git clone git@github.com:HiEventsDev/hi.events.git
   ```

2. **Navigate to the docker Directory:**
   ```bash
    cd hi.events/docker
    ```
3. **Run the Docker Compose Command:**
   ```bash
   docker-compose up -d
   ```

4. **Migration the database:**
   ```bash
    docker-compose exec backend php artisan key:generate
    docker-compose exec backend php artisan migrate
   ```

5. **Access the Dashboard:**
    ```
    Open your browser and navigate to `http://localhost:5678` to access the Hi.Events dashboard.
    ```

## Change Log 📝

Stay updated with our ongoing improvements and feature additions at
our [GitHub releases page](https://github.com/HiEventsDev/hi.events/releases).

## Contributing 🤝

We welcome contributions, suggestions, and bug reports! Before proposing a new feature or extension, please open an
issue to discuss it. We're excited to see your ideas!

## FAQ ❓

Have questions? Our [FAQ page](https://hi.events/docs/faq) has answers. Check it out for quick help and insights.

## License 📜

Hi.Events code is licensed under the terms of the Elastic License 2.0 (ELv2), which means you can use it freely
for commercial and non-commercial purposes, as long as you respect the terms of the license.

We also offer a commercial license for those who wish white-label Hi.Events or use it in a proprietary product.
For more information, please contact us at [licence@hi.events](mailto:licence@hi.events).