<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - Open-source platforma na predaj vstupeniek" width="100%">

# Hi.Events

### Open-source platforma na správu udalostí a predaj vstupeniek online

Predávajte vstupenky online na konferencie, nočné podujatia, koncerty, klubové akcie, workshopy a festivaly.  
Self-hosted alebo v cloude. Vaše udalosti, vaša značka, vaše dáta.

[Vyskúšajte Cloud →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Živé demo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Dokumentácia](https://hi.events/docs?utm_source=gh-readme) · [Webstránka](https://hi.events?utm_source=gh-readme)

[![Licencia: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![GitHub Release](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![Unit testy](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml)
[![Docker Pulls](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)

<a href="https://trendshift.io/repositories/10563" target="_blank">
  <img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" width="250" height="55"/>
</a>

<p>
<a href="README.de.md">Deutsch</a> · <a href="README.pt.md">Português</a> · <a href="README.fr.md">Français</a> · <a href="README.it.md">Italiano</a> · <a href="README.nl.md">Nederlands</a> · <a href="README.es.md">Español</a> · <a href="README.zh-cn.md">中文</a> · <a href="README.zh-hk.md">繁體中文</a> · <a href="README.ja.md">日本語</a> · <a href="README.vi.md">Tiếng Việt</a> · <a href="README.tr.md">Türkçe</a> · <a href="README.hu.md">Magyar</a> · <a href="README.pl.md">Polski</a> · <a href="README.sk.md">Slovenčina</a>
</p>

</div>

<br>

## Prečo Hi.Events?

Väčšina platforiem na predaj vstupeniek si účtuje poplatky za vstupenku a uzamyká vaše dáta vo svojom ekosystéme. **Hi.Events je moderná, open-source alternatíva k Eventbrite, Tickettailor, Dice.fm a ďalším platformám** pre organizátorov, ktorí chcú plnú kontrolu nad značkou, pokladňou, dátami a infraštruktúrou.

Vytvorené pre promotérov nočného života, organizátorov festivalov, koncertné sály, komunitné skupiny a hostiteľov konferencií.

<br>

<img alt="Hi.Events Dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Funkcie

<table>
<tr>
<td width="50%" valign="top">

### 🎟️ Predaj vstupeniek

- Flexibilné typy vstupeniek (zadarmo, platené, dary, viacúrovňové)
- Skryté a zamknuté vstupenky za promo kódmi
- Promo kódy a prístup k predpredaju
- Doplnky k produktom (merch, upgrady, extras)
- Kategórie produktov pre organizáciu
- Plná podpora daní a poplatkov (DPH, servisné poplatky)
- Správa kapacity a zdieľané limity

</td>
<td width="50%" valign="top">

### 🎨 Branding a prispôsobenie

- Krásna, konverzne optimalizovaná pokladňa
- Prispôsobiteľné dizajny PDF vstupeniek
- Brandovaná stránka organizátora
- Drag-and-drop editor stránky udalosti
- Vložiteľný widget vstupeniek
- SEO nástroje (meta tagy, Open Graph)

</td>
</tr>
<tr>
<td width="50%" valign="top">

### 👥 Správa účastníkov

- Vlastné otázky pri pokladni
- Pokročilé vyhľadávanie, filtrovanie a export (CSV/XLSX)
- Plné a čiastočné vrátenia
- Hromadné správy podľa typu vstupenky
- QR kód odbavenie s logmi skenovania
- Zoznamy odbavenia s kontrolou prístupu

</td>
<td width="50%" valign="top">

### 📊 Analytika a rast

- Dashboard predajov v reálnom čase
- Sledovanie afiliátov a odporúčaní
- Pokročilé reporty (predaje, dane, promo akcie)
- Webhooky (Zapier, Make, CRM)

</td>
</tr>
<tr>
<td colspan="2" valign="top">

### ⚙️ Prevádzka

Role a oprávnenia viacerých používateľov · Okamžité výplaty cez Stripe Connect · Offline platobné metódy · Podpora offline udalostí ·
Automatická fakturácia · Archív udalostí · Viacjazyčná podpora · Plné REST API

</td>
</tr>
</table>

<br>

## Porovnanie

| Funkcia                          | Hi.Events | Eventbrite | Tickettailor | Dice    |
|:---------------------------------|:----------|:-----------|:-------------|:--------|
| Self-hosted možnosť              | ✅         | ❌          | ❌            | ❌       |
| Open source                      | ✅         | ❌          | ❌            | ❌       |
| Žiadne poplatky za vstupenku (self-hosted) | ✅         | ❌          | ❌            | ❌       |
| Plné prispôsobenie značky        | ✅         | Obmedzené  | ✅            | Obmedzené |
| Sledovanie afiliátov             | ✅         | ✅          | ❌            | ❌       |
| Prístup k API                    | ✅         | ✅          | ✅            | Obmedzené |
| Vlastníte svoje dáta             | ✅         | ❌          | ❌            | ❌       |

<br>

## Rýchly štart

### Nasadenie jedným kliknutím

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# Vygenerujte kľúče (Linux/macOS)
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Používatelia Windows:** Pozrite `./docker/all-in-one/README.md` pre inštrukcie na generovanie kľúčov.

Otvorte `http://localhost:8123` a vytvorte si účet.

📖 [Kompletný sprievodca inštaláciou](https://hi.events/docs/getting-started?utm_source=gh-readme)

<br>

## Hi.Events Cloud

Nechcete self-hostovať? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** je plne spravovaná možnosť s nulovou konfiguráciou, automatickými aktualizáciami a spravovanou infraštruktúrou.

[Začať →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Prispievanie

Vítame príspevky. Podrobnosti nájdete v [sprievodcovi prispievaním](CONTRIBUTING.md).

<br>

## Podpora

📖 [Dokumentácia](https://hi.events/docs?utm_source=gh-readme) · 📧 [hello@hi.events](mailto:hello@hi.events) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues)

<br>

## Zoznam zmien

Sledujte nové funkcie a vylepšenia na
[stránke vydaní](https://github.com/HiEventsDev/hi.events/releases).

<br>

## Licencia

Hi.Events je licencovaný pod **AGPL-3.0 s dodatočnými podmienkami**. K dispozícii je komerčná licencia.
[Zistiť viac](https://hi.events/licensing).

<br>

<div align="center">

**[Webstránka](https://hi.events)** · **[Dokumentácia](https://hi.events/docs)** · **[Twitter/X](https://x.com/HiEventsTickets)**

Vyrobené s ☘️ v Írsku

</div>
