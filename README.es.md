<div align="center">

<img src=".github/assets/readme-banner.png" alt="Hi.Events - Open-source event ticketing and management platform" width="100%">

# Hi.Events

### Plataforma de código abierto para la venta de entradas y la gestión de eventos

Vende entradas online para conferencias, eventos de ocio nocturno, conciertos, sesiones de club, talleres y festivales.<br>
En tu propio servidor o en la nube. Tus eventos, tu marca, tus datos.

[Prueba Cloud →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Demo en vivo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Documentación](https://hi.events/docs?utm_source=gh-readme) · [Enterprise y licencias](https://hi.events/licensing?utm_source=gh-readme) · [Sitio web](https://hi.events?utm_source=gh-readme)

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
> **Ya está aquí Hi.Events 2.0**, con eventos recurrentes, asientos reservados, taquilla, carteles y programación, inicio
> de sesión único, permisos de equipo y claves de API. [Descubre las novedades →](https://github.com/HiEventsDev/hi.events/releases)

<br>

<img alt="Hi.Events event dashboard on desktop and an event page on mobile" src=".github/assets/readme-showcase.png" width="100%"/>

<br>

## ¿Por qué Hi.Events?

La mayoría de las plataformas de venta de entradas cobran una comisión por cada entrada y se quedan con los datos de tus clientes. **Hi.Events es una
alternativa de código abierto a Eventbrite, Tickettailor, Dice.fm y otras plataformas de venta de entradas** que te da el control total de tu
marca, tu proceso de pago y tus datos.

Miles de organizadores de eventos de todo el mundo confían en Hi.Events: desde promotores de ocio nocturno y festivales hasta recintos, colectivos
y organizadores de conferencias. Gestiónalo tú mismo o deja que lo hagamos por ti en Hi.Events Cloud.

<br>

## Funcionalidades

**📅 Eventos**
- Eventos únicos y recurrentes, con precios, capacidad y horarios por fecha
- Duplica un evento con un clic
- Recintos guardados y eventos online
- Diseñador de la página del evento y páginas de organizador con tu marca
- Páginas de cartel y programación

**🎟️ Entradas y productos**
- Entradas de pago, gratuitas, con donación y por tramos
- Asientos reservados <sup>Enterprise</sup>
- Complementos y merchandising
- Entradas ocultas que se desbloquean con códigos promocionales
- Límites por pedido y periodos de venta programados
- Capacidad compartida y listas de espera automáticas

**🛒 Ventas**
- Inserciones personalizables para tu web: widgets de entradas, botones, listas de eventos, carteles y programación
- Códigos promocionales y descuentos
- Enlaces de seguimiento para promotores y afiliados
- Preguntas de registro personalizadas
- Consentimiento de marketing en el pago
- Seguimiento con Meta, TikTok, Google Analytics y Tag Manager

**💳 Pagos**
- Cobros directos en tu propia cuenta de Stripe
- Impuestos y comisiones, incluidos en el precio o añadidos al pagar
- Facturación y pagos offline
- Reembolsos totales y parciales

**🚪 Asistentes y acceso**
- Taquilla para la venta en puerta <sup>Enterprise</sup>
- Check-in con la cámara del móvil o un lector USB
- Listas de check-in compartibles con recuentos en tiempo real
- Mensajes a asistentes y plantillas de correo personalizadas
- Entradas en PDF con tu marca
- Asistentes añadidos manualmente
- Informes de ventas, impuestos y códigos promocionales

<br>

## Pensado para organizaciones

**🔐 Seguridad y acceso**
- Inicio de sesión único
- Autenticación de dos factores
- Roles y permisos de equipo
- Varios organizadores en una misma cuenta

**🔌 Integraciones**
- REST API y claves de API
- Webhooks
- Exportación a CSV y XLSX

**🗄️ Tus datos**
- Alójalo en tu servidor con Docker
- Eliminación y anonimización de cuentas
- Disponible en 20 idiomas

**✅ Probado**
- Un completo conjunto de pruebas se ejecuta con cada cambio

<br>

## Inicio rápido

Desarrollado con **Laravel 13** (PHP >=8.3) · **React 19** con SSR · **TypeScript** · **PostgreSQL** · **Redis** · **Docker**.

### Despliegue con un clic

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
> **Si usas Windows:** consulta `./docker/all-in-one/README.md` para ver cómo generar las claves.

Abre `http://localhost:8123` y crea tu cuenta.

📖 [Guía de instalación completa](https://hi.events/docs/getting-started?utm_source=gh-readme)

> [!IMPORTANT]
> **¿Actualizas desde la 1.x?** Lee las [notas de la versión](https://github.com/HiEventsDev/hi.events/releases) antes de actualizar.

### REST API

Crea claves de API en **Ajustes → Claves de API**, donde también encontrarás la referencia de la API.

<br>

## Idiomas

English, Deutsch, Français, Italiano, Nederlands, Español, Português, Português (Brasil),
Polski, Slovenčina, Magyar, Svenska, Suomi, Ελληνικά, Türkçe, Tiếng Việt, 简体中文, 繁體中文, 日本語 y 한국어.

Las correcciones y los nuevos idiomas son bienvenidos: consulta la [guía de contribución](CONTRIBUTING.md).

<br>

## Hi.Events Cloud

¿Prefieres no alojarlo tú mismo? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** es la versión totalmente
gestionada, operada por el mismo equipo que desarrolla Hi.Events.

[Empieza ahora →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Licencias

Hi.Events se distribuye bajo la licencia **AGPL-3.0 con términos adicionales**. Estos términos exigen mantener la atribución "Powered by Hi.Events"
en las páginas y los correos electrónicos que genera el software; consulta [LICENCE](LICENCE) para ver el texto exacto.

Los asientos reservados y la taquilla están cubiertos por la [Hi.Events Enterprise Licence](backend/ee/LICENCE) y requieren una
licencia para su uso en producción.

**Hay licencias comerciales disponibles** si quieres eliminar la atribución o necesitas condiciones adaptadas a un despliegue de
marca blanca. [Opciones de licencia](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Contribuir

Las contribuciones son bienvenidas: consulta la [guía de contribución](CONTRIBUTING.md) para empezar. Antes de abordar un cambio importante, abre una
incidencia o una discusión para que podamos acordar el enfoque. Los colaboradores firman
un [CLA](CLA.md).

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Seguridad

¿Has encontrado una vulnerabilidad? Comunícala de forma privada a [security@hi.events](mailto:security@hi.events) en lugar de abrir
una incidencia pública. Consulta nuestra [política de seguridad](SECURITY.md).

<br>

## Soporte

📖 [Documentación](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [Incidencias en GitHub](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Discusiones](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

Las nuevas funcionalidades y mejoras se publican en
la [página de versiones](https://github.com/HiEventsDev/hi.events/releases).

<br>

<div align="center">

**[Sitio web](https://hi.events)** · **[Documentación](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Licencias](https://hi.events/licensing)**

Hecho con ☘️ en Irlanda

</div>
