# SF Express Locker for WooCommerce

HK-only WooCommerce plugin adding SF Express Locker self-pickup selection at checkout with weight-based shipping pricing.

## Features

- **Cascading locker selector** — choose region → district → locker from SF Express's official locker network
- **Weight-based shipping** — first 1kg base fee, per-kg rate for additional weight (不足1kg當1kg)
- **Flat rate matching** — "寄到指定地址" flat rate auto-adjusted to same weight-based pricing
- **Automatic daily sync** — locker data downloaded from SF Express official PDF every 24h
- **Admin management** — import/export locker data, manual PDF upload, view locker list with pagination
- **Order integration** — locker code and address saved to order meta, displayed in admin and emails
- **Zero-config activation** — auto-creates DB table, imports bundled data, sets HK country/ship-to, creates HK zone

## Requirements

- WordPress 6.2+
- WooCommerce 8.0+
- PHP 7.4+ (recommended 8.0+)
- [smalot/pdfparser](https://github.com/smalot/pdfparser) (for PDF import)

## Installation

1. Upload the `sf-express-locker` folder to `/wp-content/plugins/`
2. Run `composer install` in the plugin directory to install PDF parser dependency
3. Activate the plugin via WordPress admin
4. Configure shipping rates under **WooCommerce → 設定 → 運送 → Hong Kong → 順豐智能櫃自取**
5. (Optional) Import latest lockers via **WooCommerce → 順豐智能櫃 → 匯入資料**

## Shipping Rate Settings

| Setting | Description |
|---------|-------------|
| 首 1kg 運費 | Base fee for first 1kg |
| 其後每公斤運費 | Per-kg rate for additional weight (rounded up) |
| 最高重量限制 (kg) | Max weight for locker eligibility (default 20kg) |
| 免運費門檻 | Free shipping threshold (leave empty to disable) |

## Auto-Import

Locker data syncs from SF Express's official PDF once daily — triggered both by WordPress cron (`sf_locker_daily_maintenance`) and on admin page visits when more than 24h have passed since last sync.

Manual import is always available under **匯入資料** tab.

## Changelog

### 1.3.0
- Auto-download locker data from SF Express on admin login (once daily)
- Fixed locker code display on thank you page and email
- Various UI tweaks

### 1.2.0
- Weight-based pricing: first 1kg = base fee + ceil(excess) × per_kg_rate
- Flat rate cost synced with locker method
- Shipping cost displayed as separate row, not appended to method label
- Admin import/export/locker list tabs

### 1.1.0
- Cascading region → district → locker selector
- Locker data persisted in custom DB table
- Order meta save and display

### 1.0.0
- Initial release: SF Express locker as WooCommerce shipping method
- Basic locker selector on checkout
