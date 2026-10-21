<div align="center">

<img src=".github/assets/readme-banner.png" alt="Hi.Events - Open-source event ticketing and management platform" width="100%">

# Hi.Events

### Plateforme open source de billetterie et de gestion d'événements

Vendez des billets en ligne pour vos conférences, soirées, concerts, club nights, ateliers et festivals.<br>
Auto-hébergé ou dans le cloud. Vos événements, votre marque, vos données.

[Essayer Cloud →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Démo en direct](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Documentation](https://hi.events/docs?utm_source=gh-readme) · [Entreprise et licences](https://hi.events/licensing?utm_source=gh-readme) · [Site web](https://hi.events?utm_source=gh-readme)

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
> **Hi.Events 2.0 est disponible** avec les événements récurrents, les places réservées, la billetterie sur place, la programmation et le planning,
> l'authentification unique, les permissions d'équipe et les clés API. [Découvrir les nouveautés →](https://github.com/HiEventsDev/hi.events/releases)

<br>

<img alt="Hi.Events event dashboard on desktop and an event page on mobile" src=".github/assets/readme-showcase.png" width="100%"/>

<br>

## Pourquoi Hi.Events ?

La plupart des plateformes de billetterie prélèvent des frais sur chaque billet et conservent les données de vos clients. **Hi.Events est une
alternative open source à Eventbrite, Tickettailor, Dice.fm et aux autres plateformes de billetterie** qui vous donne le contrôle total de votre
image de marque, de votre paiement et de vos données.

Des milliers d'organisateurs lui font confiance dans le monde entier : promoteurs de soirées, festivals, salles, associations
et organisateurs de conférences. Hébergez-le vous-même, ou confiez-nous son exploitation avec Hi.Events Cloud.

<br>

## Fonctionnalités

**📅 Événements**
- Événements ponctuels ou récurrents, avec prix, capacité et horaires par date
- Duplication d'un événement en un clic
- Lieux enregistrés et événements en ligne
- Éditeur de page d'accueil d'événement et pages organisateur personnalisées
- Pages de programmation et de planning

**🎟️ Billets et produits**
- Billets payants, gratuits, à don libre et par paliers
- Places réservées <sup>Enterprise</sup>
- Options supplémentaires et merchandising
- Billets masqués débloqués par code promo
- Limites par commande et périodes de vente programmées
- Capacité partagée et listes d'attente automatiques

**🛒 Vente**
- Intégrations personnalisables pour votre site : widgets de billetterie, boutons, listes d'événements, programmation et planning
- Codes promo et réductions
- Liens de suivi pour promoteurs et affiliés
- Questions d'inscription personnalisées
- Consentement marketing lors du paiement
- Suivi Meta, TikTok, Google Analytics et Tag Manager

**💳 Paiements**
- Versements sur votre propre compte Stripe
- Taxes et frais, inclus ou ajoutés au paiement
- Facturation et paiements hors ligne
- Remboursements complets et partiels

**🚪 Participants et accueil**
- Billetterie pour la vente sur place <sup>Enterprise</sup>
- Enregistrement avec la caméra du téléphone ou un scanner USB
- Listes d'enregistrement partageables avec compteurs en direct
- Messages aux participants et modèles d'e-mail personnalisés
- Billets PDF à vos couleurs
- Ajout manuel de participants
- Rapports de ventes, de taxes et de codes promo

<br>

## Conçu pour les organisations

**🔐 Sécurité et accès**
- Authentification unique
- Authentification à deux facteurs
- Rôles et permissions d'équipe
- Plusieurs organisateurs sur un même compte

**🔌 Intégrations**
- REST API et clés API
- Webhooks
- Exports CSV et XLSX

**🗄️ Vos données**
- Auto-hébergement avec Docker
- Suppression et anonymisation de compte
- Disponible en 20 langues

**✅ Testé**
- Une suite de tests complète est exécutée à chaque modification

<br>

## Démarrage rapide

Développé avec **Laravel 13** (PHP >=8.3) · **React 19** avec SSR · **TypeScript** · **PostgreSQL** · **Redis** · **Docker**.

### Déploiement en un clic

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
> **Utilisateurs Windows :** consultez `./docker/all-in-one/README.md` pour générer les clés.

Ouvrez `http://localhost:8123` et créez votre compte.

📖 [Guide d'installation complet](https://hi.events/docs/getting-started?utm_source=gh-readme)

> [!IMPORTANT]
> **Vous migrez depuis la 1.x ?** Lisez les [notes de version](https://github.com/HiEventsDev/hi.events/releases) avant la mise à jour.

### REST API

Créez vos clés API dans **Paramètres → Clés API**, où vous trouverez aussi la référence de l'API.

<br>

## Langues

Anglais, Deutsch, Français, Italiano, Nederlands, Español, Português, Português (Brasil),
Polski, Slovenčina, Magyar, Svenska, Suomi, Ελληνικά, Türkçe, Tiếng Việt, 简体中文, 繁體中文, 日本語 et 한국어.

Les corrections et les nouvelles langues sont les bienvenues : consultez le [guide de contribution](CONTRIBUTING.md).

<br>

## Hi.Events Cloud

Vous préférez ne pas l'héberger vous-même ? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** est la version
entièrement gérée, exploitée par l'équipe qui développe Hi.Events.

[Commencer →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Licences

Hi.Events est distribué sous licence **AGPL-3.0 avec des conditions supplémentaires**. Celles-ci exigent de conserver la mention "Powered by Hi.Events"
sur les pages et les e-mails générés par le logiciel. Consultez le fichier [LICENCE](LICENCE) pour le texte exact.

Les places réservées et la billetterie sur place relèvent de la [Hi.Events Enterprise Licence](backend/ee/LICENCE) et nécessitent une
licence pour une utilisation en production.

**Des licences commerciales sont disponibles** si vous souhaitez retirer la mention ou avez besoin de conditions adaptées à un
déploiement en marque blanche. [Options de licence](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Contribuer

Les contributions sont les bienvenues : consultez le [guide de contribution](CONTRIBUTING.md) pour commencer. Merci d'ouvrir une issue ou une
discussion avant d'entamer un travail important, afin que nous puissions nous accorder sur l'approche. Les contributeurs signent
un [CLA](CLA.md).

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Sécurité

Vous avez trouvé une vulnérabilité ? Merci de la signaler en privé à [security@hi.events](mailto:security@hi.events) plutôt que d'ouvrir
une issue publique. Consultez notre [politique de sécurité](SECURITY.md).

<br>

## Support

📖 [Documentation](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Discussions](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

Les nouvelles fonctionnalités et améliorations sont présentées sur
la [page des versions](https://github.com/HiEventsDev/hi.events/releases).

<br>

<div align="center">

**[Site web](https://hi.events)** · **[Documentation](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Licences](https://hi.events/licensing)**

Fait avec ☘️ en Irlande

</div>
