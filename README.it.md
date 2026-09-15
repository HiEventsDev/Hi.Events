<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - Piattaforma Open Source per Biglietteria Eventi" width="100%">

# Hi.Events

### Piattaforma open-source per biglietteria e gestione eventi

Vendi biglietti online per conferenze, eventi notturni, concerti, serate in club, workshop e festival.  
Self-hosted o cloud. I tuoi eventi, il tuo brand, i tuoi dati.

[Prova Cloud →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Demo Live](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Documentazione](https://hi.events/docs?utm_source=gh-readme) · [Sito Web](https://hi.events?utm_source=gh-readme)

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

## Perché Hi.Events?

La maggior parte delle piattaforme di biglietteria applica commissioni per biglietto e blocca i tuoi dati nel proprio
ecosistema. **Hi.Events è un'alternativa moderna e open-source a Eventbrite, Tickettailor, Dice.fm e alle altre
piattaforme di ticketing** per gli organizzatori che vogliono il pieno controllo su brand, checkout, dati e
infrastruttura.

Usato da migliaia di organizzatori in tutto il mondo — da promoter della vita notturna e festival fino a venue, gruppi
di comunità e organizzatori di conferenze. Ospitalo tu, oppure lascia che ce ne occupiamo noi su Hi.Events Cloud.

<br>

<img alt="Hi.Events Dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Funzionalità

**🎟️ Biglietteria e vendite** — biglietti gratuiti, a pagamento, a donazione e a fasce · eventi ricorrenti e con più
date · liste d'attesa per i sold out · codici promo, inclusi biglietti nascosti e sbloccabili con codice · add-on e
categorie di prodotto · gestione di tasse, commissioni e capienza

**🎨 Branding e personalizzazione** — designer della pagina evento per immagine di copertina, colori e tipografia ·
pagina organizzatore personalizzata · biglietti PDF personalizzabili · widget di biglietteria integrabile · controllo
dei metadati SEO

**👥 Gestione partecipanti** — domande personalizzate al checkout · ricerca, filtri ed esportazione CSV/XLSX avanzati ·
rimborsi totali e parziali · messaggistica di massa · check-in con QR code, log delle scansioni e liste di check-in ad
accesso controllato

**📊 Analisi e crescita** — dashboard delle vendite · tracciamento affiliati · report su vendite giornaliere, vendite per
prodotto, codici promo, ricavi e tasse · webhook in uscita

**⚙️ Operazioni** — ruoli multi-utente · pagamenti Stripe Connect · metodi di pagamento offline · fatturazione
automatica · eventi online e in presenza · supporto multilingua · API REST completa con
[documentazione OpenAPI interattiva](#rest-api)

<br>

## Avvio Rapido

Costruito con **Laravel 13** (PHP >=8.3) · **React 19** con SSR · **TypeScript** · **PostgreSQL** · **Redis** ·
**Docker**.

### Deploy con un Clic

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# Genera le chiavi (Linux/macOS)
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Utenti Windows:** consulta `./docker/all-in-one/README.md` per le istruzioni sulla generazione delle chiavi.

Apri `http://localhost:8123` e crea il tuo account.

📖 [Guida completa all'installazione](https://hi.events/docs/getting-started?utm_source=gh-readme)

### REST API

Hi.Events include un'API REST documentata. Imposta `API_DOCS_ENABLED=true` nel tuo `.env` per pubblicare la
documentazione OpenAPI interattiva su `/docs/api` della tua istanza, oppure esporta la specifica con:

```bash
php artisan scramble:export
```

<br>

## Hi.Events Cloud

Preferisci non fare self-hosting? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** è la
versione completamente gestita di questo repository — zero configurazione, aggiornamenti automatici e infrastruttura
gestita dal team che sviluppa Hi.Events.

[Inizia ora →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Licenza

Hi.Events è rilasciato sotto **AGPL-3.0 con termini aggiuntivi**. I termini aggiuntivi richiedono di mantenere
l'attribuzione "Powered by Hi.Events" sulle pagine e nelle email generate dal software — vedi [LICENCE](LICENCE) per il
testo esatto.

**Sono disponibili licenze commerciali** se vuoi rimuovere l'attribuzione o hai bisogno di condizioni adatte a un
deployment white-label. [Opzioni di licenza](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Contribuire

I contributi sono benvenuti — consulta la [guida ai contributi](CONTRIBUTING.md) per iniziare. Apri una issue o una
discussione prima di avviare lavori significativi, così da allinearci sull'approccio. Chi contribuisce firma un
[CLA](CLA.md).

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Sicurezza

Hai trovato una vulnerabilità? Segnalala privatamente a [security@hi.events](mailto:security@hi.events) invece di aprire
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

**[Sito Web](https://hi.events)** · **[Documentazione](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Licenze](https://hi.events/licensing)**

Made with ☘️ in Ireland

</div>
