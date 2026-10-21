<div align="center">

<img src=".github/assets/readme-banner.png" alt="Hi.Events - Open-source event ticketing and management platform" width="100%">

# Hi.Events

### 開源活動售票及管理平台

網上發售會議、夜生活活動、音樂會、Club Night、工作坊及音樂節門票。<br>
自行託管或雲端部署。你的活動，你的品牌，你的數據。

[試用雲端版 →](https://app.hi.events/auth/register?utm_source=gh-readme) · [線上示範](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [文件](https://hi.events/docs?utm_source=gh-readme) · [企業版及授權](https://hi.events/licensing?utm_source=gh-readme) · [官方網站](https://hi.events?utm_source=gh-readme)

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
> **Hi.Events 2.0 現已推出**，新增週期性活動、劃位座位、票房、表演陣容及節目表、單一
> 登入、團隊權限及 API 金鑰。[看看有甚麼新功能 →](https://github.com/HiEventsDev/hi.events/releases)

<br>

<img alt="Hi.Events event dashboard on desktop and an event page on mobile" src=".github/assets/readme-showcase.png" width="100%"/>

<br>

## 為甚麼選擇 Hi.Events？

大部分售票平台每張門票都要抽成，還會把你的客戶數據據為己有。**Hi.Events 是 Eventbrite、Tickettailor、Dice.fm 等售票平台的
開源替代方案**，讓你全面掌控自己的
品牌、結帳流程及數據。

全球數以千計的活動主辦單位都信賴我們——由夜生活活動推廣商、音樂節，到場地、社區團體
及會議主辦方。你可以自行部署，也可以交給我們，透過 Hi.Events Cloud 為你代管。

<br>

## 功能特點

**📅 活動**
- 單次及週期性活動，可按日期設定價格、名額及時間
- 一鍵複製活動
- 儲存常用場地，支援網上活動
- 活動主頁設計工具及品牌化主辦單位頁面
- 表演陣容及節目表頁面

**🎟️ 門票及商品**
- 收費、免費、捐款及分級門票
- 劃位座位 <sup>Enterprise</sup>
- 附加項目及周邊商品
- 以優惠碼解鎖的隱藏門票
- 每張訂單購買上限及預定開售時段
- 共用名額及自動候補名單

**🛒 銷售**
- 可嵌入自家網站的自訂組件：售票小工具、按鈕、活動列表、表演陣容及節目表
- 優惠碼及折扣
- 供推廣商及聯盟夥伴使用的追蹤連結
- 自訂登記問題
- 結帳時徵求推廣訊息同意
- 支援 Meta、TikTok、Google Analytics 及 Tag Manager 追蹤

**💳 付款**
- 款項直接存入你自己的 Stripe 帳戶
- 稅項及手續費，可包含在票價內或於結帳時另加
- 發票及線下付款
- 全額及部分退款

**🚪 參加者及入場**
- 現場售票的票房 <sup>Enterprise</sup>
- 以手機鏡頭或 USB 掃描器簽到
- 可分享的簽到名單，實時顯示人數
- 參加者訊息及自訂電郵範本
- 品牌化 PDF 門票
- 手動新增參加者
- 銷售、稅項及優惠碼報告

<br>

## 為機構而設

**🔐 安全及存取**
- 單一登入
- 雙重認證
- 團隊角色及權限
- 一個帳戶管理多個主辦單位

**🔌 整合**
- REST API 及 API 金鑰
- Webhooks
- CSV 及 XLSX 匯出

**🗄️ 你的數據**
- 以 Docker 自行託管
- 帳戶刪除及匿名化
- 支援 20 種語言

**✅ 品質保證**
- 每次改動都會執行全面的測試套件

<br>

## 快速開始

採用 **Laravel 13**（PHP >=8.3）· 支援 SSR 的 **React 19** · **TypeScript** · **PostgreSQL** · **Redis** · **Docker** 構建。

### 一鍵部署

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
> **Windows 用戶**：生成金鑰的方法請參閱 `./docker/all-in-one/README.md`。

開啟 `http://localhost:8123` 並建立你的帳戶。

📖 [完整安裝指南](https://hi.events/docs/getting-started?utm_source=gh-readme)

> [!IMPORTANT]
> **由 1.x 升級？** 升級前請先閱讀[發佈說明](https://github.com/HiEventsDev/hi.events/releases)。

### REST API

在 **設置 → API 金鑰** 建立 API 金鑰，API 參考文件亦在同一頁面。

<br>

## 語言

English、Deutsch、Français、Italiano、Nederlands、Español、Português、Português (Brasil)、
Polski、Slovenčina、Magyar、Svenska、Suomi、Ελληνικά、Türkçe、Tiếng Việt、简体中文、繁體中文、日本語及한국어。

歡迎提交翻譯修正或新增語言——請參閱[貢獻指南](CONTRIBUTING.md)。

<br>

## Hi.Events Cloud

不想自行託管？**[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** 是全代管
版本，由開發 Hi.Events 的團隊親自營運。

[立即開始 →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## 授權

Hi.Events 採用 **AGPL-3.0 及附加條款** 授權。附加條款規定須在本軟件產生的頁面及電郵中保留 "Powered by Hi.Events"
署名——確切條文請參閱 [LICENCE](LICENCE)。

劃位座位及票房功能受 [Hi.Events Enterprise Licence](backend/ee/LICENCE) 約束，用於正式環境
須取得授權。

**我們提供商業授權**，適合希望移除署名或需要白牌
部署條款的用戶。[授權方案](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## 參與貢獻

歡迎貢獻——請參閱[貢獻指南](CONTRIBUTING.md)開始。進行較大型的改動前，請先開設 issue 或
發起討論，讓我們就實作方向達成共識。貢獻者須簽署
[CLA](CLA.md)。

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## 安全

發現安全漏洞？請透過 [security@hi.events](mailto:security@hi.events) 私下報告，切勿公開
提交 issue。詳情請參閱我們的[安全政策](SECURITY.md)。

<br>

## 支援

📖 [文件](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [討論區](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

新功能及改進一覽請瀏覽
[發佈頁面](https://github.com/HiEventsDev/hi.events/releases)。

<br>

<div align="center">

**[官方網站](https://hi.events)** · **[文件](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[授權](https://hi.events/licensing)**

在愛爾蘭用 ☘️ 精心打造

</div>
