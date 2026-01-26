<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - 开源活动售票平台" width="100%">

# Hi.Events

### 开源活动售票与管理平台

在线销售会议、夜生活活动、音乐会、俱乐部之夜、工作坊和音乐节的门票。
自托管或云端。您的活动，您的品牌，您的数据。

[试用云端版 →](https://app.hi.events/auth/register?utm_source=gh-readme) · [在线演示](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [开发文档](https://hi.events/docs?utm_source=gh-readme) · [官方网站](https://hi.events?utm_source=gh-readme)

[![许可证: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://github.com/HiEventsDev/Hi.Events/blob/develop/LICENCE)
[![GitHub 版本](https://img.shields.io/github/v/release/HiEventsDev/hi.events?include_prereleases)](https://github.com/HiEventsDev/hi.events/releases)
[![运行单元测试](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml/badge.svg?event=push)](https://github.com/HiEventsDev/hi.events/actions/workflows/unit-tests.yml)
[![Docker 拉取量](https://img.shields.io/docker/pulls/daveearley/hi.events-all-in-one)](https://hub.docker.com/r/daveearley/hi.events-all-in-one)

<a href="https://trendshift.io/repositories/10563" target="_blank">
  <img src="https://trendshift.io/api/badge/repositories/10563" alt="HiEventsDev%2Fhi.events | Trendshift" width="250" height="55"/>
</a>

<p>
<a href="README.de.md">Deutsch</a> · <a href="README.pt.md">Português</a> · <a href="README.pt-br.md">Português do Brasil</a> · <a href="README.fr.md">Français</a> · <a href="README.it.md">Italian</a> · <a href="README.nl.md">Nederlands</a> · <a href="README.es.md">Español</a> · <a href="README.zh-cn.md">中文</a> · <a href="README.zh-hk.md">繁體中文</a> · <a href="README.ja.md">日本語</a> · <a href="README.vi.md">Tiếng Việt</a> · <a href="README.tr.md">Türkçe</a> · <a href="README.hu.md">Magyar</a> · <a href="README.pl.md">Polski</a>
</p>

</div>

<br>

## 为什么选择 Hi.Events？

大多数售票平台会收取每张票的手续费，并将您的数据锁定在他们的生态系统中。**Hi.Events 是 Eventbrite、Tickettailor、Dice.fm 和其他售票平台的现代化开源替代方案**，专为希望完全控制品牌、结账流程、数据和基础设施的主办方打造。

专为夜生活推广方、音乐节主办方、场馆、社区团体和会议主办方设计。

<br>

<img alt="Hi.Events 仪表盘" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## 功能特性

<table>
<tr>
<td width="50%" valign="top">

### 🎟️ 售票与销售

- 灵活的票种类型（免费、付费、捐赠、阶梯票）
- 通过优惠码隐藏和锁定门票
- 优惠码和预售权限
- 产品附加项（周边、升级、额外项）
- 产品分类管理
- 完整的税费支持（增值税、服务费）
- 容量管理和共享限制

</td>
<td width="50%" valign="top">

### 🎨 品牌与定制

- 精美的转化优化结账页面
- 可自定义的 PDF 门票设计
- 品牌主办方主页
- 拖放式活动页面构建器
- 可嵌入的票务小组件
- SEO 工具（元标签、Open Graph）

</td>
</tr>
<tr>
<td width="50%" valign="top">

### 👥 参会者管理

- 自定义结账问题
- 高级搜索、筛选和导出（CSV/XLSX）
- 全额和部分退款
- 按票种批量消息发送
- 二维码签到与扫描记录
- 访问控制签到列表

</td>
<td width="50%" valign="top">

### 📊 分析与增长

- 实时销售仪表盘
- 联盟和推荐跟踪
- 高级报表（销售、税务、优惠码）
- Webhooks（Zapier、Make、CRM）

</td>
</tr>
<tr>
<td colspan="2" valign="top">

### ⚙️ 运营

多用户角色和权限 · Stripe Connect 即时支付 · 线下支付方式 · 线下活动支持 · 自动开具发票 · 活动归档 · 多语言支持 · 完整的 REST API

</td>
</tr>
</table>

<br>

## 功能对比

| 功能特性                | Hi.Events | Eventbrite | Tickettailor | Dice    |
|:-----------------------|:----------|:-----------|:-------------|:--------|
| 自托管选项              | ✅         | ❌          | ❌            | ❌       |
| 开源                   | ✅         | ❌          | ❌            | ❌       |
| 无单票手续费（自托管）   | ✅         | ❌          | ❌            | ❌       |
| 完全自定义品牌          | ✅         | 有限制      | ✅            | 有限制   |
| 联盟跟踪               | ✅         | ✅          | ❌            | ❌       |
| API 访问               | ✅         | ✅          | ✅            | 有限制   |
| 拥有您的数据            | ✅         | ❌          | ❌            | ❌       |

<br>

## 快速开始

### 一键部署

[![部署到 DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![部署到 Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![部署到 Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![部署到 Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

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
> **Windows 用户：** 请查看 `./docker/all-in-one/README.md` 了解密钥生成说明。

打开 `http://localhost:8123` 并创建您的账户。

📖 [完整安装指南](https://hi.events/docs/getting-started?utm_source=gh-readme)

<br>

## Hi.Events 云端版

不想自托管？**[Hi.Events 云端版](https://app.hi.events/auth/register?utm_source=gh-readme)** 是完全托管的选项，无需设置，自动更新，基础设施由我们管理。

[立即开始 →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## 开发文档

| 资源               | 链接                                                                                          |
|:------------------|:----------------------------------------------------------------------------------------------|
| 快速入门           | [hi.events/docs/getting-started](https://hi.events/docs/getting-started?utm_source=gh-readme) |
| 配置说明           | [hi.events/docs/configuration](https://hi.events/docs/configuration?utm_source=gh-readme)     |
| API 参考文档       | [hi.events/docs/api](https://hi.events/docs/api?utm_source=gh-readme)                         |
| Webhooks          | [hi.events/docs/webhooks](https://hi.events/docs/webhooks?utm_source=gh-readme)               |

<br>

## 参与贡献

我们欢迎贡献。详细信息请参阅[贡献指南](CONTRIBUTING.md)。

<br>

## 支持

📖 [开发文档](https://hi.events/docs?utm_source=gh-readme) · 📧 [hello@hi.events](mailto:hello@hi.events) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues)

<br>

## 更新日志

在[发布页面](https://github.com/HiEventsDev/hi.events/releases)了解新功能和改进。

<br>

## 许可证

Hi.Events 采用 **AGPL-3.0 附加条款**许可。商业许可可用。[了解更多](https://hi.events/licensing)。

<br>

<div align="center">

**[官方网站](https://hi.events)** · **[开发文档](https://hi.events/docs)** · **[Twitter/X](https://x.com/HiEventsTickets)**

用 ☘️ 在爱尔兰制作

</div>
