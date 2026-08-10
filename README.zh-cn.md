<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - 开源活动售票平台" width="100%">

# Hi.Events

### 开源活动售票与管理平台

在线销售会议、夜生活活动、音乐会、俱乐部之夜、工作坊和音乐节的门票。  
自托管或云端。您的活动，您的品牌，您的数据。

[试用云端版 →](https://app.hi.events/auth/register?utm_source=gh-readme) · [在线演示](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [开发文档](https://hi.events/docs?utm_source=gh-readme) · [官方网站](https://hi.events?utm_source=gh-readme)

[![许可证: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![GitHub 版本](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![GitHub Stars](https://img.shields.io/github/stars/HiEventsDev/hi.events?style=flat)](https://github.com/HiEventsDev/hi.events/stargazers)
[![Docker 拉取量](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)
[![E2E 测试](https://github.com/HiEventsDev/hi.events/actions/workflows/e2e.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/e2e.yml)

<a href="https://trendshift.io/repositories/10563" target="_blank">
  <img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" width="250" height="55"/>
</a>

<p>
<a href="README.de.md">Deutsch</a> · <a href="README.pt.md">Português</a> · <a href="README.fr.md">Français</a> · <a href="README.it.md">Italiano</a> · <a href="README.nl.md">Nederlands</a> · <a href="README.es.md">Español</a> · <a href="README.zh-cn.md">中文</a> · <a href="README.zh-hk.md">繁體中文</a> · <a href="README.ja.md">日本語</a> · <a href="README.vi.md">Tiếng Việt</a> · <a href="README.tr.md">Türkçe</a> · <a href="README.hu.md">Magyar</a> · <a href="README.pl.md">Polski</a> · <a href="README.sk.md">Slovenčina</a> · <a href="README.el.md">Ελληνικά</a>
</p>

</div>

<br>

## 为什么选择 Hi.Events？

大多数售票平台按票收取费用，并将您的数据锁定在其生态系统中。**Hi.Events 是 Eventbrite、Tickettailor、Dice.fm
等售票平台的现代开源替代方案**，适合希望完全掌控品牌、结账流程、数据与基础设施的主办方。

全球数千家活动主办方正在使用——从夜生活推广方、音乐节到场馆、社群组织和会议主办方。您可以自行部署，也可以交由我们在
Hi.Events 云端版托管。

<br>

<img alt="Hi.Events Dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## 功能特性

**🎟️ 售票与销售** — 免费票、付费票、捐赠票与阶梯票 · 周期性活动与多日期活动 · 售罄候补名单 · 优惠码，包括优惠码解锁票与隐藏票 ·
商品附加项与分类 · 税费、手续费与容量管理

**🎨 品牌与定制** — 活动主页设计器，可设置封面图、配色与字体 · 品牌化的主办方主页 · 可定制的 PDF 门票 · 可嵌入的售票挂件 ·
SEO 元数据控制

**👥 参会者管理** — 自定义结账问题 · 高级搜索、筛选与 CSV/XLSX 导出 · 全额与部分退款 · 批量消息 · 二维码签到，含扫描记录与
权限受控的签到名单

**📊 分析与增长** — 销售仪表板 · 联盟推广追踪 · 每日销售、商品销售、优惠码、营收与税务报表 · 出站 Webhook

**⚙️ 运营** — 多用户角色 · Stripe Connect 收款 · 线下支付方式 · 自动开票 · 线上与线下活动 · 多语言支持 · 完整 REST API，附
[交互式 OpenAPI 文档](#rest-api)

<br>

## 快速开始

基于 **Laravel 13**（PHP >=8.3）· 支持 SSR 的 **React 19** · **TypeScript** · **PostgreSQL** · **Redis** · **Docker**
构建。

### 一键部署

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# 生成密钥（Linux/macOS）
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Windows 用户：** 请参阅 `./docker/all-in-one/README.md` 了解密钥生成说明。

打开 `http://localhost:8123` 并创建您的账户。

📖 [完整安装指南](https://hi.events/docs/getting-started?utm_source=gh-readme)

### REST API

Hi.Events 提供完整文档化的 REST API。在 `.env` 中设置 `API_DOCS_ENABLED=true`，即可在自有实例的 `/docs/api`
提供交互式 OpenAPI 文档，或使用以下命令导出规范：

```bash
php artisan scramble:export
```

<br>

## Hi.Events 云端版

不想自行托管？**[Hi.Events 云端版](https://app.hi.events/auth/register?utm_source=gh-readme)**
是本仓库的全托管版本——无需配置，自动更新，基础设施由开发 Hi.Events 的团队负责运维。

[立即开始 →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## 许可证

Hi.Events 采用 **AGPL-3.0 并附加条款** 授权。附加条款要求在软件生成的页面与邮件中保留 “Powered by Hi.Events”
标识——准确措辞请参阅 [LICENCE](LICENCE)。

如果您希望移除该标识，或需要适用于白标部署的条款，**我们提供商业许可**。
[许可方案](https://hi.events/licensing?utm_source=gh-readme) · [hello@hi.events](mailto:hello@hi.events)

<br>

## 参与贡献

欢迎贡献代码——请先阅读[贡献指南](CONTRIBUTING.md)。在开始较大的工作之前，请先创建 issue 或发起讨论，以便我们对方案达成一致。
贡献者需签署 [CLA](CLA.md)。

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## 安全

发现漏洞？请私下发送邮件至 [security@hi.events](mailto:security@hi.events)，而不要公开提交 issue。
详见我们的[安全策略](SECURITY.md)。

<br>

## 支持

📖 [开发文档](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [讨论区](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

新功能与改进都会发布在[发布页面](https://github.com/HiEventsDev/hi.events/releases)。

<br>

<div align="center">

**[官方网站](https://hi.events)** · **[开发文档](https://hi.events/docs)** · **[云端版](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[许可](https://hi.events/licensing)**

Made with ☘️ in Ireland

</div>
