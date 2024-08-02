<p align="center">
  <img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/hi-events-rainbow.png?v=1" alt="Logotipo de Hi.Events" width="200px">
</p>
<h3 align="center">Hi.Events</h3>
<p align="center">
<a href="https://demo.hi.events/event/1/dog-conf-2030">Evento de Demostración 🌟</a> <a href="https://hi.events?utm_source=gh-readme">Sitio Web 🌎</a>  <a href="https://hi.events/docs">Documentación 📄</a>  <a href="https://hi.events/docs/getting-started?utm_source=gh-readme">Instalación ⚙️</a>
</p>

<h3 align="center">
 Gestiona eventos y vende entradas en línea sin esfuerzo.
</h3>

<div align="center">

[![Documentación de Hi.Events](https://img.shields.io/badge/docs-hi.events-blue)](https://hi.events/docs)
[![Licencia: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/hi.events/LICENCE)
[![Lanzamiento en GitHub](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![Ejecutar pruebas unitarias](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml)
[![Descargas de Docker](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)

</div>

<div align="center">
 🌟 ¡Un estrella sería muy apreciada! 🌟
</div>

<hr/>

## Tabla de Contenidos

- [Introducción](#-introducción)
- [Funciones](#-funciones)
- [Primeros Pasos](#-primeros-pasos)
- [Registro de Cambios](#-registro-de-cambios)
- [Contribuciones](#-contribuciones)
- [FAQ](#-faq)

## 📚 Introducción

<a href="https://hi.events">Hi.Events</a> es una plataforma de gestión de eventos y venta de entradas autoalojada y rica en funciones. Desde conferencias hasta noches de club, Hi.Events está diseñada para ayudarle a crear, gestionar y vender entradas para eventos de todos los tamaños.

<img alt="Panel de control de venta de entradas autoalojado de Hi.Events" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/dashboard-screenshot.png"/>

## 🌟 Funciones

<a href="https://hi.events">Hi.Events</a> está repleta de funciones para agilizar su gestión de eventos y la venta de entradas:

- 📊 **Analítica de Eventos:** Obtenga información profunda sobre el rendimiento del evento y las ventas de entradas.
- 🎟 **Widget de Entradas Integrable:** Integre fácilmente la venta de entradas en cualquier sitio web.
- 🖥 **Páginas de Eventos Personalizables:** Cree páginas de eventos llamativas con opciones de diseño flexibles.
- 🔑 **Herramientas de Check-In Intuitivas:** Registre fácilmente a los asistentes en la puerta con la herramienta de check-in por código QR de Hi.Events.
- 💬 **Herramientas de Mensajería para Eventos:** Envíe mensajes importantes y recordatorios a los asistentes.
- 📝 **Formularios de Pedido Personalizados:** Recopile información de los asistentes con preguntas personalizadas en el momento del pago.
- 🎫 **Tipos de Entradas Múltiples:** Entradas gratuitas, pagadas, de donación o escalonadas.
- 💸 **Códigos Promocionales Versátiles:** Códigos de descuento altamente versátiles. Acceso anticipado, múltiples opciones de descuento.
- 💰 **Pagos Instantáneos:** Disfrute de pagos instantáneos con la integración de Stripe.
- 🧾 **Configuración de Impuestos y Tarifas:** Añada impuestos y tarifas por entrada.
- 📦 **Exportaciones de Datos:** Exporte datos de asistentes y pedidos a XLSX o CSV.
- 💻 **API REST:** API REST completa para integraciones personalizadas.
- 🔍 **Herramientas de SEO:** Personalice la configuración de SEO para cada evento.
- 🛒 **Proceso de Compra Hermoso:** Asegure una experiencia de compra fluida y hermosa.
- 🔐 **Acceso Basado en Roles:** Soporte para múltiples roles de usuario.
- 💻 **Soporte para Eventos en Línea:** Ofrezca instrucciones y enlaces para eventos en línea.
- ⏪ **Soporte para Reembolsos Completos y Parciales:** Gestione fácilmente reembolsos completos y parciales.
- 📧 **Notificaciones por Correo Electrónico:** Mantenga informados a los asistentes con notificaciones automáticas por correo electrónico.
- 📱 **Adaptable a Móviles:** Disfrute de una experiencia sin interrupciones en cualquier dispositivo.
- 🌐 **Soporte Multilingüe:** Soporte para varios idiomas.
- 🎉 **Y mucho más!**

## 🚀 Primeros Pasos

Para obtener instrucciones de instalación detalladas, consulte nuestra [documentación](https://hi.events/docs/getting-started). Para un inicio rápido, siga estos pasos:

### Despliegues con Un Clic

[![Desplegar en DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)

[![Desplegar en Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)

[![Desplegar en Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)

[![Desplegar en Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### 🐳 Inicio Rápido con Docker

> [!IMPORTANTE]  
> Asegúrese de tener Docker y Docker Compose instalados en su sistema. Si no, puede descargarlos desde el sitio web oficial de Docker: [Docker](https://www.docker.com/get-started).

1. **Clonar el Repositorio:**
   ```bash
   git clone git@github.com:HiEventsDev/hi.events.git
   ```

2. **Navegar al Directorio de Docker:**
   ```bash
   cd hi.events/docker/all-in-one
   ```

3. **Iniciar los Contenedores de Docker:**
   ```bash
   docker compose up -d
   ```
4. **Crear una cuenta:**
   ```bash
   Abra su navegador y navegue a http://localhost:8123/auth/register.
   ```

ℹ️ Consulte la [guía de primeros pasos](https://hi.events/docs/getting-started) para otros métodos de instalación y
para configurar un entorno de producción o de desarrollo local.

## 📝 Registro de Cambios

Manténgase actualizado con nuestras mejoras y adiciones de funciones en nuestra [página de lanzamientos en GitHub](https://github.com/HiEventsDev/hi.events/releases).

## 🤝 Contribuciones

¡Damos la bienvenida a contribuciones, sugerencias y reportes de errores! Antes de proponer una nueva función o extensión,
abra un issue para discutirlo.

## ❓ FAQ

¿Tiene preguntas? Nuestra [documentación](https://hi.events/docs) tiene respuestas. Si no encuentra lo que busca, no dude en
contactarnos en [hello@hi.events](mailto:hello@hi.events).

## 📜 Licencia

Hi.Events está licenciado bajo los términos de la [AGPL-3.0](https://github.com/HiEventsDev/hi.events/blob/main/LICENCE).

Para obtener más información sobre la licencia, incluidas las opciones de licencia comercial, visite nuestra página de licencias [aquí](https://hi.events/licensing).
