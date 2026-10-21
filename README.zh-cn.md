<div align="center">

<img src=".github/assets/readme-banner.png" alt="Hi.Events - Open-source event ticketing and management platform" width="100%">

# Hi.Events

### 开源活动售票与管理平台

在线销售会议、夜生活活动、音乐会、俱乐部之夜、工作坊和音乐节的门票。<br>
自托管或云端部署。您的活动，您的品牌，您的数据。

[试用云端版 →](https://app.hi.events/auth/register?utm_source=gh-readme) · [在线演示](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [文档](https://hi.events/docs?utm_source=gh-readme) · [企业版与许可](https://hi.events/licensing?utm_source=gh-readme) · [官方网站](https://hi.events?utm_source=gh-readme)

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
> **Hi.Events 2.0 正式发布**，新增周期性活动、座位预订、票房、阵容与日程、单点
> 登录、团队权限和 API 密钥。[查看新功能 →](https://github.com/HiEventsDev/hi.events/releases)

<br>

<img alt="Hi.Events event dashboard on desktop and an event page on mobile" src=".github/assets/readme-showcase.png" width="100%"/>

<br>

## 为什么选择 Hi.Events？

大多数售票平台按票抽成，还掌握着您的客户数据。**Hi.Events 是 Eventbrite、Tickettailor、Dice.fm 等售票平台的
开源替代方案**，让您完全掌控自己的
品牌、结账流程和数据。

全球数千家活动主办方的信赖之选——从夜生活推广方、音乐节，到场馆、社区团体
和会议主办方。您可以自行部署，也可以交给我们，在 Hi.Events Cloud 上为您托管。

<br>

## 功能特性

**📅 活动**
- 单次活动与周期性活动，可按日期设置价格、容量和时间
- 一键复制活动
- 保存常用场馆，支持线上活动
- 活动主页设计器与品牌化主办方页面
- 阵容与日程页面

**🎟️ 门票与商品**
- 付费票、免费票、捐赠票和阶梯票
- 座位预订 <sup>Enterprise</sup>
- 附加项与周边商品
- 通过优惠码解锁的隐藏门票
- 单笔订单限购与定时开售
- 共享容量与自动候补名单

**🛒 销售**
- 可嵌入自有网站的定制组件：售票挂件、按钮、活动列表、阵容和日程
- 优惠码与折扣
- 面向推广方和联盟伙伴的追踪链接
- 自定义报名问题
- 结账时征求营销订阅同意
- 支持 Meta、TikTok、Google Analytics 和 Tag Manager 追踪

**💳 支付**
- 款项直接结算到您自己的 Stripe 账户
- 税费与手续费，可含在票价中或在结账时加收
- 发票与线下支付
- 全额与部分退款

**🚪 参会者与入场**
- 现场售票的票房 <sup>Enterprise</sup>
- 使用手机摄像头或 USB 扫码枪签到
- 可分享的签到名单，实时显示人数
- 参会者消息与自定义邮件模板
- 品牌化 PDF 门票
- 手动添加参会者
- 销售、税务和优惠码报表

<br>

## 专为机构打造

**🔐 安全与访问**
- 单点登录
- 双重身份验证
- 团队角色与权限
- 一个账户管理多个主办方

**🔌 集成**
- REST API 与 API 密钥
- Webhooks
- CSV 和 XLSX 导出

**🗄️ 您的数据**
- 使用 Docker 自托管
- 账户删除与匿名化
- 支持 20 种语言

**✅ 质量保障**
- 每次变更都会运行全面的测试套件

<br>

## 快速开始

基于 **Laravel 13**（PHP >=8.3）· 支持 SSR 的 **React 19** · **TypeScript** · **PostgreSQL** · **Redis** · **Docker** 构建。

### 一键部署

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
> **Windows 用户**：密钥生成方法请参阅 `./docker/all-in-one/README.md`。

打开 `http://localhost:8123` 并创建您的账户。

📖 [完整安装指南](https://hi.events/docs/getting-started?utm_source=gh-readme)

> [!IMPORTANT]
> **从 1.x 升级？** 升级前请先阅读[发布说明](https://github.com/HiEventsDev/hi.events/releases)。

### REST API

在 **设置 → API 密钥** 中创建 API 密钥，API 参考文档也在那里。

<br>

## 语言

English、Deutsch、Français、Italiano、Nederlands、Español、Português、Português (Brasil)、
Polski、Slovenčina、Magyar、Svenska、Suomi、Ελληνικά、Türkçe、Tiếng Việt、简体中文、繁體中文、日本語和한국어。

欢迎提交翻译修正或新增语言——请参阅[贡献指南](CONTRIBUTING.md)。

<br>

## Hi.Events Cloud

不想自行托管？**[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** 是全托管
版本，由 Hi.Events 的开发团队亲自运营。

[立即开始 →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## 许可

Hi.Events 采用 **AGPL-3.0 及附加条款** 授权。附加条款要求在本软件生成的页面和邮件中保留 "Powered by Hi.Events"
署名——具体措辞请参阅 [LICENCE](LICENCE)。

座位预订和票房功能受 [Hi.Events Enterprise Licence](backend/ee/LICENCE) 约束，用于生产环境
需要获得许可。

**我们提供商业许可**，适用于希望移除署名或需要白标
部署条款的用户。[许可方案](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## 参与贡献

欢迎贡献代码——请参阅[贡献指南](CONTRIBUTING.md)开始。开展较大的改动前，请先创建 issue 或
发起讨论，以便我们就实现方案达成一致。贡献者需签署
[CLA](CLA.md)。

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## 安全

发现安全漏洞？请通过 [security@hi.events](mailto:security@hi.events) 私下报告，不要公开
提交 issue。详见我们的[安全政策](SECURITY.md)。

<br>

## 支持

📖 [文档](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [讨论区](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

新功能与改进请查看
[发布页面](https://github.com/HiEventsDev/hi.events/releases)。

<br>

<div align="center">

**[官方网站](https://hi.events)** · **[文档](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[许可](https://hi.events/licensing)**

在爱尔兰用 ☘️ 精心打造

</div>
