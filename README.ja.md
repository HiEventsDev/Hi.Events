<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - オープンソースイベントチケット販売プラットフォーム" width="100%">

# Hi.Events

### オープンソースイベントチケット販売・管理プラットフォーム

カンファレンス、ナイトライフイベント、コンサート、クラブナイト、ワークショップ、フェスティバルのチケットをオンラインで販売。
セルフホスティングまたはクラウド。あなたのイベント、あなたのブランド、あなたのデータ。

[クラウド版を試す →](https://app.hi.events/auth/register?utm_source=gh-readme) · [ライブデモ](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [ドキュメント](https://hi.events/docs?utm_source=gh-readme) · [ウェブサイト](https://hi.events?utm_source=gh-readme)

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![GitHub Release](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![Run Unit Tests](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml)
[![Docker Pulls](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)

<a href="https://trendshift.io/repositories/10563" target="_blank">
  <img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" width="250" height="55"/>
</a>

<p>
<a href="README.de.md">Deutsch</a> · <a href="README.pt.md">Português</a> · <a href="README.pt-br.md">Português do Brasil</a> · <a href="README.fr.md">Français</a> · <a href="README.it.md">Italiano</a> · <a href="README.nl.md">Nederlands</a> · <a href="README.es.md">Español</a> · <a href="README.zh-cn.md">简体中文</a> · <a href="README.zh-hk.md">繁體中文（香港）</a> · <a href="README.vi.md">Tiếng Việt</a> · <a href="README.tr.md">Türkçe</a> · <a href="README.hu.md">Magyar</a> · <a href="README.ja.md">日本語</a> · <a href="README.pl.md">Polski</a>
</p>

</div>

<br>

## なぜHi.Eventsなのか？

多くのチケット販売プラットフォームは、チケットごとの手数料を請求し、あなたのデータを自社のエコシステムに閉じ込めます。**Hi.Eventsは、Eventbrite、Tickettailor、Dice.fm、その他のチケット販売プラットフォームに代わる、モダンなオープンソースの代替品です。**ブランディング、チェックアウト、データ、インフラストラクチャを完全にコントロールしたい主催者向けです。

ナイトライフプロモーター、フェスティバル主催者、会場、コミュニティグループ、カンファレンス主催者向けに構築されています。

<br>

<img alt="Hi.Eventsダッシュボード" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## 機能

<table>
<tr>
<td width="50%" valign="top">

### 🎟️ チケット販売・販売管理

- 柔軟なチケットタイプ（無料、有料、寄付、段階式）
- プロモコードで隠されたロックされたチケット
- プロモコードと先行販売アクセス
- 製品アドオン（グッズ、アップグレード、追加オプション）
- 整理のための製品カテゴリー
- 完全な税金と手数料のサポート（VAT、サービス料）
- 収容人数管理と共有制限

</td>
<td width="50%" valign="top">

### 🎨 ブランディング・カスタマイズ

- 美しく、コンバージョン最適化されたチェックアウト
- カスタマイズ可能なPDFチケットデザイン
- ブランド化された主催者ホームページ
- ドラッグアンドドロップイベントページビルダー
- 埋め込み可能なチケットウィジェット
- SEOツール（メタタグ、Open Graph）

</td>
</tr>
<tr>
<td width="50%" valign="top">

### 👥 参加者管理

- カスタムチェックアウト質問
- 高度な検索、フィルタリング、エクスポート（CSV/XLSX）
- 全額および部分的な払い戻し
- チケットタイプ別の一括メッセージング
- スキャンログ付きQRコードチェックイン
- アクセス制御されたチェックインリスト

</td>
<td width="50%" valign="top">

### 📊 分析・成長

- リアルタイム販売ダッシュボード
- アフィリエイトと紹介トラッキング
- 高度なレポート（販売、税金、プロモ）
- Webhook（Zapier、Make、CRM）

</td>
</tr>
<tr>
<td colspan="2" valign="top">

### ⚙️ 運用管理

マルチユーザーロールと権限 · Stripe Connect即時支払い · オフライン決済方法 · オフラインイベントサポート ·
自動請求書発行 · イベントアーカイブ · 多言語サポート · 完全なREST API

</td>
</tr>
</table>

<br>

## 比較

| 機能                      | Hi.Events | Eventbrite | Tickettailor | Dice    |
|:--------------------------|:----------|:-----------|:-------------|:--------|
| セルフホスティングオプション | ✅         | ❌          | ❌            | ❌       |
| オープンソース             | ✅         | ❌          | ❌            | ❌       |
| チケット手数料なし（セルフホスティング） | ✅         | ❌          | ❌            | ❌       |
| 完全なカスタムブランディング | ✅         | 制限あり    | ✅            | 制限あり |
| アフィリエイトトラッキング   | ✅         | ✅          | ❌            | ❌       |
| APIアクセス               | ✅         | ✅          | ✅            | 制限あり |
| データを所有              | ✅         | ❌          | ❌            | ❌       |

<br>

## クイックスタート

### ワンクリックデプロイ

[![DigitalOceanでデプロイ](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Renderでデプロイ](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Railwayでデプロイ](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Zeaburでデプロイ](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# キーの生成（Linux/macOS）
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Windowsユーザー:** キー生成の手順については、`./docker/all-in-one/README.md`を参照してください。

`http://localhost:8123`を開いてアカウントを作成してください。

📖 [完全なインストールガイド](https://hi.events/docs/getting-started?utm_source=gh-readme)

<br>

## Hi.Events Cloud

セルフホスティングを希望しない場合は、**[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)**が完全に管理されたオプションで、セットアップなし、自動更新、管理されたインフラストラクチャを提供します。

[今すぐ始める →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## ドキュメント

| リソース       | リンク                                                                                          |
|:---------------|:-----------------------------------------------------------------------------------------------|
| はじめに       | [hi.events/docs/getting-started](https://hi.events/docs/getting-started?utm_source=gh-readme) |
| 設定           | [hi.events/docs/configuration](https://hi.events/docs/configuration?utm_source=gh-readme)     |
| APIリファレンス | [hi.events/docs/api](https://hi.events/docs/api?utm_source=gh-readme)                         |
| Webhook        | [hi.events/docs/webhooks](https://hi.events/docs/webhooks?utm_source=gh-readme)               |

<br>

## 貢献

貢献を歓迎します。詳細については、[貢献ガイド](CONTRIBUTING.md)を参照してください。

<br>

## サポート

📖 [ドキュメント](https://hi.events/docs?utm_source=gh-readme) · 📧 [hello@hi.events](mailto:hello@hi.events) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues)

<br>

## 変更履歴

新機能と改善については、[リリースページ](https://github.com/HiEventsDev/hi.events/releases)で最新情報を入手してください。

<br>

## ライセンス

Hi.Eventsは**AGPL-3.0（追加条項付き）**でライセンスされています。商用ライセンスも利用可能です。[詳細はこちら](https://hi.events/licensing)。

<br>

<div align="center">

**[ウェブサイト](https://hi.events)** · **[ドキュメント](https://hi.events/docs)** · **[Twitter/X](https://x.com/HiEventsTickets)**

Made with ☘️ in Ireland

</div>
