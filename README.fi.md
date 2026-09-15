<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - avoimen lähdekoodin lippu- ja tapahtumanhallinta-alusta" width="100%">

# Hi.Events

### Avoimen lähdekoodin tapahtumalippu- ja tapahtumanhallinta-alusta

Myy lippuja verkossa konferensseihin, yöelämätapahtumiin, konsertteihin, klubitapahtumiin, työpajoihin ja festivaaleille.  
Itse ylläpidettävä tai pilvipalvelu. Tapahtumasi, brändisi, tietosi.

[Kokeile pilvipalvelua →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Live-esittely](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Dokumentaatio](https://hi.events/docs?utm_source=gh-readme) · [Verkkosivusto](https://hi.events?utm_source=gh-readme)

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![GitHub Release](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![GitHub Stars](https://img.shields.io/github/stars/HiEventsDev/hi.events?style=flat)](https://github.com/HiEventsDev/hi.events/stargazers)
[![Docker Pulls](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)
[![E2E Tests](https://github.com/HiEventsDev/hi.events/actions/workflows/e2e.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/e2e.yml)

<a href="https://trendshift.io/repositories/10563" target="_blank">
  <img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" width="250" height="55"/>
</a>

<p>
<a href="README.de.md">Deutsch</a> · <a href="README.pt.md">Português</a> · <a href="README.fr.md">Français</a> · <a href="README.it.md">Italiano</a> · <a href="README.nl.md">Nederlands</a> · <a href="README.es.md">Español</a> · <a href="README.zh-cn.md">中文</a> · <a href="README.zh-hk.md">繁體中文</a> · <a href="README.ja.md">日本語</a> · <a href="README.vi.md">Tiếng Việt</a> · <a href="README.tr.md">Türkçe</a> · <a href="README.hu.md">Magyar</a> · <a href="README.pl.md">Polski</a> · <a href="README.sk.md">Slovenčina</a> · <a href="README.el.md">Ελληνικά</a>
</p>

</div>

<br>

## Miksi Hi.Events?

Useimmat lippualustat veloittavat maksun jokaisesta lipusta ja sitovat tietosi omaan ekosysteemiinsä. **Hi.Events on
nykyaikainen, avoimen lähdekoodin vaihtoehto Eventbritelle, Tickettailorille, Dice.fm:lle ja muille lippualustoille**
järjestäjille, jotka haluavat täyden hallinnan brändistä, kassasta, tiedoista ja infrastruktuurista.

Tuhannet tapahtumajärjestäjät ympäri maailman luottavat siihen — yöelämän promoottoreista ja festivaaleista
tapahtumapaikkoihin, yhteisöryhmiin ja konferenssijärjestäjiin. Ylläpidä sitä itse tai anna meidän ylläpitää sitä
puolestasi Hi.Events Cloudissa.

<br>

<img alt="Hi.Eventsin hallintapaneeli" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Ominaisuudet

**🎟️ Lipunmyynti ja myynti** — maksuttomat, maksulliset, lahjoitus- ja porrastetut liput · toistuvat ja usean päivän
tapahtumat · loppuunmyytyjen lippujen jonotuslistat · tarjouskoodit, mukaan lukien tarjouksella avattavat ja piilotetut
liput · tuotteiden lisäosat ja kategoriat · verojen, maksujen ja kapasiteetin hallinta

**🎨 Brändäys ja mukauttaminen** — tapahtuman etusivun suunnittelutyökalu kansikuvalle, väreille ja typografialle ·
brändätty järjestäjän etusivu · mukautettavat PDF-liput · upotettava lippuwidget · SEO-metatietojen hallinta

**👥 Osallistujien hallinta** — mukautetut kassakysymykset · tarkennettu haku, suodatus ja CSV/XLSX-vienti · täydet ja
osittaiset hyvitykset · joukkoviestit · QR-kirjautuminen tarkistuslokeilla ja käyttöoikeuksin rajatuilla tarkistuslistoilla

**📊 Analytiikka ja kasvu** — myynnin hallintapaneeli · kumppaniseuranta · päivämyynnin, tuotemyynnin, tarjouskoodien,
tulojen ja verojen raportit · lähtevät webhookit

**⚙️ Toiminnot** — monen käyttäjän roolit · Stripe Connect -maksut · offline-maksutavat · automaattinen laskutus ·
verkko- ja lähitapahtumat · monikielisyys · täydellinen REST API ja
[interaktiivinen OpenAPI-dokumentaatio](#rest-rajapinta)

<br>

## Pika-aloitus

Rakennettu teknologioilla **Laravel 13** (PHP >=8.3) · **React 19** SSR-tuella · **TypeScript** · **PostgreSQL** · **Redis** · **Docker**.

### Käyttöönotto yhdellä napsautuksella

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# Luo avaimet (Linux/macOS)
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Windows-käyttäjät:** Katso avainten luontiohjeet tiedostosta `./docker/all-in-one/README.md`.

Avaa `http://localhost:8123` ja luo käyttäjätilisi.

📖 [Täydellinen asennusopas](https://hi.events/docs/getting-started?utm_source=gh-readme)

### REST-rajapinta

Hi.Events sisältää dokumentoidun REST-rajapinnan. Aseta `.env`-tiedostossa `API_DOCS_ENABLED=true`, jotta interaktiivinen
OpenAPI-dokumentaatio on saatavilla oman instanssisi osoitteessa `/docs/api`, tai vie määritys komennolla:

```bash
php artisan scramble:export
```

<br>

## Hi.Events Cloud

Etkö halua ylläpitää palvelua itse? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** on
tämän repositorion täysin hallinnoitu versio — ei asennusta, automaattiset päivitykset ja hallinnoitu infrastruktuuri,
Hi.Eventsiä kehittävän tiimin ylläpitämänä.

[Aloita käyttö →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Lisensointi

Hi.Events on lisensoitu **AGPL-3.0-lisenssillä lisäehdoin**. Lisäehdot edellyttävät, että ohjelmiston luomilla sivuilla
ja sähköposteissa säilytetään "Powered by Hi.Events" -maininta — tarkka sanamuoto löytyy [LICENCE](LICENCE)-tiedostosta.

**Kaupallisia lisenssejä on saatavilla**, jos haluat poistaa maininnan tai tarvitset white label -käyttöönottoon sopivat
ehdot. [Lisenssivaihtoehdot](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Osallistuminen kehitykseen

Osallistuminen on tervetullutta — tutustu aloittamiseen [osallistumisoppaassa](CONTRIBUTING.md). Avaa issue tai keskustelu
ennen merkittävän työn aloittamista, jotta voimme sopia lähestymistavasta. Osallistujat allekirjoittavat
[CLA-sopimuksen](CLA.md).

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Tietoturva

Löysitkö haavoittuvuuden? Ilmoita siitä yksityisesti osoitteeseen [security@hi.events](mailto:security@hi.events) sen
sijaan, että avaisit julkisen issuen. Tutustu [tietoturvakäytäntöömme](SECURITY.md).

<br>

## Tuki

📖 [Documentation](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Discussions](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

Uudet ominaisuudet ja parannukset on lueteltu
[julkaisusivulla](https://github.com/HiEventsDev/hi.events/releases).

<br>

<div align="center">

**[Verkkosivusto](https://hi.events)** · **[Dokumentaatio](https://hi.events/docs)** · **[Pilvipalvelu](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Lisensointi](https://hi.events/licensing)**

Tehty ☘️ Irlannissa

</div>
