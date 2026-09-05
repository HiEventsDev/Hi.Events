<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - Nyílt forráskódú eseménykezelő és jegyértékesítő platform" width="100%">

# Hi.Events

### Nyílt forráskódú eseménykezelő és jegyértékesítő platform

Adjon el jegyeket online konferenciákra, szórakozóhelyi eseményekre, koncertekre, klubestekre, workshopokra és fesztiválokra.  
Saját szerveren vagy felhőben. Az Ön eseményei, az Ön márkája, az Ön adatai.

[Próbálja ki a felhőt →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Élő demó](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Dokumentáció](https://hi.events/docs?utm_source=gh-readme) · [Weboldal](https://hi.events?utm_source=gh-readme)

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![GitHub Release](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![GitHub Stars](https://img.shields.io/github/stars/HiEventsDev/hi.events?style=flat)](https://github.com/HiEventsDev/hi.events/stargazers)
[![Docker Pulls](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)
[![E2E Tests](https://github.com/HiEventsDev/hi.events/actions/workflows/e2e.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/e2e.yml)

<a href="https://trendshift.io/repositories/10563" target="_blank">
  <img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" width="250" height="55"/>
</a>

<p>
<a href="README.de.md">Deutsch</a> · <a href="README.pt.md">Português</a> · <a href="README.pt-br.md">Português do Brasil</a> · <a href="README.fr.md">Français</a> · <a href="README.it.md">Italiano</a> · <a href="README.nl.md">Nederlands</a> · <a href="README.es.md">Español</a> · <a href="README.zh-cn.md">中文</a> · <a href="README.zh-hk.md">繁體中文</a> · <a href="README.ja.md">日本語</a> · <a href="README.vi.md">Tiếng Việt</a> · <a href="README.tr.md">Türkçe</a> · <a href="README.hu.md">Magyar</a> · <a href="README.pl.md">Polski</a> · <a href="README.sk.md">Slovenčina</a> · <a href="README.el.md">Ελληνικά</a>

</p>

</div>

<br>

## Miért a Hi.Events?

A legtöbb jegyértékesítő platform jegyenkénti díjat számol fel, és a saját ökoszisztémájába zárja az adatait.
**A Hi.Events az Eventbrite, a Tickettailor, a Dice.fm és a többi jegyértékesítő platform modern, nyílt forráskódú
alternatívája** azoknak a szervezőknek, akik teljes kontrollt szeretnének a márkamegjelenés, a vásárlási folyamat, az
adatok és az infrastruktúra felett.

Világszerte több ezer szervező használja – szórakozóhelyi promóterektől és fesztiváloktól a helyszíneken, közösségi
csoportokon át a konferenciaszervezőkig. Üzemeltesse saját maga, vagy bízza ránk a Hi.Events Cloudban.

<br>

<img alt="Hi.Events Dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Funkciók

**🎟️ Jegyértékesítés** – ingyenes, fizetős, adomány alapú és sávos jegyek · ismétlődő és több időpontos események ·
várólista teltház esetén · promóciós kódok, beleértve a kóddal nyíló és rejtett jegyeket · termékkiegészítők és
kategóriák · adó-, díj- és kapacitáskezelés

**🎨 Márkaépítés és testreszabás** – eseményoldal-szerkesztő borítóképhez, színekhez és tipográfiához · saját márkás
szervezői oldal · testreszabható PDF-jegyek · beágyazható jegyértékesítő widget · SEO-metaadatok beállítása

**👥 Résztvevőkezelés** – egyedi kérdések a vásárlás során · fejlett keresés, szűrés és CSV/XLSX exportálás · teljes és
részleges visszatérítések · tömeges üzenetküldés · QR-kódos beléptetés szkennelési naplóval és hozzáférés-szabályozott
beléptetési listákkal

**📊 Elemzés és növekedés** – értékesítési irányítópult · partnerkövetés · napi értékesítési, termékértékesítési,
promóciós kód-, bevételi és adóriportok · kimenő webhookok

**⚙️ Működés** – többfelhasználós szerepkörök · Stripe Connect fizetések · offline fizetési módok · automatikus
számlázás · online és személyes események · többnyelvű támogatás · teljes REST API
[interaktív OpenAPI-dokumentációval](#rest-api)

<br>

## Gyors kezdés

**Laravel 13** (PHP >=8.3) · **React 19** SSR-rel · **TypeScript** · **PostgreSQL** · **Redis** · **Docker**
alapokon készült.

### Egy kattintásos telepítés

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# Kulcsok generálása (Linux/macOS)
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Windows-felhasználók:** A kulcsgenerálás lépéseit lásd a `./docker/all-in-one/README.md` fájlban.

Nyissa meg a `http://localhost:8123` címet, és hozza létre a fiókját.

📖 [Teljes telepítési útmutató](https://hi.events/docs/getting-started?utm_source=gh-readme)

### REST API

A Hi.Events dokumentált REST API-t tartalmaz. Állítsa be az `API_DOCS_ENABLED=true` értéket a `.env` fájlban, hogy a
saját példányán a `/docs/api` címen interaktív OpenAPI-dokumentációt szolgáljon ki, vagy exportálja a specifikációt:

```bash
php artisan scramble:export
```

<br>

## Hi.Events Cloud

Nem szeretne saját szerveren üzemeltetni? A **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)**
ennek a tárolónak a teljesen menedzselt változata – nulla beállítás, automatikus frissítések és menedzselt
infrastruktúra, azoktól, akik a Hi.Eventset fejlesztik.

[Kezdés →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Licencelés

A Hi.Events **AGPL-3.0 licenc alatt, kiegészítő feltételekkel** érhető el. A kiegészítő feltételek előírják, hogy a
szoftver által generált oldalakon és e-mailekben meg kell tartani a „Powered by Hi.Events” feltüntetést – a pontos
szöveget lásd a [LICENCE](LICENCE) fájlban.

**Kereskedelmi licencek is elérhetők**, ha el szeretné távolítani a feltüntetést, vagy white-label telepítéshez
illeszkedő feltételekre van szüksége. [Licencelési lehetőségek](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Közreműködés

Szívesen fogadjuk a hozzájárulásokat – az induláshoz olvassa el a [közreműködési útmutatót](CONTRIBUTING.md). Nagyobb
munka megkezdése előtt nyisson egy issue-t vagy beszélgetést, hogy egyeztetni tudjuk a megközelítést. A közreműködők
[CLA](CLA.md)-t írnak alá.

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Biztonság

Sebezhetőséget talált? Kérjük, jelentse bizalmasan a [security@hi.events](mailto:security@hi.events) címen, ahelyett
hogy nyilvános issue-t nyitna. Lásd a [biztonsági szabályzatunkat](SECURITY.md).

<br>

## Támogatás

📖 [Dokumentáció](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Beszélgetések](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

Az új funkciók és fejlesztések a [kiadások oldalán](https://github.com/HiEventsDev/hi.events/releases) találhatók.

<br>

<div align="center">

**[Weboldal](https://hi.events)** · **[Dokumentáció](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Licencelés](https://hi.events/licensing)**

Made with ☘️ in Ireland

</div>
