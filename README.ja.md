<div align="center">

<img src=".github/assets/readme-banner.png" alt="Hi.Events - Open-source event ticketing and management platform" width="100%">

# Hi.Events

### オープンソースのイベントチケット販売・管理プラットフォーム

カンファレンス、ナイトライフイベント、コンサート、クラブイベント、ワークショップ、フェスティバルのチケットをオンラインで販売できます。<br>
セルフホストでもクラウドでも。あなたのイベント、あなたのブランド、あなたのデータです。

[Cloudを試す →](https://app.hi.events/auth/register?utm_source=gh-readme) · [ライブデモ](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [ドキュメント](https://hi.events/docs?utm_source=gh-readme) · [Enterprise・ライセンス](https://hi.events/licensing?utm_source=gh-readme) · [ウェブサイト](https://hi.events?utm_source=gh-readme)

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
> **Hi.Events 2.0 がリリースされました。** 繰り返しイベント、指定席、ボックスオフィス、ラインナップとスケジュール、
> シングルサインオン、チーム権限、APIキーに対応しています。[新機能を見る →](https://github.com/HiEventsDev/hi.events/releases)

<br>

<img alt="Hi.Events event dashboard on desktop and an event page on mobile" src=".github/assets/readme-showcase.png" width="100%"/>

<br>

## Hi.Events を選ぶ理由

多くのチケット販売プラットフォームは、チケット1枚ごとに手数料を取り、顧客データを手元に置いています。**Hi.Events は、
Eventbrite、Tickettailor、Dice.fm などのチケット販売プラットフォームに代わるオープンソースの選択肢**です。ブランディング、
チェックアウト、データをすべて自分でコントロールできます。

ナイトライフのプロモーターやフェスティバルから、会場、コミュニティ団体、カンファレンス主催者まで、世界中の数千のイベント主催者に
選ばれています。セルフホストで運用することも、Hi.Events Cloud で運用を任せることもできます。

<br>

## 機能

**📅 イベント**
- 単発イベントと繰り返しイベント（日程ごとに価格・定員・時間を設定可能）
- ワンクリックでイベントを複製
- 会場の保存とオンラインイベント
- イベントホームページデザイナーとブランド化された主催者ページ
- ラインナップページとスケジュールページ

**🎟️ チケット・商品**
- 有料・無料・寄付・段階制チケット
- 指定席 <sup>Enterprise</sup>
- アドオンとグッズ
- プロモーションコードで表示される非公開チケット
- 注文ごとの購入上限と販売期間の予約設定
- 共有キャパシティと自動キャンセル待ち

**🛒 販売**
- 自社サイト向けにカスタマイズできる埋め込み：チケットウィジェット、ボタン、イベント一覧、ラインナップ、スケジュール
- プロモーションコードと割引
- プロモーター・アフィリエイト向けのトラッキングリンク
- 登録時のカスタム質問
- チェックアウト時のマーケティング配信同意
- Meta、TikTok、Google Analytics、Tag Manager によるトラッキング

**💳 決済**
- ご自身の Stripe アカウントへの入金
- 税金と手数料（価格に含める・チェックアウト時に加算）
- 請求書発行とオフライン決済
- 全額返金・一部返金

**🚪 参加者・入場**
- 当日販売用のボックスオフィス <sup>Enterprise</sup>
- スマートフォンのカメラや USB スキャナーでのチェックイン
- リアルタイム集計つきの共有可能なチェックインリスト
- 参加者へのメッセージ送信とカスタムメールテンプレート
- ブランド化された PDF チケット
- 参加者の手動追加
- 売上・税金・プロモーションコードのレポート

<br>

## 組織での利用に

**🔐 セキュリティ・アクセス管理**
- シングルサインオン
- 二要素認証
- チームのロールと権限
- 1つのアカウントで複数の主催者を管理

**🔌 連携**
- REST API と APIキー
- Webhook
- CSV・XLSX エクスポート

**🗄️ データ管理**
- Docker でセルフホスト
- アカウントの削除と匿名化
- 20言語に対応

**✅ テスト済み**
- すべての変更に対して包括的なテストスイートを実行

<br>

## クイックスタート

**Laravel 13**（PHP >=8.3）· SSR 対応の **React 19** · **TypeScript** · **PostgreSQL** · **Redis** · **Docker** で構築されています。

### ワンクリックデプロイ

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
> **Windows をお使いの方へ：** キーの生成手順は `./docker/all-in-one/README.md` をご覧ください。

`http://localhost:8123` を開いて、アカウントを作成してください。

📖 [インストールガイド（完全版）](https://hi.events/docs/getting-started?utm_source=gh-readme)

> [!IMPORTANT]
> **1.x からアップグレードする場合は、** アップグレード前に[リリースノート](https://github.com/HiEventsDev/hi.events/releases)をお読みください。

### REST API

APIキーは **設定 → APIキー** から作成できます。APIリファレンスも同じ画面にあります。

<br>

## 対応言語

English, Deutsch, Français, Italiano, Nederlands, Español, Português, Português (Brasil),
Polski, Slovenčina, Magyar, Svenska, Suomi, Ελληνικά, Türkçe, Tiếng Việt, 简体中文, 繁體中文, 日本語、한국어。

翻訳の修正や新しい言語の追加を歓迎します。[コントリビューションガイド](CONTRIBUTING.md)をご覧ください。

<br>

## Hi.Events Cloud

セルフホストが不要なら、**[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** をご利用ください。Hi.Events の開発チームが
運用するフルマネージド版です。

[今すぐ始める →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## ライセンス

Hi.Events は **追加条項付きの AGPL-3.0** でライセンスされています。追加条項により、ソフトウェアが生成するページとメールには「Powered by Hi.Events」の
表記を残す必要があります。正確な文言は [LICENCE](LICENCE) をご覧ください。

指定席とボックスオフィスは [Hi.Events Enterprise Licence](backend/ee/LICENCE) の対象であり、本番環境で利用するには
ライセンスが必要です。

表記を削除したい場合や、ホワイトラベルでの導入に適した条件が必要な場合は、**商用ライセンスをご用意しています**。
[ライセンスオプション](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## コントリビューション

コントリビューションを歓迎します。まずは[コントリビューションガイド](CONTRIBUTING.md)をご覧ください。大きな変更に取り組む前に、
方針をすり合わせるため issue または discussion を作成してください。コントリビューターには
[CLA](CLA.md) への署名をお願いしています。

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## セキュリティ

脆弱性を発見した場合は、公開の issue を作成せず、[security@hi.events](mailto:security@hi.events) まで非公開でご報告ください。
詳しくは[セキュリティポリシー](SECURITY.md)をご覧ください。

<br>

## サポート

📖 [ドキュメント](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [ディスカッション](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

新機能や改善点は
[リリースページ](https://github.com/HiEventsDev/hi.events/releases)でご確認いただけます。

<br>

<div align="center">

**[ウェブサイト](https://hi.events)** · **[ドキュメント](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[ライセンス](https://hi.events/licensing)**

アイルランドで ☘️ を込めて開発

</div>
