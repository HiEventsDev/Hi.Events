<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - Açık Kaynak Etkinlik Biletleme Platformu" width="100%">

# Hi.Events

### Açık kaynak etkinlik biletleme ve yönetim platformu

Konferanslar, gece hayatı etkinlikleri, konserler, kulüp geceleri, atölyeler ve festivaller için çevrimiçi bilet satın.  
Kendi sunucunuzda veya bulutta. Etkinlikleriniz, markanız, verileriniz.

[Bulut Sürümünü Deneyin →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Canlı Demo](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Dokümantasyon](https://hi.events/docs?utm_source=gh-readme) · [Web Sitesi](https://hi.events?utm_source=gh-readme)

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

## Neden Hi.Events?

Çoğu biletleme platformu bilet başına ücret alır ve verilerinizi kendi ekosistemine kilitler. **Hi.Events; Eventbrite,
Tickettailor, Dice.fm ve diğer biletleme platformlarına modern, açık kaynaklı bir alternatiftir** ve marka, ödeme akışı,
veri ve altyapı üzerinde tam kontrol isteyen organizatörler için tasarlanmıştır.

Dünya genelinde binlerce etkinlik organizatörü tarafından kullanılıyor — gece hayatı organizatörleri ve festivallerden
mekânlara, topluluk gruplarına ve konferans düzenleyicilerine kadar. İster kendiniz barındırın, ister Hi.Events Cloud
üzerinde işletmesini bize bırakın.

<br>

<img alt="Hi.Events Dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Özellikler

**🎟️ Biletleme ve satış** — ücretsiz, ücretli, bağış ve kademeli biletler · tekrar eden ve çok tarihli etkinlikler ·
tükendiğinde bekleme listeleri · promosyon kodları, kodla açılan ve gizli biletler dâhil · ürün eklentileri ve
kategorileri · vergi, ücret ve kapasite yönetimi

**🎨 Markalaşma ve özelleştirme** — kapak görseli, renkler ve tipografi için etkinlik sayfası tasarımcısı · markanıza
uygun organizatör sayfası · özelleştirilebilir PDF biletler · gömülebilir bilet bileşeni · SEO meta verisi denetimleri

**👥 Katılımcı yönetimi** — özel ödeme adımı soruları · gelişmiş arama, filtreleme ve CSV/XLSX dışa aktarma · tam ve
kısmi iadeler · toplu mesajlaşma · tarama kayıtlı QR kod ile giriş ve erişimi denetlenen giriş listeleri

**📊 Analitik ve büyüme** — satış panosu · iş ortağı (affiliate) takibi · günlük satış, ürün satışı, promosyon kodu,
gelir ve vergi raporları · giden webhook'lar

**⚙️ Operasyonlar** — çok kullanıcılı roller · Stripe Connect ödemeleri · çevrimdışı ödeme yöntemleri · otomatik
faturalandırma · çevrimiçi ve yüz yüze etkinlikler · çoklu dil desteği ·
[etkileşimli OpenAPI dokümantasyonu](#rest-api) ile tam REST API

<br>

## Hızlı Başlangıç

**Laravel 13** (PHP >=8.3) · SSR ile **React 19** · **TypeScript** · **PostgreSQL** · **Redis** · **Docker** ile
geliştirildi.

### Tek Tıkla Dağıtım

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# Anahtarları oluşturun (Linux/macOS)
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Windows kullanıcıları:** Anahtar oluşturma talimatları için `./docker/all-in-one/README.md` dosyasına bakın.

`http://localhost:8123` adresini açın ve hesabınızı oluşturun.

📖 [Tam kurulum kılavuzu](https://hi.events/docs/getting-started?utm_source=gh-readme)

### REST API

Hi.Events, dokümante edilmiş bir REST API ile gelir. Kendi sunucunuzda `/docs/api` adresinde etkileşimli OpenAPI
dokümantasyonunu yayınlamak için `.env` dosyanızda `API_DOCS_ENABLED=true` ayarını yapın veya spesifikasyonu şu komutla
dışa aktarın:

```bash
php artisan scramble:export
```

<br>

## Hi.Events Cloud

Kendiniz barındırmak istemiyor musunuz? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)**,
bu deponun tümüyle yönetilen sürümüdür — kurulum yok, otomatik güncellemeler ve yönetilen altyapı; Hi.Events'i geliştiren
ekip tarafından işletilir.

[Hemen başlayın →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Lisanslama

Hi.Events, **ek koşullarla birlikte AGPL-3.0** kapsamında lisanslanmıştır. Ek koşullar, yazılımın ürettiği sayfalarda ve
e-postalarda "Powered by Hi.Events" atfının korunmasını gerektirir — tam metin için [LICENCE](LICENCE) dosyasına bakın.

Atfı kaldırmak isterseniz veya beyaz etiketli bir dağıtıma uygun koşullara ihtiyacınız varsa **ticari lisanslar
mevcuttur**. [Lisanslama seçenekleri](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Katkıda Bulunma

Katkılar memnuniyetle karşılanır — başlamak için [katkı kılavuzuna](CONTRIBUTING.md) göz atın. Kapsamlı bir çalışmaya
başlamadan önce yaklaşımda anlaşabilmemiz için lütfen bir issue veya tartışma açın. Katkıda bulunanlar bir [CLA](CLA.md)
imzalar.

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Güvenlik

Bir güvenlik açığı mı buldunuz? Lütfen herkese açık bir issue açmak yerine
[security@hi.events](mailto:security@hi.events) adresine özel olarak bildirin.
[Güvenlik politikamıza](SECURITY.md) göz atın.

<br>

## Destek

📖 [Dokümantasyon](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Tartışmalar](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

Yeni özellikler ve iyileştirmeler [sürümler sayfasında](https://github.com/HiEventsDev/hi.events/releases) listelenir.

<br>

<div align="center">

**[Web Sitesi](https://hi.events)** · **[Dokümantasyon](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Lisanslama](https://hi.events/licensing)**

Made with ☘️ in Ireland

</div>
