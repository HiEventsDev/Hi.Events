<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - Plataforma de venta de entradas de eventos de código abierto" width="100%">

# Hi.Events

### Plataforma de gestión y venta de entradas de eventos de código abierto

Vende entradas online para conferencias, eventos nocturnos, conciertos, fiestas, talleres y festivales.
Autohospedado o en la nube. Tus eventos, tu marca, tus datos.

[Probar en la Nube →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Demo en Vivo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Documentación](https://hi.events/docs?utm_source=gh-readme) · [Sitio Web](https://hi.events?utm_source=gh-readme)

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![GitHub Release](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![Run Unit Tests](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml)
[![Docker Pulls](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)

<a href="https://trendshift.io/repositories/10563" target="_blank">
  <img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" width="250" height="55"/>
</a>

<p>
<a href="README.de.md">Deutsch</a> · <a href="README.pt.md">Português</a> · <a href="README.pt-br.md">Português do Brasil</a> · <a href="README.fr.md">Français</a> · <a href="README.it.md">Italiano</a> · <a href="README.nl.md">Nederlands</a> · <a href="README.es.md">Español</a> · <a href="README.zh-cn.md">中文</a> · <a href="README.zh-hk.md">繁體中文</a> · <a href="README.ja.md">日本語</a> · <a href="README.vi.md">Tiếng Việt</a> · <a href="README.tr.md">Türkçe</a> · <a href="README.hu.md">Magyar</a> · <a href="README.pl.md">Polski</a> · <a href="README.sk.md">Slovenčina</a>
</p>

</div>

<br>

## ¿Por qué Hi.Events?

La mayoría de las plataformas de venta de entradas cobran comisiones por entrada y bloquean tus datos en su ecosistema. **Hi.Events es una alternativa moderna y de código abierto a Eventbrite, Tickettailor, Dice.fm y otras plataformas de venta de entradas** para organizadores que desean control total sobre la marca, el proceso de compra, los datos y la infraestructura.

Diseñado para promotores de eventos nocturnos, organizadores de festivales, venues, grupos comunitarios y organizadores de conferencias.

<br>

<img alt="Panel de Control de Hi.Events" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Funcionalidades

<table>
<tr>
<td width="50%" valign="top">

### 🎟️ Venta de Entradas

- Tipos de entradas flexibles (gratuitas, de pago, donación, escalonadas)
- Entradas ocultas y bloqueadas con códigos promocionales
- Códigos promocionales y acceso de preventa
- Productos adicionales (merchandising, mejoras, extras)
- Categorías de productos para organización
- Soporte completo de impuestos y tarifas (IVA, tarifas de servicio)
- Gestión de capacidad y límites compartidos

</td>
<td width="50%" valign="top">

### 🎨 Marca y Personalización

- Proceso de compra atractivo y optimizado para conversión
- Diseños de entradas PDF personalizables
- Página de inicio del organizador con marca propia
- Constructor de páginas de eventos con arrastrar y soltar
- Widget de entradas embebible
- Herramientas SEO (meta tags, Open Graph)

</td>
</tr>
<tr>
<td width="50%" valign="top">

### 👥 Gestión de Asistentes

- Preguntas personalizadas en el checkout
- Búsqueda avanzada, filtrado y exportación (CSV/XLSX)
- Reembolsos completos y parciales
- Mensajes masivos por tipo de entrada
- Check-in con código QR y registros de escaneo
- Listas de check-in con control de acceso

</td>
<td width="50%" valign="top">

### 📊 Análisis y Crecimiento

- Panel de ventas en tiempo real
- Seguimiento de afiliados y referencias
- Informes avanzados (ventas, impuestos, promociones)
- Webhooks (Zapier, Make, CRMs)

</td>
</tr>
<tr>
<td colspan="2" valign="top">

### ⚙️ Operaciones

Roles y permisos multiusuario · Pagos instantáneos con Stripe Connect · Métodos de pago offline · Soporte para eventos presenciales ·
Facturación automática · Archivo de eventos · Soporte multiidioma · API REST completa

</td>
</tr>
</table>

<br>

## Comparación

| Funcionalidad                         | Hi.Events | Eventbrite | Tickettailor | Dice    |
|:--------------------------------------|:----------|:-----------|:-------------|:--------|
| Opción autohospedada                  | ✅         | ❌          | ❌            | ❌       |
| Código abierto                        | ✅         | ❌          | ❌            | ❌       |
| Sin comisiones por entrada (auto-alojado) | ✅         | ❌          | ❌            | ❌       |
| Marca personalizada completa          | ✅         | Limitado   | ✅            | Limitado |
| Seguimiento de afiliados              | ✅         | ✅          | ❌            | ❌       |
| Acceso a API                          | ✅         | ✅          | ✅            | Limitado |
| Tus propios datos                     | ✅         | ❌          | ❌            | ❌       |

<br>

## Inicio Rápido

### Despliegue con un Clic

[![Deploy en DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy en Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy en Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy en Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

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
> **Usuarios de Windows:** Consulta `./docker/all-in-one/README.md` para instrucciones de generación de claves.

Abre `http://localhost:8123` y crea tu cuenta.

📖 [Guía de instalación completa](https://hi.events/docs/getting-started?utm_source=gh-readme)

<br>

## Hi.Events Cloud

¿Prefieres no autohospedar? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** es una opción completamente gestionada con configuración cero, actualizaciones automáticas e infraestructura administrada.

[Comenzar →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Documentación

| Recurso        | Enlace                                                                                        |
|:---------------|:----------------------------------------------------------------------------------------------|
| Primeros Pasos | [hi.events/docs/getting-started](https://hi.events/docs/getting-started?utm_source=gh-readme) |
| Configuración  | [hi.events/docs/configuration](https://hi.events/docs/configuration?utm_source=gh-readme)     |
| Referencia API | [hi.events/docs/api](https://hi.events/docs/api?utm_source=gh-readme)                         |
| Webhooks       | [hi.events/docs/webhooks](https://hi.events/docs/webhooks?utm_source=gh-readme)               |

<br>

## Contribuir

Damos la bienvenida a contribuciones. Consulta la [guía de contribución](CONTRIBUTING.md) para más detalles.

<br>

## Soporte

📖 [Documentación](https://hi.events/docs?utm_source=gh-readme) · 📧 [hello@hi.events](mailto:hello@hi.events) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues)

<br>

## Registro de Cambios

Mantente actualizado con las nuevas funcionalidades y mejoras en la [página de versiones](https://github.com/HiEventsDev/hi.events/releases).

<br>

## Licencia

Hi.Events está licenciado bajo **AGPL-3.0 con términos adicionales**. Licencias comerciales disponibles. [Más información](https://hi.events/licensing).

<br>

<div align="center">

**[Sitio Web](https://hi.events)** · **[Documentación](https://hi.events/docs)** · **[Twitter/X](https://x.com/HiEventsTickets)**

Hecho con ☘️ en Irlanda

</div>
