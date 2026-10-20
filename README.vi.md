<div align="center">

<img src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-banner.png?v=1" alt="Hi.Events - Nền tảng Bán vé Sự kiện Mã nguồn Mở" width="100%">

# Hi.Events

### Nền tảng bán vé và quản lý sự kiện mã nguồn mở

Bán vé trực tuyến cho hội nghị, sự kiện giải trí, hòa nhạc, đêm câu lạc bộ, hội thảo và lễ hội.  
Tự lưu trữ hoặc đám mây. Sự kiện của bạn, thương hiệu của bạn, dữ liệu của bạn.

[Dùng thử Cloud →](https://app.hi.events/auth/register?utm_source=gh-readme) · [Demo trực tiếp](https://app.hi.events/event/2/hievents-conference-2030?utm_source=gh-readme) · [Tài liệu](https://hi.events/docs?utm_source=gh-readme) · [Website](https://hi.events?utm_source=gh-readme)

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

## Tại sao chọn Hi.Events?

Hầu hết các nền tảng bán vé đều thu phí trên mỗi vé và giữ dữ liệu của bạn trong hệ sinh thái của họ. **Hi.Events là
giải pháp thay thế hiện đại, mã nguồn mở cho Eventbrite, Tickettailor, Dice.fm và các nền tảng bán vé khác**, dành cho
những nhà tổ chức muốn toàn quyền kiểm soát thương hiệu, quy trình thanh toán, dữ liệu và hạ tầng.

Được hàng nghìn nhà tổ chức trên khắp thế giới tin dùng — từ các đơn vị tổ chức sự kiện về đêm và lễ hội cho đến các địa
điểm biểu diễn, nhóm cộng đồng và ban tổ chức hội nghị. Bạn có thể tự vận hành, hoặc để chúng tôi vận hành giúp trên
Hi.Events Cloud.

<br>

<img alt="Hi.Events Dashboard" src="https://hievents-public.s3.us-west-1.amazonaws.com/website/github-screenshot.png"/>

<br>

## Tính năng

**🎟️ Bán vé & doanh số** — vé miễn phí, có phí, quyên góp và theo bậc · sự kiện định kỳ và nhiều ngày · danh sách chờ
khi hết vé · mã khuyến mãi, bao gồm vé ẩn và vé chỉ mở bằng mã · sản phẩm bổ sung và danh mục sản phẩm · quản lý thuế,
phí và sức chứa

**🎨 Thương hiệu & tùy chỉnh** — trình thiết kế trang sự kiện cho ảnh bìa, màu sắc và kiểu chữ · trang nhà tổ chức mang
thương hiệu của bạn · vé PDF tùy chỉnh · widget bán vé có thể nhúng · kiểm soát metadata SEO

**👥 Quản lý người tham dự** — câu hỏi tùy chỉnh khi thanh toán · tìm kiếm, lọc và xuất CSV/XLSX nâng cao · hoàn tiền
toàn phần và một phần · gửi tin nhắn hàng loạt · check-in bằng mã QR kèm nhật ký quét và danh sách check-in có phân
quyền

**📊 Phân tích & tăng trưởng** — bảng điều khiển doanh số · theo dõi tiếp thị liên kết · báo cáo doanh số theo ngày,
theo sản phẩm, mã khuyến mãi, doanh thu và thuế · webhook gửi đi

**⚙️ Vận hành** — phân quyền nhiều người dùng · thanh toán qua Stripe Connect · phương thức thanh toán ngoại tuyến ·
xuất hóa đơn tự động · sự kiện trực tuyến và trực tiếp · hỗ trợ đa ngôn ngữ · REST API đầy đủ kèm
[tài liệu OpenAPI tương tác](#rest-api)

<br>

## Bắt đầu nhanh

Xây dựng bằng **Laravel 13** (PHP >=8.3) · **React 19** với SSR · **TypeScript** · **PostgreSQL** · **Redis** ·
**Docker**.

### Triển khai Một cú nhấp chuột

[![Deploy on DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://github.com/HiEventsDev/hi.events-digitalocean)
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://github.com/HiEventsDev/hi.events-render.com)
[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/8CGKmu?referralCode=KvSr11)
[![Deploy on Zeabur](https://zeabur.com/button.svg)](https://zeabur.com/templates/8DIRY6)

### Docker

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one

# Tạo khóa (Linux/macOS)
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env

docker compose up -d
```

> [!TIP]
> **Người dùng Windows:** Xem `./docker/all-in-one/README.md` để biết hướng dẫn tạo khóa.

Mở `http://localhost:8123` và tạo tài khoản của bạn.

📖 [Hướng dẫn cài đặt đầy đủ](https://hi.events/docs/getting-started?utm_source=gh-readme)

### REST API

Hi.Events đi kèm một REST API có tài liệu đầy đủ. Đặt `API_DOCS_ENABLED=true` trong tệp `.env` để phục vụ tài liệu
OpenAPI tương tác tại `/docs/api` trên máy chủ của bạn, hoặc xuất đặc tả bằng:

```bash
php artisan scramble:export
```

<br>

## Hi.Events Cloud

Không muốn tự lưu trữ? **[Hi.Events Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** là phiên bản được
quản lý hoàn toàn của kho mã này — không cần cài đặt, cập nhật tự động và hạ tầng do chính đội ngũ phát triển Hi.Events
vận hành.

[Bắt đầu ngay →](https://app.hi.events/auth/register?utm_source=gh-readme)

<br>

## Giấy phép

Hi.Events được cấp phép theo **AGPL-3.0 kèm các điều khoản bổ sung**. Các điều khoản bổ sung yêu cầu giữ lại dòng ghi
nhận "Powered by Hi.Events" trên các trang và email do phần mềm tạo ra — xem [LICENCE](LICENCE) để biết nội dung chính
xác.

**Chúng tôi có cung cấp giấy phép thương mại** nếu bạn muốn gỡ bỏ dòng ghi nhận hoặc cần các điều khoản phù hợp cho
triển khai white-label. [Các lựa chọn cấp phép](https://hi.events/licensing?utm_source=gh-readme) ·
[hello@hi.events](mailto:hello@hi.events)

<br>

## Đóng góp

Chúng tôi hoan nghênh mọi đóng góp — hãy xem [hướng dẫn đóng góp](CONTRIBUTING.md) để bắt đầu. Vui lòng mở issue hoặc
thảo luận trước khi bắt tay vào những thay đổi lớn để chúng ta thống nhất cách tiếp cận. Người đóng góp cần ký
[CLA](CLA.md).

<a href="https://github.com/HiEventsDev/hi.events/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=HiEventsDev/hi.events" alt="Hi.Events contributors" />
</a>

<br>

## Bảo mật

Phát hiện lỗ hổng bảo mật? Vui lòng báo cáo riêng tới [security@hi.events](mailto:security@hi.events) thay vì mở issue
công khai. Xem [chính sách bảo mật](SECURITY.md) của chúng tôi.

<br>

## Hỗ trợ

📖 [Tài liệu](https://hi.events/docs?utm_source=gh-readme) ·
🐛 [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues) ·
💬 [Thảo luận](https://github.com/HiEventsDev/hi.events/discussions) ·
📧 [hello@hi.events](mailto:hello@hi.events)

Các tính năng mới và cải tiến được liệt kê trên
[trang phát hành](https://github.com/HiEventsDev/hi.events/releases).

<br>

<div align="center">

**[Website](https://hi.events)** · **[Tài liệu](https://hi.events/docs)** · **[Cloud](https://app.hi.events/auth/register?utm_source=gh-readme)** · **[Cấp phép](https://hi.events/licensing)**

Made with ☘️ in Ireland

</div>
