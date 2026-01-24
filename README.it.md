<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - Piattaforma Open Source per Biglietteria Eventi" width="100%">

# Hi.Events

### Piattaforma open-source per biglietteria e gestione eventi

Vendi biglietti online per conferenze, eventi notturni, concerti, serate in club, workshop e festival.
Self-hosted o cloud. I tuoi eventi, il tuo brand, i tuoi dati.

[Prova Cloud →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Demo Live](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Documentazione](https://hi.events/docs?utm_source=gh-readme) · [Sito Web](https://hi.events?utm_source=gh-readme)

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![GitHub Release](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![Run Unit Tests](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml)
[![Docker Pulls](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)

<a href="https://trendshift.io/repositories/10563" target="_blank">
  <img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" width="250" height="55"/>
</a>

<p>
<a href="README.de.md">Deutsch</a> · <a href="README.pt.md">Português</a> · <a href="README.pt-br.md">Português do Brasil</a> · <a href="README.fr.md">Français</a> · <a href="README.it.md">Italiano</a> · <a href="README.nl.md">Nederlands</a> · <a href="README.es.md">Español</a> · <a href="README.zh-cn.md">中文</a> · <a href="README.zh-hk.md">繁體中文</a> · <a href="README.ja.md">日本語</a> · <a href="README.vi.md">Tiếng Việt</a> · <a href="README.tr.md">Türkçe</a> · <a href="README.hu.md">Magyar</a> · <a href="README.pl.md">Polski</a>
</p>

</div>

<br>

## Perché Hi.Events?

La maggior parte delle piattaforme di biglietteria applica commissioni per biglietto e blocca i tuoi dati nel loro ecosistema. **Hi.Events è un'alternativa moderna e open-source a Eventbrite, Tickettailor, Dice.fm e altre piattaforme di biglietteria** per gli organizzatori che desiderano il pieno controllo su branding, checkout, dati e infrastruttura.

Progettato per promoter della vita notturna, organizzatori di festival, venue, gruppi comunitari e organizzatori di conferenze.

<br>

<img alt="Dashboard Hi.Events" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Funzionalità

<table>
<tr>
<td width="50%" valign="top">

### 🎟️ Biglietteria e Vendite

- Tipi di biglietto flessibili (gratuiti, a pagamento, donazione, a livelli)
- Biglietti nascosti e bloccati dietro codici promozionali
- Codici promozionali e accesso in prevendita
- Prodotti aggiuntivi (merchandising, upgrade, extra)
- Categorie di prodotti per l'organizzazione
- Supporto completo per tasse e commissioni (IVA, commissioni di servizio)
- Gestione della capacità e limiti condivisi

</td>
<td width="50%" valign="top">

### 🎨 Branding e Personalizzazione

- Checkout ottimizzato per le conversioni
- Design personalizzabili dei biglietti PDF
- Homepage dell'organizzatore brandizzata
- Generatore di pagine eventi drag-and-drop
- Widget biglietti incorporabile
- Strumenti SEO (meta tag, Open Graph)

</td>
</tr>
<tr>
<td width="50%" valign="top">

### 👥 Gestione Partecipanti

- Domande di checkout personalizzate
- Ricerca avanzata, filtri ed esportazione (CSV/XLSX)
- Rimborsi totali e parziali
- Messaggistica di massa per tipo di biglietto
- Check-in con codice QR e registri di scansione
- Liste di check-in con accesso controllato

</td>
<td width="50%" valign="top">

### 📊 Analisi e Crescita

- Dashboard vendite in tempo reale
- Tracciamento affiliati e referral
- Report avanzati (vendite, tasse, promozioni)
- Webhook (Zapier, Make, CRM)

</td>
</tr>
<tr>
<td colspan="2" valign="top">

### ⚙️ Operazioni

Ruoli e permessi multi-utente · Pagamenti istantanei tramite Stripe Connect · Metodi di pagamento offline · Supporto eventi offline ·
Fatturazione automatica · Archivio eventi · Supporto multi-lingua · REST API completa

</td>
</tr>
</table>

<br>

## Confronto

| Funzionalità                        | Hi.Events | Eventbrite | Tickettailor | Dice     |
|:------------------------------------|:----------|:-----------|:-------------|:---------|
| Opzione self-hosted                 | ✅         | ❌          | ❌            | ❌        |
| Open source                         | ✅         | ❌          | ❌            | ❌        |
| Nessuna commissione (self-hosted)  | ✅         | ❌          | ❌            | ❌        |
| Branding completamente personalizzabile | ✅    | Limitato   | ✅            | Limitato |
| Tracciamento affiliati              | ✅         | ✅          | ❌            | ❌        |
| Accesso API                         | ✅         | ✅          | ✅            | Limitato |
| Proprietà dei dati                  | ✅         | ❌          | ❌            | ❌        |

<br>

## Avvio Rapido

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
> **Utenti Windows:** Consulta `./docker/all-in-one/README.md` per le istruzioni sulla generazione delle chiavi.

Apri `http://localhost:8123` e crea il tuo account.

📖 [Guida completa all'installazione](https://hi.events/docs/getting-started?utm_source=gh-readme)

<br>

## Hi.Events Cloud

Preferisci non fare self-hosting? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** è un'opzione completamente gestita con zero configurazione, aggiornamenti automatici e infrastruttura gestita.

[Inizia →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Documentazione

| Risorsa         | Link                                                                                          |
|:----------------|:----------------------------------------------------------------------------------------------|
| Per Iniziare    | [hi.events/docs/getting-started](https://hi.events/docs/getting-started?utm_source=gh-readme) |
| Configurazione  | [hi.events/docs/configuration](https://hi.events/docs/configuration?utm_source=gh-readme)     |
| Riferimento API | [hi.events/docs/api](https://hi.events/docs/api?utm_source=gh-readme)                         |
| Webhook         | [hi.events/docs/webhooks](https://hi.events/docs/webhooks?utm_source=gh-readme)               |

<br>

## Contribuire

Accogliamo con piacere i contributi. Consulta la [guida ai contributi](CONTRIBUTING.md) per i dettagli.

<br>

## Supporto

📖 [Documentazione](https://hi.events/docs?utm_source=gh-readme) · 📧 [hello@hi.events](mailto:hello@hi.events) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues)

<br>

## Registro delle Modifiche

Rimani aggiornato con le nuove funzionalità e i miglioramenti sulla [pagina delle release](https://github.com/HiEventsDev/hi.events/releases).

<br>

## Licenza

Hi.Events è rilasciato sotto licenza **AGPL-3.0 con termini aggiuntivi**. Licenze commerciali disponibili. [Maggiori informazioni](https://hi.events/licensing).

<br>

<div align="center">

**[Sito Web](https://hi.events)** · **[Documentazione](https://hi.events/docs)** · **[Twitter/X](https://x.com/HiEventsTickets)**

Realizzato con ☘️ in Irlanda

</div>
