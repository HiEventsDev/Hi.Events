<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - 開源活動售票平台" width="100%">

# Hi.Events

### 開源活動售票及管理平台

線上售賣會議、夜生活活動、音樂會、俱樂部派對、工作坊及節慶活動門票。  
自行託管或雲端部署。您的活動，您的品牌，您的數據。

[試用雲端版 →](https://app.hi.events/auth/register?utm_source=gh-readme) · [線上示範](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [技術文檔](https://hi.events/docs?utm_source=gh-readme) · [官方網站](https://hi.events?utm_source=gh-readme)

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

## 為何選擇 Hi.Events？

大部分售票平台按張收費，並將您的數據鎖定在其生態系統之中。**Hi.Events 是 Eventbrite、Tickettailor、Dice.fm
等售票平台的現代開源替代方案**，專為希望完全掌控品牌、結帳流程、數據及基礎架構的主辦單位而設。

全球數以千計的主辦單位正在使用——由夜生活主辦方、節慶活動，以至場地、社群團體及會議主辦單位。您可以自行託管，或交由我們在
Hi.Events 雲端版代為營運。

<br>

<img alt="Hi.Events Dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## 功能特色

**🎟️ 售票及銷售** — 免費票、付費票、捐款票及分級票 · 定期活動及多日期活動 · 售罄輪候名單 · 優惠碼，包括優惠碼解鎖門票及隱藏門票 ·
商品加購項目及分類 · 稅項、費用及容量管理

**🎨 品牌及自訂** — 活動主頁設計器，可設定封面圖片、色彩及字體 · 品牌化主辦單位主頁 · 可自訂的 PDF 門票 · 可嵌入的售票小工具 ·
SEO 中繼資料設定

**👥 參加者管理** — 自訂結帳問題 · 進階搜尋、篩選及 CSV/XLSX 匯出 · 全額及部分退款 · 批量訊息 · QR Code 入場核銷，附掃描紀錄
及權限受控的核銷名單

**📊 分析及增長** — 銷售儀表板 · 聯盟推廣追蹤 · 每日銷售、商品銷售、優惠碼、收入及稅項報表 · 對外 Webhook

**⚙️ 營運** — 多用戶角色 · Stripe Connect 收款 · 離線付款方式 · 自動開立發票 · 線上及實體活動 · 多語言支援 · 完整 REST API，
並提供[互動式 OpenAPI 文檔](#rest-api)

<br>

## 快速開始

以 **Laravel 13**（PHP >=8.3）· 支援 SSR 的 **React 19** · **TypeScript** · **PostgreSQL** · **Redis** · **Docker**
建構。

### 一鍵部署

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# 產生金鑰（Linux/macOS）
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Windows 用戶：** 請參閱 `./docker/all-in-one/README.md` 了解金鑰產生方法。

開啟 `http://localhost:8123` 並建立您的帳戶。

📖 [完整安裝指南](https://hi.events/docs/getting-started?utm_source=gh-readme)

### REST API

Hi.Events 附帶完整文檔的 REST API。在 `.env` 中設定 `API_DOCS_ENABLED=true`，即可於自有實例的 `/docs/api`
提供互動式 OpenAPI 文檔，或以下列指令匯出規格：

```bash
php artisan scramble:export
```

<br>

## Hi.Events 雲端版

不想自行託管？**[Hi.Events 雲端版](https://app.hi.events/auth/register?utm_source=gh-readme)**
是本倉庫的全託管版本——毋須設定，自動更新，基礎架構由開發 Hi.Events 的團隊負責營運。

[立即開始 →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## 授權條款

Hi.Events 以 **AGPL-3.0 附加條款** 授權。附加條款要求在軟件產生的頁面及電郵中保留「Powered by Hi.Events」標示——確切字句
請參閱 [LICENCE](LICENCE)。

如您希望移除該標示，或需要適用於白牌部署的條款，**我們提供商業授權**。
[授權方案](https://hi.events/licensing?utm_source=gh-readme) · [hello@hi.events](mailto:hello@hi.events)

<br>

## 貢獻

歡迎貢獻——請先閱讀[貢獻指南](CONTRIBUTING.md)。在展開較大規模的工作前，請先開立 issue 或發起討論，以便我們就方向取得共識。
貢獻者須簽署 [CLA](CLA.md)。

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## 安全

發現保安漏洞？請私下電郵至 [security@hi.events](mailto:security@hi.events)，而非公開開立 issue。
詳見我們的[安全政策](SECURITY.md)。

<br>

## 支援

📖 [技術文檔](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [討論區](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

新功能及改進均會刊登於[發佈頁面](https://github.com/HiEventsDev/hi.events/releases)。

<br>

<div align="center">

**[官方網站](https://hi.events)** · **[技術文檔](https://hi.events/docs)** · **[雲端版](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[授權](https://hi.events/licensing)**

Made with ☘️ in Ireland

</div>
