<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - Open-source platforma na predaj vstupeniek" width="100%">

# Hi.Events

### Open-source platforma na správu udalostí a predaj vstupeniek online

Predávajte vstupenky online na konferencie, nočné podujatia, koncerty, klubové akcie, workshopy a festivaly.  
Self-hosted alebo v cloude. Vaše udalosti, vaša značka, vaše dáta.

[Vyskúšajte Cloud →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Živé demo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Dokumentácia](https://hi.events/docs?utm_source=gh-readme) · [Webstránka](https://hi.events?utm_source=gh-readme)

[![Licencia: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![GitHub Release](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![GitHub Stars](https://img.shields.io/github/stars/HiEventsDev/hi.events?style=flat)](https://github.com/HiEventsDev/hi.events/stargazers)
[![Docker Pulls](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)
[![E2E testy](https://github.com/HiEventsDev/hi.events/actions/workflows/e2e.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/e2e.yml)

<a href="https://trendshift.io/repositories/10563" target="_blank">
  <img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" width="250" height="55"/>
</a>

<p>
<a href="README.de.md">Deutsch</a> · <a href="README.pt.md">Português</a> · <a href="README.fr.md">Français</a> · <a href="README.it.md">Italiano</a> · <a href="README.nl.md">Nederlands</a> · <a href="README.es.md">Español</a> · <a href="README.zh-cn.md">中文</a> · <a href="README.zh-hk.md">繁體中文</a> · <a href="README.ja.md">日本語</a> · <a href="README.vi.md">Tiếng Việt</a> · <a href="README.tr.md">Türkçe</a> · <a href="README.hu.md">Magyar</a> · <a href="README.pl.md">Polski</a> · <a href="README.sk.md">Slovenčina</a> · <a href="README.el.md">Ελληνικά</a> · <a href="README.fi.md">Suomi</a>
</p>

</div>

<br>

## Prečo Hi.Events?

Väčšina platforiem na predaj vstupeniek si účtuje poplatky za každú vstupenku a zamyká vaše dáta vo svojom ekosystéme.
**Hi.Events je moderná open-source alternatíva k Eventbrite, Tickettailor, Dice.fm a ďalším platformám na predaj
vstupeniek** pre organizátorov, ktorí chcú mať plnú kontrolu nad značkou, nákupným procesom, dátami a infraštruktúrou.

Používajú ho tisícky organizátorov po celom svete — od promotérov nočného života a festivalov až po kluby, komunitné
skupiny a organizátorov konferencií. Prevádzkujte si ho sami, alebo to nechajte na nás v Hi.Events Cloud.

<br>

<img alt="Hi.Events Dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Funkcie

**🎟️ Predaj vstupeniek** — bezplatné, platené, darovacie a stupňované vstupenky · opakované a viacdňové udalosti ·
čakacie listiny po vypredaní · promo kódy vrátane skrytých vstupeniek a vstupeniek odomykaných kódom · doplnky a
kategórie produktov · správa daní, poplatkov a kapacity

**🎨 Branding a prispôsobenie** — dizajnér stránky udalosti pre titulný obrázok, farby a typografiu · stránka
organizátora vo vašom brandingu · prispôsobiteľné PDF vstupenky · vložiteľný widget na predaj vstupeniek · nastavenie
SEO metadát

**👥 Správa účastníkov** — vlastné otázky pri objednávke · pokročilé vyhľadávanie, filtrovanie a export do CSV/XLSX ·
úplné a čiastočné refundácie · hromadné správy · check-in cez QR kód so záznamami skenovania a check-in zoznamami s
riadeným prístupom

**📊 Analytika a rast** — prehľad predaja · sledovanie affiliate partnerov · reporty denných predajov, predajov podľa
produktu, promo kódov, tržieb a daní · odchádzajúce webhooky

**⚙️ Prevádzka** — role pre viacerých používateľov · platby cez Stripe Connect · offline spôsoby platby · automatická
fakturácia · online aj osobné udalosti · viacjazyčná podpora · kompletné REST API s
[interaktívnou OpenAPI dokumentáciou](#rest-api)

<br>

## Rýchly štart

Postavené na **Laravel 13** (PHP >=8.3) · **React 19** so SSR · **TypeScript** · **PostgreSQL** · **Redis** ·
**Docker**.

### Nasadenie jedným kliknutím

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# Vygenerovanie kľúčov (Linux/macOS)
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Používatelia Windows:** pokyny na generovanie kľúčov nájdete v `./docker/all-in-one/README.md`.

Otvorte `http://localhost:8123` a vytvorte si účet.

📖 [Kompletný návod na inštaláciu](https://hi.events/docs/getting-started?utm_source=gh-readme)

### REST API

Hi.Events prináša zdokumentované REST API. Nastavte `API_DOCS_ENABLED=true` vo svojom `.env`, aby ste na vlastnej
inštancii sprístupnili interaktívnu OpenAPI dokumentáciu na `/docs/api`, alebo špecifikáciu vyexportujte príkazom:

```bash
php artisan scramble:export
```

<br>

## Hi.Events Cloud

Nechcete si to prevádzkovať sami? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** je
plne spravovaná verzia tohto repozitára — žiadne nastavovanie, automatické aktualizácie a spravovaná infraštruktúra od
tímu, ktorý Hi.Events vyvíja.

[Začať →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Licencovanie

Hi.Events je licencované pod **AGPL-3.0 s dodatočnými podmienkami**. Dodatočné podmienky vyžadujú zachovanie označenia
„Powered by Hi.Events“ na stránkach a v e-mailoch generovaných softvérom — presné znenie nájdete v [LICENCE](LICENCE).

**K dispozícii sú komerčné licencie**, ak chcete označenie odstrániť alebo potrebujete podmienky vhodné pre
white-label nasadenie. [Možnosti licencovania](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Prispievanie

Príspevky sú vítané — začnite [príručkou pre prispievateľov](CONTRIBUTING.md). Pred začatím rozsiahlejšej práce prosím
otvorte issue alebo diskusiu, aby sme zosúladili postup. Prispievatelia podpisujú [CLA](CLA.md).

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Bezpečnosť

Našli ste zraniteľnosť? Nahláste ju prosím súkromne na [security@hi.events](mailto:security@hi.events), namiesto
otvorenia verejného issue. Pozrite si našu [bezpečnostnú politiku](SECURITY.md).

<br>

## Podpora

📖 [Dokumentácia](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Diskusie](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

Nové funkcie a vylepšenia sú uvedené na
[stránke vydaní](https://github.com/HiEventsDev/hi.events/releases).

<br>

<div align="center">

**[Webstránka](https://hi.events)** · **[Dokumentácia](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Licencovanie](https://hi.events/licensing)**

Made with ☘️ in Ireland

</div>
