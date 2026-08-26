<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - Plateforme Open Source de Billetterie d'Événements" width="100%">

# Hi.Events

### Plateforme open source de billetterie et gestion d'événements

Vendez des billets en ligne pour des conférences, événements nocturnes, concerts, soirées en club, ateliers et festivals.  
Auto-hébergé ou cloud. Vos événements, votre marque, vos données.

[Essayer le Cloud →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Démo en Direct](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Documentation](https://hi.events/docs?utm_source=gh-readme) · [Site Web](https://hi.events?utm_source=gh-readme)

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

## Pourquoi Hi.Events ?

La plupart des plateformes de billetterie facturent des frais par billet et enferment vos données dans leur écosystème.
**Hi.Events est une alternative moderne et open source à Eventbrite, Tickettailor, Dice.fm et aux autres plateformes de
billetterie**, pour les organisateurs qui veulent garder le contrôle total de leur image de marque, de leur tunnel
d'achat, de leurs données et de leur infrastructure.

Adopté par des milliers d'organisateurs à travers le monde — des promoteurs de soirées et festivals aux salles, groupes
associatifs et organisateurs de conférences. Hébergez-le vous-même, ou laissez-nous le faire pour vous sur Hi.Events
Cloud.

<br>

<img alt="Hi.Events Dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Fonctionnalités

**🎟️ Billetterie & ventes** — billets gratuits, payants, à prix libre et par paliers · événements récurrents et à dates
multiples · listes d'attente en cas de rupture · codes promo, y compris billets cachés et réservés aux codes promo ·
options additionnelles et catégories de produits · gestion des taxes, des frais et des capacités

**🎨 Marque & personnalisation** — éditeur de page d'événement pour l'image de couverture, les couleurs et la
typographie · page organisateur à votre marque · billets PDF personnalisables · widget de billetterie intégrable ·
contrôle des métadonnées SEO

**👥 Gestion des participants** — questions personnalisées au moment de la commande · recherche, filtrage et export
CSV/XLSX avancés · remboursements complets et partiels · messagerie groupée · check-in par QR code avec journaux de
scan et listes de check-in à accès contrôlé

**📊 Analytique & croissance** — tableau de bord des ventes · suivi des affiliés · rapports de ventes quotidiennes, de
ventes par produit, de codes promo, de revenus et de taxes · webhooks sortants

**⚙️ Opérations** — rôles multi-utilisateurs · paiements Stripe Connect · méthodes de paiement hors ligne · facturation
automatique · événements en ligne et en présentiel · support multilingue · API REST complète avec
[documentation OpenAPI interactive](#rest-api)

<br>

## Démarrage Rapide

Construit avec **Laravel 13** (PHP >=8.3) · **React 19** avec SSR · **TypeScript** · **PostgreSQL** · **Redis** ·
**Docker**.

### Déploiement en Un Clic

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# Générer les clés (Linux/macOS)
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Utilisateurs Windows :** Consultez `./docker/all-in-one/README.md` pour les instructions de génération des clés.

Ouvrez `http://localhost:8123` et créez votre compte.

📖 [Guide d'installation complet](https://hi.events/docs/getting-started?utm_source=gh-readme)

### REST API

Hi.Events fournit une API REST documentée. Définissez `API_DOCS_ENABLED=true` dans votre `.env` pour servir la
documentation OpenAPI interactive sur `/docs/api` de votre propre instance, ou exportez la spécification avec :

```bash
php artisan scramble:export
```

<br>

## Hi.Events Cloud

Vous préférez ne pas héberger vous-même ? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)**
est la version entièrement gérée de ce dépôt — aucune configuration, mises à jour automatiques et infrastructure gérée,
opérée par l'équipe qui développe Hi.Events.

[Commencer →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Licence

Hi.Events est distribué sous **AGPL-3.0 avec des conditions supplémentaires**. Ces conditions imposent de conserver la
mention « Powered by Hi.Events » sur les pages et e-mails générés par le logiciel — voir [LICENCE](LICENCE) pour le
texte exact.

**Des licences commerciales sont disponibles** si vous souhaitez retirer cette mention ou avez besoin de conditions
adaptées à un déploiement en marque blanche.
[Options de licence](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Contribuer

Les contributions sont les bienvenues — consultez le [guide de contribution](CONTRIBUTING.md) pour commencer. Merci
d'ouvrir une issue ou une discussion avant d'entamer un travail conséquent afin que nous puissions nous accorder sur
l'approche. Les contributeurs signent un [CLA](CLA.md).

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Sécurité

Vous avez trouvé une vulnérabilité ? Merci de la signaler en privé à [security@hi.events](mailto:security@hi.events)
plutôt que d'ouvrir une issue publique. Consultez notre [politique de sécurité](SECURITY.md).

<br>

## Support

📖 [Documentation](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Discussions](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

Les nouvelles fonctionnalités et améliorations sont listées sur
la [page des releases](https://github.com/HiEventsDev/hi.events/releases).

<br>

<div align="center">

**[Site Web](https://hi.events)** · **[Documentation](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Licences](https://hi.events/licensing)**

Made with ☘️ in Ireland

</div>
