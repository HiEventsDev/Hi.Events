<div align="center">

<img src=".github/assets/readme-banner.png" alt="Hi.Events - Open-source event ticketing and management platform" width="100%">

# Hi.Events

### Piattaforma open source per biglietteria e gestione eventi

Vendi biglietti online per conferenze, eventi nightlife, concerti, serate in discoteca, workshop e festival.<br>
Self-hosted o in cloud. I tuoi eventi, il tuo brand, i tuoi dati.

[Prova Cloud →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Demo dal vivo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Documentazione](https://hi.events/docs?utm_source=gh-readme) · [Enterprise e licenze](https://hi.events/licensing?utm_source=gh-readme) · [Sito web](https://hi.events?utm_source=gh-readme)

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
> **È arrivato Hi.Events 2.0** con eventi ricorrenti, posti riservati, biglietteria, line-up e programmi, single
> sign-on, permessi del team e chiavi API. [Scopri le novità →](https://github.com/HiEventsDev/hi.events/releases)

<br>

<img alt="Hi.Events event dashboard on desktop and an event page on mobile" src=".github/assets/readme-showcase.png" width="100%"/>

<br>

## Perché Hi.Events?

La maggior parte delle piattaforme di biglietteria applica una commissione su ogni biglietto e trattiene i dati dei tuoi clienti. **Hi.Events è un'alternativa
open source a Eventbrite, Tickettailor, Dice.fm e alle altre piattaforme di biglietteria** che ti dà il pieno controllo su
branding, checkout e dati.

Scelto da migliaia di organizzatori in tutto il mondo: promoter di nightlife, festival, location, associazioni
e organizzatori di conferenze. Gestiscilo in autonomia, oppure lascia che lo gestiamo noi per te su Hi.Events Cloud.

<br>

## Funzionalità

**📅 Eventi**
- Eventi singoli e ricorrenti, con prezzi, capienza e orari per data
- Duplica un evento con un clic
- Luoghi salvati ed eventi online
- Editor della homepage dell'evento e pagine organizzatore personalizzate
- Pagine di line-up e programma

**🎟️ Biglietti e prodotti**
- Biglietti a pagamento, gratuiti, a offerta libera e a fasce
- Posti riservati <sup>Enterprise</sup>
- Prodotti aggiuntivi e merchandising
- Biglietti nascosti sbloccabili con codici promo
- Limiti per ordine e finestre di vendita programmate
- Capienza condivisa e liste d'attesa automatiche

**🛒 Vendita**
- Embed personalizzabili per il tuo sito: widget biglietti, pulsanti, elenchi eventi, line-up e programmi
- Codici promo e sconti
- Link di tracciamento per promoter e affiliati
- Domande di registrazione personalizzate
- Consenso marketing al checkout
- Tracciamento con Meta, TikTok, Google Analytics e Tag Manager

**💳 Pagamenti**
- Pagamenti sul tuo account Stripe
- Tasse e commissioni, incluse o aggiunte al checkout
- Fatturazione e pagamenti offline
- Rimborsi totali e parziali

**🚪 Partecipanti e ingresso**
- Biglietteria per la vendita all'ingresso <sup>Enterprise</sup>
- Check-in con la fotocamera del telefono o uno scanner USB
- Liste di check-in condivisibili con conteggi in tempo reale
- Messaggi ai partecipanti e modelli email personalizzati
- Biglietti PDF con il tuo brand
- Partecipanti aggiunti manualmente
- Report su vendite, tasse e codici promo

<br>

## Pensato per le organizzazioni

**🔐 Sicurezza e accessi**
- Single sign-on
- Autenticazione a due fattori
- Ruoli e permessi del team
- Più organizzatori in un unico account

**🔌 Integrazioni**
- REST API e chiavi API
- Webhooks
- Esportazioni CSV e XLSX

**🗄️ I tuoi dati**
- Self-hosting con Docker
- Eliminazione e anonimizzazione dell'account
- Disponibile in 20 lingue

**✅ Testato**
- Una suite di test completa viene eseguita a ogni modifica

<br>

## Avvio rapido

Realizzato con **Laravel 13** (PHP >=8.3) · **React 19** con SSR · **TypeScript** · **PostgreSQL** · **Redis** · **Docker**.

### Deploy con un clic

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
> **Utenti Windows:** consulta `./docker/all-in-one/README.md` per le istruzioni sulla generazione delle chiavi.

Apri `http://localhost:8123` e crea il tuo account.

📖 [Guida all'installazione completa](https://hi.events/docs/getting-started?utm_source=gh-readme)

> [!IMPORTANT]
> **Aggiorni dalla 1.x?** Leggi le [note di rilascio](https://github.com/HiEventsDev/hi.events/releases) prima di aggiornare.

### REST API

Crea le chiavi API in **Impostazioni → Chiavi API**, dove trovi anche il riferimento API.

<br>

## Lingue

Inglese, Deutsch, Français, Italiano, Nederlands, Español, Português, Português (Brasil),
Polski, Slovenčina, Magyar, Svenska, Suomi, Ελληνικά, Türkçe, Tiếng Việt, 简体中文, 繁體中文, 日本語 e 한국어.

Correzioni e nuove lingue sono benvenute: consulta la [guida per i contributori](CONTRIBUTING.md).

<br>

## Hi.Events Cloud

Preferisci non gestire l'hosting? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** è la versione completamente
gestita, curata dal team che sviluppa Hi.Events.

[Inizia ora →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Licenze

Hi.Events è distribuito con licenza **AGPL-3.0 con termini aggiuntivi**. I termini aggiuntivi richiedono di mantenere la dicitura "Powered by Hi.Events"
nelle pagine e nelle email generate dal software. Il testo esatto è disponibile in [LICENCE](LICENCE).

I posti riservati e la biglietteria sono coperti dalla [Hi.Events Enterprise Licence](backend/ee/LICENCE) e richiedono una
licenza per l'uso in produzione.

**Sono disponibili licenze commerciali** se vuoi rimuovere la dicitura o ti servono condizioni adatte a un
deployment white label. [Opzioni di licenza](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Contribuire

I contributi sono benvenuti: consulta la [guida per i contributori](CONTRIBUTING.md) per iniziare. Apri una issue o una
discussione prima di avviare lavori importanti, così possiamo concordare l'approccio. I contributori firmano
un [CLA](CLA.md).

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Sicurezza

Hai trovato una vulnerabilità? Segnalala in privato a [security@hi.events](mailto:security@hi.events) invece di aprire
una issue pubblica. Consulta la nostra [policy di sicurezza](SECURITY.md).

<br>

## Supporto

📖 [Documentazione](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Discussioni](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

Le nuove funzionalità e i miglioramenti sono elencati nella
[pagina delle release](https://github.com/HiEventsDev/hi.events/releases).

<br>

<div align="center">

**[Sito web](https://hi.events)** · **[Documentazione](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Licenze](https://hi.events/licensing)**

Fatto con ☘️ in Irlanda

</div>
