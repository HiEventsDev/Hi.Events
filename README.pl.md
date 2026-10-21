<div align="center">

<img src=".github/assets/readme-banner.png" alt="Hi.Events - Open-source event ticketing and management platform" width="100%">

# Hi.Events

### Open source'owa platforma do sprzedaży biletów i zarządzania wydarzeniami

Sprzedawaj bilety online na konferencje, imprezy, koncerty, noce klubowe, warsztaty i festiwale.<br>
Na własnym serwerze lub w chmurze. Twoje wydarzenia, Twoja marka, Twoje dane.

[Wypróbuj Cloud →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Demo na żywo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Dokumentacja](https://hi.events/docs?utm_source=gh-readme) · [Enterprise i licencje](https://hi.events/licensing?utm_source=gh-readme) · [Strona internetowa](https://hi.events?utm_source=gh-readme)

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
> **Hi.Events 2.0 już jest**, a z nim wydarzenia cykliczne, rezerwacja miejsc, kasa biletowa, line-upy i harmonogramy, logowanie
> jednokrotne, uprawnienia zespołu i klucze API. [Zobacz, co nowego →](https://github.com/HiEventsDev/hi.events/releases)

<br>

<img alt="Hi.Events event dashboard on desktop and an event page on mobile" src=".github/assets/readme-showcase.png" width="100%"/>

<br>

## Dlaczego Hi.Events?

Większość platform biletowych pobiera prowizję od każdego biletu i zatrzymuje dane Twoich klientów. **Hi.Events to open source'owa
alternatywa dla Eventbrite, Tickettailor, Dice.fm i innych platform biletowych**, która daje Ci pełną kontrolę nad
marką, procesem zakupu i danymi.

Zaufały nam tysiące organizatorów wydarzeń na całym świecie — od promotorów imprez i festiwali po obiekty, społeczności
i organizatorów konferencji. Uruchom Hi.Events samodzielnie albo pozwól, że zajmiemy się tym za Ciebie w Hi.Events Cloud.

<br>

## Funkcje

**📅 Wydarzenia**
- Wydarzenia jednorazowe i cykliczne, z cenami, pojemnością i godzinami dla każdego terminu
- Duplikowanie wydarzenia jednym kliknięciem
- Zapisane miejsca i wydarzenia online
- Kreator strony wydarzenia i strony organizatora z Twoją marką
- Strony z line-upem i harmonogramem

**🎟️ Bilety i produkty**
- Bilety płatne, darmowe, z dobrowolną wpłatą i w pulach cenowych
- Rezerwacja miejsc <sup>Enterprise</sup>
- Dodatki i merch
- Ukryte bilety odblokowywane kodami promocyjnymi
- Limity na zamówienie i zaplanowane okna sprzedaży
- Wspólna pojemność i automatyczne listy oczekujących

**🛒 Sprzedaż**
- Konfigurowalne elementy do osadzenia na Twojej stronie: widżety biletowe, przyciski, listy wydarzeń, line-upy i harmonogramy
- Kody promocyjne i rabaty
- Linki śledzące dla promotorów i partnerów
- Własne pytania rejestracyjne
- Zgoda marketingowa przy płatności
- Śledzenie przez Meta, TikTok, Google Analytics i Tag Manager

**💳 Płatności**
- Wypłaty na Twoje własne konto Stripe
- Podatki i opłaty wliczone w cenę lub doliczane przy płatności
- Faktury i płatności offline
- Zwroty pełne i częściowe

**🚪 Uczestnicy i wejście**
- Kasa biletowa do sprzedaży przy wejściu <sup>Enterprise</sup>
- Weryfikacja wejść aparatem telefonu lub skanerem USB
- Udostępniane listy wejść z licznikami na żywo
- Wiadomości do uczestników i własne szablony e-maili
- Bilety PDF z Twoją marką
- Ręczne dodawanie uczestników
- Raporty sprzedaży, podatków i kodów promocyjnych

<br>

## Stworzony dla organizacji

**🔐 Bezpieczeństwo i dostęp**
- Logowanie jednokrotne
- Uwierzytelnianie dwuskładnikowe
- Role i uprawnienia w zespole
- Wielu organizatorów na jednym koncie

**🔌 Integracje**
- REST API i klucze API
- Webhooki
- Eksport do CSV i XLSX

**🗄️ Twoje dane**
- Własny hosting z Dockerem
- Usuwanie i anonimizacja kont
- Dostępny w 20 językach

**✅ Przetestowany**
- Rozbudowany zestaw testów uruchamiany przy każdej zmianie

<br>

## Szybki start

Zbudowany na **Laravel 13** (PHP >=8.3) · **React 19** z SSR · **TypeScript** · **PostgreSQL** · **Redis** · **Docker**.

### Wdrożenie jednym kliknięciem

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
> **Użytkownicy Windows:** instrukcję generowania kluczy znajdziesz w `./docker/all-in-one/README.md`.

Otwórz `http://localhost:8123` i załóż konto.

📖 [Pełny przewodnik instalacji](https://hi.events/docs/getting-started?utm_source=gh-readme)

> [!IMPORTANT]
> **Aktualizujesz z wersji 1.x?** Przed aktualizacją przeczytaj [informacje o wydaniu](https://github.com/HiEventsDev/hi.events/releases).

### REST API

Klucze API utworzysz w sekcji **Ustawienia → Klucze API**, gdzie znajdziesz też dokumentację API.

<br>

## Języki

English, Deutsch, Français, Italiano, Nederlands, Español, Português, Português (Brasil),
Polski, Slovenčina, Magyar, Svenska, Suomi, Ελληνικά, Türkçe, Tiếng Việt, 简体中文, 繁體中文, 日本語 i 한국어.

Poprawki i nowe języki są mile widziane — zajrzyj do [przewodnika dla współtwórców](CONTRIBUTING.md).

<br>

## Hi.Events Cloud

Nie chcesz utrzymywać własnego serwera? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** to w pełni
zarządzana wersja, prowadzona przez zespół, który tworzy Hi.Events.

[Zacznij teraz →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Licencjonowanie

Hi.Events jest udostępniany na licencji **AGPL-3.0 z dodatkowymi warunkami**. Dodatkowe warunki wymagają zachowania informacji "Powered by Hi.Events"
na stronach i w e-mailach generowanych przez oprogramowanie — dokładne brzmienie znajdziesz w pliku [LICENCE](LICENCE).

Rezerwacja miejsc i kasa biletowa są objęte licencją [Hi.Events Enterprise Licence](backend/ee/LICENCE) i do użytku produkcyjnego wymagają
licencji.

**Dostępne są licencje komercyjne**, jeśli chcesz usunąć informację o autorstwie lub potrzebujesz warunków dla wdrożenia
white-label. [Opcje licencjonowania](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Współtworzenie

Zapraszamy do współtworzenia — na początek zajrzyj do [przewodnika dla współtwórców](CONTRIBUTING.md). Zanim zaczniesz większą pracę, otwórz zgłoszenie
lub dyskusję, abyśmy mogli uzgodnić podejście. Współtwórcy podpisują
[CLA](CLA.md).

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Bezpieczeństwo

Znalazłeś lukę w zabezpieczeniach? Zgłoś ją prywatnie na adres [security@hi.events](mailto:security@hi.events), zamiast otwierać
publiczne zgłoszenie. Zobacz naszą [politykę bezpieczeństwa](SECURITY.md).

<br>

## Wsparcie

📖 [Dokumentacja](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [Zgłoszenia na GitHubie](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Dyskusje](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

Nowe funkcje i ulepszenia znajdziesz na
[stronie wydań](https://github.com/HiEventsDev/hi.events/releases).

<br>

<div align="center">

**[Strona internetowa](https://hi.events)** · **[Dokumentacja](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Licencjonowanie](https://hi.events/licensing)**

Stworzone z ☘️ w Irlandii

</div>
