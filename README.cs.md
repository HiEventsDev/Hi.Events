<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - Open-source platforma pro prodej vstupenek" width="100%">

# Hi.Events

### Open-source platforma pro prodej vstupenek a správu událostí

Prodávejte vstupenky online na konference, noční akce, koncerty, klubové večery, workshopy a festivaly.  
Self-hosted nebo v cloudu. Vaše události, vaše značka, vaše data.

[Vyzkoušet Cloud →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Živé demo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Dokumentace](https://hi.events/docs?utm_source=gh-readme) · [Web](https://hi.events?utm_source=gh-readme)

[![Licence: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![GitHub Release](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![Unit testy](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml)
[![Docker Pulls](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)

<a href="https://trendshift.io/repositories/10563" target="_blank">
  <img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" width="250" height="55"/>
</a>

<p>
<a href="README.de.md">Deutsch</a> · <a href="README.pt.md">Português</a> · <a href="README.fr.md">Français</a> · <a href="README.it.md">Italiano</a> · <a href="README.nl.md">Nederlands</a> · <a href="README.es.md">Español</a> · <a href="README.zh-cn.md">中文</a> · <a href="README.zh-hk.md">繁體中文</a> · <a href="README.ja.md">日本語</a> · <a href="README.vi.md">Tiếng Việt</a> · <a href="README.tr.md">Türkçe</a> · <a href="README.hu.md">Magyar</a> · <a href="README.pl.md">Polski</a> · <strong>Čeština</strong> · <a href="README.sk.md">Slovenčina</a> · <a href="README.el.md">Ελληνικά</a>
</p>

</div>

<br>

## Proč Hi.Events?

Většina platforem pro prodej vstupenek si účtuje poplatky za každou vstupenku a uzamyká vaše data do svého ekosystému. **Hi.Events je moderní,
open-source alternativa k Eventbrite, Tickettailor, Dice.fm a dalším platformám pro prodej vstupenek** pro organizátory, kteří chtějí
plnou kontrolu nad značkou, pokladnou, daty a infrastrukturou.

Vytvořeno pro promotéry nočního života, organizátory festivalů, místa konání, komunitní skupiny a pořadatele konferencí.

<br>

<img alt="Hi.Events Dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Funkce

<table>
<tr>
<td width="50%" valign="top">

### 🎟️ Vstupenky a prodej

- Flexibilní typy vstupenek (zdarma, placené, dobrovolné, víceúrovňové)
- Skryté a zamčené vstupenky za promo kódy
- Promo kódy a přístup k předprodeji
- Doplňky produktů (merch, upgrady, extra položky)
- Kategorie produktů pro přehlednost
- Plná podpora daní a poplatků (DPH, servisní poplatky)
- Správa kapacity a sdílené limity

</td>
<td width="50%" valign="top">

### 🎨 Branding a přizpůsobení

- Krásná pokladna optimalizovaná pro konverze
- Přizpůsobitelné designy PDF vstupenek
- Brandovaná domovská stránka organizátora
- Editor stránky události metodou drag-and-drop
- Vložitelný widget vstupenek
- SEO nástroje (meta tagy, Open Graph)

</td>
</tr>
<tr>
<td width="50%" valign="top">

### 👥 Správa účastníků

- Vlastní otázky u pokladny
- Pokročilé vyhledávání, filtrování a export (CSV/XLSX)
- Plné a částečné vrácení peněz
- Hromadné zprávy podle typu vstupenky
- Odbavení pomocí QR kódu se záznamy skenování
- Seznamy odbavení s řízeným přístupem

</td>
<td width="50%" valign="top">

### 📊 Analytika a růst

- Přehled prodejů v reálném čase
- Sledování affiliate partnerů a doporučení
- Pokročilé reporty (prodeje, daně, promo akce)
- Webhooky (Zapier, Make, CRM systémy)

</td>
</tr>
<tr>
<td colspan="2" valign="top">

### ⚙️ Provoz

Role a oprávnění pro více uživatelů · Okamžité výplaty přes Stripe Connect · Offline platební metody · Podpora offline událostí ·
Automatická fakturace · Archiv událostí · Vícejazyčná podpora · Plné REST API

</td>
</tr>
</table>

<br>

## Porovnání

| Funkce                           | Hi.Events | Eventbrite | Tickettailor | Dice    |
|:---------------------------------|:----------|:-----------|:-------------|:--------|
| Možnost self-hostingu            | ✅         | ❌          | ❌            | ❌       |
| Open source                      | ✅         | ❌          | ❌            | ❌       |
| Žádné poplatky za vstupenku (self-hosted) | ✅ | ❌          | ❌            | ❌       |
| Plné přizpůsobení značky         | ✅         | Omezené    | ✅            | Omezené |
| Sledování affiliate              | ✅         | ✅          | ❌            | ❌       |
| Přístup k API                    | ✅         | ✅          | ✅            | Omezené |
| Vlastníte svá data               | ✅         | ❌          | ❌            | ❌       |

<br>

## Rychlý start

### Nasazení jedním kliknutím

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# Vygenerujte klíče (Linux/macOS)
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Uživatelé Windows:** Pokyny ke generování klíčů najdete v `./docker/all-in-one/README.md`.

Otevřete `http://localhost:8123` a vytvořte si účet.

📖 [Kompletní návod k instalaci](https://hi.events/docs/getting-started?utm_source=gh-readme)

<br>

## Hi.Events Cloud

Nechcete se starat o self-hosting? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** je plně
spravovaná varianta s nulovou konfigurací, automatickými aktualizacemi a spravovanou infrastrukturou.

[Začít →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Přispívání

Příspěvky vítáme. Podrobnosti najdete v [průvodci pro přispěvatele](CONTRIBUTING.md).

<br>

## Podpora

📖 [Dokumentace](https://hi.events/docs?utm_source=gh-readme) · 📧 [hello@hi.events](mailto:hello@hi.events) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues)

<br>

## Seznam změn

Sledujte nové funkce a vylepšení na
[stránce vydání](https://github.com/HiEventsDev/hi.events/releases).

<br>

## Licence

Hi.Events je licencován pod **AGPL-3.0 s dodatečnými podmínkami**. K dispozici je komerční licence.
[Zjistit více](https://hi.events/licensing).

<br>

<div align="center">

**[Web](https://hi.events)** · **[Dokumentace](https://hi.events/docs)** · **[Twitter/X](https://x.com/HiEventsTickets)**

Vyrobeno s ☘️ v Irsku

</div>
