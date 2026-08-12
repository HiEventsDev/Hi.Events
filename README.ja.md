<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - オープンソースイベントチケット販売プラットフォーム" width="100%">

# Hi.Events

### オープンソースイベントチケット販売・管理プラットフォーム

カンファレンス、ナイトライフイベント、コンサート、クラブナイト、ワークショップ、フェスティバルのチケットをオンラインで販売。  
セルフホスティングまたはクラウド。あなたのイベント、あなたのブランド、あなたのデータ。

[クラウド版を試す →](https://app.hi.events/auth/register?utm_source=gh-readme) · [ライブデモ](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [ドキュメント](https://hi.events/docs?utm_source=gh-readme) · [ウェブサイト](https://hi.events?utm_source=gh-readme)

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![GitHub Release](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![GitHub Stars](https://img.shields.io/github/stars/HiEventsDev/hi.events?style=flat)](https://github.com/HiEventsDev/hi.events/stargazers)
[![Docker Pulls](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)
[![E2E Tests](https://github.com/HiEventsDev/hi.events/actions/workflows/e2e.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/e2e.yml)

<a href="https://trendshift.io/repositories/10563" target="_blank">
  <img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" width="250" height="55"/>
</a>

<p>
<a href="README.de.md">Deutsch</a> · <a href="README.pt.md">Português</a> · <a href="README.fr.md">Français</a> · <a href="README.it.md">Italiano</a> · <a href="README.nl.md">Nederlands</a> · <a href="README.es.md">Español</a> · <a href="README.zh-cn.md">中文</a> · <a href="README.zh-hk.md">繁體中文</a> · <a href="README.ja.md">日本語</a> · <a href="README.vi.md">Tiếng Việt</a> · <a href="README.tr.md">Türkçe</a> · <a href="README.hu.md">Magyar</a> · <a href="README.pl.md">Polski</a> · <a href="README.sk.md">Slovenčina</a> · <a href="README.el.md">Ελληνικά</a> · <a href="README.fi.md">Suomi</a>
</p>

</div>

<br>

## なぜHi.Eventsなのか？

多くのチケット販売プラットフォームはチケット1枚ごとに手数料を課し、データを自社のエコシステムに囲い込みます。**Hi.Eventsは、
Eventbrite、Tickettailor、Dice.fmなどのチケット販売プラットフォームに代わる、モダンなオープンソースの選択肢**であり、
ブランド、チェックアウト、データ、インフラを完全にコントロールしたい主催者のために作られています。

世界中の数千の主催者に利用されています。ナイトライフのプロモーターやフェスティバルから、会場、コミュニティ団体、
カンファレンス主催者まで。自分でホスティングすることも、Hi.Events Cloudで運用を任せることもできます。

<br>

<img alt="Hi.Events Dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## 機能

**🎟️ チケット販売** — 無料・有料・寄付・階層型チケット · 繰り返し開催イベントと複数日程イベント · 完売時のウェイティングリスト ·
プロモコード（プロモコード限定チケットや非公開チケットを含む） · 商品アドオンとカテゴリ · 税・手数料・キャパシティの管理

**🎨 ブランディング・カスタマイズ** — カバー画像、カラー、タイポグラフィを設定できるイベントページデザイナー ·
ブランド化された主催者ページ · カスタマイズ可能なPDFチケット · 埋め込み可能なチケットウィジェット · SEOメタデータの制御

**👥 参加者管理** — カスタムのチェックアウト質問 · 高度な検索・フィルタリングとCSV/XLSXエクスポート · 全額・一部返金 ·
一斉メッセージ送信 · スキャンログ付きQRコードチェックインとアクセス制御されたチェックインリスト

**📊 分析・成長** — 売上ダッシュボード · アフィリエイトトラッキング · 日次売上、商品別売上、プロモコード、収益、税のレポート ·
送信Webhook

**⚙️ 運用管理** — マルチユーザーロール · Stripe Connectによる決済 · オフライン決済方法 · 請求書の自動発行 ·
オンライン・オフライン両方のイベント · 多言語対応 · [インタラクティブなOpenAPIドキュメント](#rest-api)付きの完全なREST API

<br>

## クイックスタート

**Laravel 13**（PHP >=8.3）· SSR対応の**React 19** · **TypeScript** · **PostgreSQL** · **Redis** · **Docker**
で構築されています。

### ワンクリックデプロイ

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# キーを生成（Linux/macOS）
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Windowsユーザーの方:** キーの生成方法は `./docker/all-in-one/README.md` を参照してください。

`http://localhost:8123` を開いてアカウントを作成してください。

📖 [インストールガイド全文](https://hi.events/docs/getting-started?utm_source=gh-readme)

### REST API

Hi.Eventsにはドキュメント化されたREST APIが付属しています。`.env` に `API_DOCS_ENABLED=true` を設定すると、自身の
インスタンスの `/docs/api` でインタラクティブなOpenAPIドキュメントを配信できます。仕様のエクスポートは次のコマンドで行えます:

```bash
php artisan scramble:export
```

<br>

## Hi.Events Cloud

セルフホスティングをお望みでない場合は、**[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)**
をご利用ください。本リポジトリのフルマネージド版で、セットアップ不要、自動アップデート、インフラの運用は
Hi.Eventsを開発するチームが担当します。

[今すぐ始める →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## ライセンス

Hi.Eventsは**追加条項付きのAGPL-3.0**でライセンスされています。追加条項では、ソフトウェアが生成するページやメールに
「Powered by Hi.Events」の表示を残すことが求められます。正確な文言は [LICENCE](LICENCE) を参照してください。

表示を削除したい場合や、ホワイトラベル展開に適した条件が必要な場合は、**商用ライセンスをご利用いただけます**。
[ライセンスの選択肢](https://hi.events/licensing?utm_source=gh-readme) · [hello@hi.events](mailto:hello@hi.events)

<br>

## 貢献

コントリビューションを歓迎します。まずは[コントリビューションガイド](CONTRIBUTING.md)をご覧ください。大きな作業に着手する前に、
方針をすり合わせるためにissueまたはディスカッションを作成してください。コントリビューターは [CLA](CLA.md) に署名します。

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## セキュリティ

脆弱性を発見された場合は、公開のissueではなく [security@hi.events](mailto:security@hi.events) まで非公開でご報告ください。
[セキュリティポリシー](SECURITY.md)もご覧ください。

<br>

## サポート

📖 [ドキュメント](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [ディスカッション](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

新機能や改善は[リリースページ](https://github.com/HiEventsDev/hi.events/releases)に掲載されます。

<br>

<div align="center">

**[ウェブサイト](https://hi.events)** · **[ドキュメント](https://hi.events/docs)** · **[クラウド](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[ライセンス](https://hi.events/licensing)**

Made with ☘️ in Ireland

</div>
