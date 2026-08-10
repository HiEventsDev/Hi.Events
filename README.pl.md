<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - Otwarta platforma sprzedaży biletów do wydarzeń" width="100%">

# Hi.Events

### Otwarta platforma do zarządzania wydarzeniami i sprzedaży biletów online

Sprzedawaj bilety online na konferencje, imprezy nocne, koncerty, imprezy klubowe, warsztaty i festiwale.  
Samodzielnie hostowana lub w chmurze. Twoje wydarzenia, Twoja marka, Twoje dane.

[Wypróbuj w chmurze →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Demo na żywo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Dokumentacja](https://hi.events/docs?utm_source=gh-readme) · [Strona internetowa](https://hi.events?utm_source=gh-readme)

[![Licencja: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![Wydanie GitHub](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![Gwiazdki GitHub](https://img.shields.io/github/stars/HiEventsDev/hi.events?style=flat)](https://github.com/HiEventsDev/hi.events/stargazers)
[![Pobrania Docker](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)
[![Testy E2E](https://github.com/HiEventsDev/hi.events/actions/workflows/e2e.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/e2e.yml)

<a href="https://trendshift.io/repositories/10563" target="_blank">
  <img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" width="250" height="55"/>
</a>

<p>
<a href="README.de.md">Deutsch</a> · <a href="README.pt.md">Português</a> · <a href="README.fr.md">Français</a> · <a href="README.it.md">Italiano</a> · <a href="README.nl.md">Nederlands</a> · <a href="README.es.md">Español</a> · <a href="README.zh-cn.md">中文</a> · <a href="README.zh-hk.md">繁體中文</a> · <a href="README.ja.md">日本語</a> · <a href="README.vi.md">Tiếng Việt</a> · <a href="README.tr.md">Türkçe</a> · <a href="README.hu.md">Magyar</a> · <a href="README.pl.md">Polski</a> · <a href="README.sk.md">Slovenčina</a> · <a href="README.el.md">Ελληνικά</a>
</p>

</div>

<br>

## Dlaczego Hi.Events?

Większość platform biletowych pobiera prowizję od każdego biletu i zamyka Twoje dane w swoim ekosystemie. **Hi.Events to
nowoczesna, otwartoźródłowa alternatywa dla Eventbrite, Tickettailor, Dice.fm i innych platform biletowych** dla
organizatorów, którzy chcą mieć pełną kontrolę nad marką, procesem zakupu, danymi i infrastrukturą.

Korzystają z niego tysiące organizatorów na całym świecie — od promotorów imprez nocnych i festiwali po kluby, grupy
społecznościowe i organizatorów konferencji. Hostuj samodzielnie albo pozwól nam zająć się tym w Hi.Events Cloud.

<br>

<img alt="Hi.Events Dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Funkcje

**🎟️ Sprzedaż biletów** — bilety bezpłatne, płatne, darowizny i wielopoziomowe · wydarzenia cykliczne i wielodniowe ·
listy oczekujących po wyprzedaniu · kody promocyjne, w tym bilety ukryte i odblokowywane kodem · dodatki i kategorie
produktów · zarządzanie podatkami, opłatami i pojemnością

**🎨 Branding i dostosowanie** — kreator strony wydarzenia dla obrazu tła, kolorów i typografii · strona organizatora w
Twojej identyfikacji wizualnej · konfigurowalne bilety PDF · osadzalny widżet sprzedaży · kontrola metadanych SEO

**👥 Zarządzanie uczestnikami** — własne pytania w procesie zakupu · zaawansowane wyszukiwanie, filtrowanie i eksport
CSV/XLSX · pełne i częściowe zwroty · masowa wysyłka wiadomości · odprawa kodem QR z logami skanowania i listami
odprawy z kontrolą dostępu

**📊 Analityka i wzrost** — pulpit sprzedaży · śledzenie afiliacji · raporty sprzedaży dziennej, sprzedaży produktów,
kodów promocyjnych, przychodów i podatków · webhooki wychodzące

**⚙️ Operacje** — role dla wielu użytkowników · płatności Stripe Connect · offline'owe metody płatności · automatyczne
fakturowanie · wydarzenia online i stacjonarne · obsługa wielu języków · pełne REST API z
[interaktywną dokumentacją OpenAPI](#rest-api)

<br>

## Szybki start

Zbudowane na **Laravel 13** (PHP >=8.3) · **React 19** z SSR · **TypeScript** · **PostgreSQL** · **Redis** · **Docker**.

### Wdrażanie jednym kliknięciem

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# Wygeneruj klucze (Linux/macOS)
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Użytkownicy Windows:** instrukcje generowania kluczy znajdziesz w `./docker/all-in-one/README.md`.

Otwórz `http://localhost:8123` i załóż konto.

📖 [Pełny przewodnik instalacji](https://hi.events/docs/getting-started?utm_source=gh-readme)

### REST API

Hi.Events udostępnia udokumentowane REST API. Ustaw `API_DOCS_ENABLED=true` w pliku `.env`, aby serwować interaktywną
dokumentację OpenAPI pod adresem `/docs/api` we własnej instancji, albo wyeksportuj specyfikację poleceniem:

```bash
php artisan scramble:export
```

<br>

## Hi.Events Cloud

Wolisz nie hostować samodzielnie? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** to w
pełni zarządzana wersja tego repozytorium — zero konfiguracji, automatyczne aktualizacje i zarządzana infrastruktura,
prowadzona przez zespół, który tworzy Hi.Events.

[Zacznij →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Licencjonowanie

Hi.Events jest udostępniane na licencji **AGPL-3.0 z dodatkowymi warunkami**. Dodatkowe warunki wymagają zachowania
informacji „Powered by Hi.Events” na stronach i w wiadomościach e-mail generowanych przez oprogramowanie — dokładne
brzmienie znajdziesz w pliku [LICENCE](LICENCE).

**Dostępne są licencje komercyjne**, jeśli chcesz usunąć tę informację lub potrzebujesz warunków odpowiednich dla
wdrożenia white-label. [Opcje licencjonowania](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Wkład

Zapraszamy do współtworzenia projektu — zacznij od [przewodnika dla kontrybutorów](CONTRIBUTING.md). Przed rozpoczęciem
większych prac otwórz issue lub dyskusję, abyśmy mogli uzgodnić podejście. Osoby współtworzące podpisują [CLA](CLA.md).

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Bezpieczeństwo

Znalazłeś podatność? Zgłoś ją prywatnie na adres [security@hi.events](mailto:security@hi.events) zamiast otwierać
publiczne issue. Zobacz naszą [politykę bezpieczeństwa](SECURITY.md).

<br>

## Wsparcie

📖 [Dokumentacja](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Dyskusje](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

Nowe funkcje i usprawnienia znajdziesz na
[stronie wydań](https://github.com/HiEventsDev/hi.events/releases).

<br>

<div align="center">

**[Strona internetowa](https://hi.events)** · **[Dokumentacja](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Licencjonowanie](https://hi.events/licensing)**

Made with ☘️ in Ireland

</div>
