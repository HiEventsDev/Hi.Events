<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - Plataforma de venta de entradas de eventos de código abierto" width="100%">

# Hi.Events

### Plataforma de gestión y venta de entradas de eventos de código abierto

Vende entradas online para conferencias, eventos nocturnos, conciertos, fiestas, talleres y festivales.  
Autohospedado o en la nube. Tus eventos, tu marca, tus datos.

[Probar en la Nube →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Demo en Vivo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Documentación](https://hi.events/docs?utm_source=gh-readme) · [Sitio Web](https://hi.events?utm_source=gh-readme)

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

## ¿Por qué Hi.Events?

La mayoría de las plataformas de venta de entradas cobran comisiones por entrada y encierran tus datos en su
ecosistema. **Hi.Events es una alternativa moderna y de código abierto a Eventbrite, Tickettailor, Dice.fm y otras
plataformas de ticketing** para organizadores que quieren control total sobre su marca, su proceso de compra, sus datos
y su infraestructura.

Utilizado por miles de organizadores en todo el mundo: desde promotores de ocio nocturno y festivales hasta salas,
grupos comunitarios y organizadores de conferencias. Hospédalo tú mismo o deja que nosotros lo gestionemos en Hi.Events
Cloud.

<br>

<img alt="Hi.Events Dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Funcionalidades

**🎟️ Venta de entradas** — entradas gratuitas, de pago, de donación y por niveles · eventos recurrentes y con varias
fechas · listas de espera cuando se agotan · códigos promocionales, incluidas entradas ocultas y restringidas por
código · complementos y categorías de productos · gestión de impuestos, comisiones y aforo

**🎨 Marca y personalización** — diseñador de la página del evento para imagen de portada, colores y tipografía · página
del organizador con tu marca · entradas PDF personalizables · widget de venta integrable · control de metadatos SEO

**👥 Gestión de asistentes** — preguntas personalizadas en el proceso de compra · búsqueda, filtrado y exportación
CSV/XLSX avanzados · reembolsos totales y parciales · mensajería masiva · check-in con código QR con registros de
escaneo y listas de check-in con acceso controlado

**📊 Análisis y crecimiento** — panel de ventas · seguimiento de afiliados · informes de ventas diarias, ventas por
producto, códigos promocionales, ingresos e impuestos · webhooks salientes

**⚙️ Operaciones** — roles multiusuario · pagos con Stripe Connect · métodos de pago offline · facturación automática ·
eventos online y presenciales · soporte multiidioma · API REST completa con
[documentación OpenAPI interactiva](#rest-api)

<br>

## Inicio Rápido

Construido con **Laravel 13** (PHP >=8.3) · **React 19** con SSR · **TypeScript** · **PostgreSQL** · **Redis** ·
**Docker**.

### Despliegue con un Clic

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# Generar claves (Linux/macOS)
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Usuarios de Windows:** Consulta `./docker/all-in-one/README.md` para las instrucciones de generación de claves.

Abre `http://localhost:8123` y crea tu cuenta.

📖 [Guía de instalación completa](https://hi.events/docs/getting-started?utm_source=gh-readme)

### REST API

Hi.Events incluye una API REST documentada. Configura `API_DOCS_ENABLED=true` en tu `.env` para servir la documentación
OpenAPI interactiva en `/docs/api` de tu propia instancia, o exporta la especificación con:

```bash
php artisan scramble:export
```

<br>

## Hi.Events Cloud

¿Prefieres no autohospedar? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** es la
versión totalmente gestionada de este repositorio: sin configuración, con actualizaciones automáticas e infraestructura
gestionada por el equipo que desarrolla Hi.Events.

[Empezar →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Licencia

Hi.Events se distribuye bajo **AGPL-3.0 con términos adicionales**. Esos términos adicionales exigen mantener la
atribución «Powered by Hi.Events» en las páginas y correos generados por el software; consulta [LICENCE](LICENCE) para
el texto exacto.

**Hay licencias comerciales disponibles** si quieres eliminar la atribución o necesitas términos adecuados para un
despliegue de marca blanca. [Opciones de licencia](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Contribuir

Las contribuciones son bienvenidas: consulta la [guía de contribución](CONTRIBUTING.md) para empezar. Abre una issue o
una discusión antes de comenzar un trabajo importante para que podamos alinear el enfoque. Quienes contribuyen firman un
[CLA](CLA.md).

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Seguridad

¿Has encontrado una vulnerabilidad? Repórtala de forma privada a [security@hi.events](mailto:security@hi.events) en
lugar de abrir una issue pública. Consulta nuestra [política de seguridad](SECURITY.md).

<br>

## Soporte

📖 [Documentación](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Discusiones](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

Las nuevas funcionalidades y mejoras se publican en
la [página de releases](https://github.com/HiEventsDev/hi.events/releases).

<br>

<div align="center">

**[Sitio Web](https://hi.events)** · **[Documentación](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Licencias](https://hi.events/licensing)**

Made with ☘️ in Ireland

</div>
