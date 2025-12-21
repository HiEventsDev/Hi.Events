<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - 開源活動售票平台" width="100%">

# Hi.Events

### 開源活動售票及管理平台

線上售賣會議、夜生活活動、音樂會、俱樂部派對、工作坊及節慶活動門票。
自行託管或雲端部署。您的活動，您的品牌，您的數據。

[試用雲端版 →](https://app.hi.events/auth/register?utm_source=gh-readme) · [線上示範](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [技術文檔](https://hi.events/docs?utm_source=gh-readme) · [官方網站](https://hi.events?utm_source=gh-readme)

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![GitHub Release](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![Run Unit Tests](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml)
[![Docker Pulls](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)

<a href="https://trendshift.io/repositories/10563" target="_blank">
  <img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" width="250" height="55"/>
</a>

<p>
<a href="README.de.md">Deutsch</a> · <a href="README.pt.md">Português</a> · <a href="README.pt-br.md">Português (Brasil)</a> · <a href="README.fr.md">Français</a> · <a href="README.it.md">Italiano</a> · <a href="README.nl.md">Nederlands</a> · <a href="README.es.md">Español</a> · <a href="README.zh-cn.md">中文</a> · <a href="README.zh-hk.md">繁體中文</a> · <a href="README.ja.md">日本語</a> · <a href="README.vi.md">Tiếng Việt</a> · <a href="README.tr.md">Türkçe</a> · <a href="README.hu.md">Magyar</a>
</p>

</div>

<br>

## 為何選擇 Hi.Events？

大多數售票平台會收取每張門票的手續費，並將您的數據鎖定在其生態系統中。**Hi.Events 是 Eventbrite、Tickettailor、Dice.fm 及其他售票平台的現代化開源替代方案**，專為希望完全掌控品牌、結帳流程、數據及基礎設施的主辦方而設。

專為夜生活推廣者、節慶主辦方、場地、社區團體及會議主持人而建。

<br>

<img alt="Hi.Events 管理面板" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## 功能特色

<table>
<tr>
<td width="50%" valign="top">

### 🎟️ 售票及銷售

- 靈活的門票類型（免費、付費、捐贈、分級）
- 透過優惠碼隱藏及鎖定門票
- 優惠碼及預售權限
- 產品附加項目（商品、升級、額外項目）
- 產品分類組織
- 完整稅務及手續費支援（增值稅、服務費）
- 容量管理及共享限額

</td>
<td width="50%" valign="top">

### 🎨 品牌及自訂

- 精美、轉換率優化的結帳頁面
- 可自訂的 PDF 門票設計
- 品牌主辦方首頁
- 拖放式活動頁面建置器
- 可嵌入的售票小工具
- SEO 工具（元標籤、Open Graph）

</td>
</tr>
<tr>
<td width="50%" valign="top">

### 👥 參加者管理

- 自訂結帳問題
- 進階搜尋、篩選及匯出（CSV/XLSX）
- 全額及部分退款
- 按門票類型批量發送訊息
- QR 碼簽到及掃描記錄
- 存取權限控制的簽到名單

</td>
<td width="50%" valign="top">

### 📊 分析及增長

- 即時銷售儀表板
- 聯盟行銷及推薦追蹤
- 進階報表（銷售、稅務、優惠）
- Webhooks（Zapier、Make、CRM）

</td>
</tr>
<tr>
<td colspan="2" valign="top">

### ⚙️ 營運

多用戶角色及權限 · Stripe Connect 即時付款 · 離線付款方式 · 離線活動支援 ·
自動開立發票 · 活動封存 · 多語言支援 · 完整 REST API

</td>
</tr>
</table>

<br>

## 比較

| 功能                    | Hi.Events | Eventbrite | Tickettailor | Dice    |
|:----------------------|:----------|:-----------|:-------------|:--------|
| 自行託管選項                | ✅         | ❌          | ❌            | ❌       |
| 開源                    | ✅         | ❌          | ❌            | ❌       |
| 無每張門票手續費（自行託管）       | ✅         | ❌          | ❌            | ❌       |
| 完整自訂品牌                | ✅         | 有限         | ✅            | 有限      |
| 聯盟行銷追蹤                | ✅         | ✅          | ❌            | ❌       |
| API 存取                | ✅         | ✅          | ✅            | 有限      |
| 擁有您的數據                | ✅         | ❌          | ❌            | ❌       |

<br>

## 快速開始

### 一鍵部署

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# 生成密鑰（Linux/macOS）
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Windows 用戶：** 請參閱 `./docker/all-in-one/README.md` 了解密鑰生成指示。

開啟 `http://localhost:8123` 並建立您的帳戶。

📖 [完整安裝指南](https://hi.events/docs/getting-started?utm_source=gh-readme)

<br>

## Hi.Events 雲端版

不想自行託管？**[Hi.Events 雲端版](https://app.hi.events/auth/register?utm_source=gh-readme)** 是完全託管的選項，無需設定、自動更新及託管基礎設施。

[立即開始 →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## 技術文檔

| 資源   | 連結                                                                                            |
|:-----|:----------------------------------------------------------------------------------------------|
| 入門指南 | [hi.events/docs/getting-started](https://hi.events/docs/getting-started?utm_source=gh-readme) |
| 配置說明 | [hi.events/docs/configuration](https://hi.events/docs/configuration?utm_source=gh-readme)     |
| API 參考 | [hi.events/docs/api](https://hi.events/docs/api?utm_source=gh-readme)                         |
| Webhooks | [hi.events/docs/webhooks](https://hi.events/docs/webhooks?utm_source=gh-readme)               |

<br>

## 貢獻

我們歡迎貢獻。詳情請參閱[貢獻指南](CONTRIBUTING.md)。

<br>

## 支援

📖 [技術文檔](https://hi.events/docs?utm_source=gh-readme) · 📧 [hello@hi.events](mailto:hello@hi.events) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues)

<br>

## 變更日誌

於[發佈頁面](https://github.com/HiEventsDev/hi.events/releases)查看最新功能及改進。

<br>

## 授權條款

Hi.Events 採用 **AGPL-3.0 附加條款** 授權。商業授權另行提供。[了解更多](https://hi.events/licensing)。

<br>

<div align="center">

**[官方網站](https://hi.events)** · **[技術文檔](https://hi.events/docs)** · **[Twitter/X](https://x.com/HiEventsTickets)**

在愛爾蘭用 ☘️ 製作

</div>
