# Modern Restaurant Booking Manager

Plugin WordPress hiện đại cho hệ thống đặt bàn nhà hàng với giao diện được thiết kế lại hoàn toàn dựa trên [Figma Community Design](https://www.figma.com/community/file/1308435721888237413). Plugin cung cấp trải nghiệm đặt bàn mượt mà cho khách hàng, portal quản lý hiện đại cho nhân viên và admin panel toàn diện cho quản trị viên.

## 🎨 Design System

Plugin được thiết kế dựa trên Figma Community file "Table Booking Restaurant Application" với:
- **Modern UI/UX** components
- **Light & Dark Mode** support
- **Mobile-First** responsive design
- **Accessibility** compliant
- **Consistent** design language

## ✨ Tính năng chính

### 🍽️ Customer Experience
- **Modern Booking Widget**: Modal 3-step booking với animation mượt mà
- **Smart Time Selection**: Gợi ý khung giờ thay thế khi hết chỗ
- **Real-time Availability**: Kiểm tra tình trạng bàn trống tức thời
- **Multi-language Support**: Đa ngôn ngữ với language switcher
- **Dark/Light Mode**: Chuyển đổi theme theo preference
- **Email Confirmation**: Xác nhận đặt bàn với secure token

### 👨‍💼 Restaurant Manager Portal
- **Modern Dashboard**: Thống kê real-time với charts & metrics
- **Booking Management**: Quản lý đặt bàn với drag-drop calendar
- **Table Management**: Quản lý bàn với visual floor plan
- **Customer Profile**: Lịch sử khách hàng, VIP/Blacklist status
- **Location Management**: Multi-location support
- **Advanced Filters**: Lọc theo ngày, trạng thái, nguồn booking

### 🎯 Super Admin Panel
- **Multi-location Overview**: Dashboard tổng quan nhiều chi nhánh
- **Advanced Analytics**: Báo cáo chi tiết, export data
- **Portal Account Management**: Tạo/quản lý tài khoản nhân viên
- **Email Templates**: Customize email automation
- **System Settings**: Cấu hình toàn hệ thống

## 🏗️ Kiến trúc hiện đại

### Frontend Architecture
```
Modern Stack:
├── CSS Custom Properties (CSS Variables)
├── CSS Grid & Flexbox Layout
├── Progressive Enhancement
├── Mobile-First Responsive
├── Dark Mode Support
└── Accessibility (WCAG 2.1)
```

### Component Structure
```
UI Components:
├── Booking Widget (Modal)
│   ├── Step 1: Location & Date Selection
│   ├── Step 2: Time Slot Grid
│   └── Step 3: Customer Information
├── Manager Portal
│   ├── Dashboard with Charts
│   ├── Booking Calendar View
│   ├── Table Management
│   └── Customer Profiles
└── Admin Panel
    ├── Multi-location Dashboard
    ├── Analytics & Reports
    └── System Configuration
```

### Technical Stack
- **Frontend**: Modern CSS, Vanilla JavaScript (ES6+)
- **Backend**: WordPress Hooks & AJAX
- **Database**: Custom tables with optimized queries
- **Email**: HTML templates with inline CSS
- **Security**: Nonce validation, sanitized inputs

## 📦 Cấu trúc file

```
modern-restaurant-booking/
├── restaurant-booking-manager.php          # ⭐ Plugin bootstrap (NEW)
├── includes/
│   ├── class-plugin-loader.php            # ⭐ Hook orchestration (NEW)
│   ├── class-plugin-manager.php           # ⭐ Dependency container (NEW)
│   ├── class-plugin-activator.php         # ⭐ Activation routines (NEW)
│   ├── class-plugin-deactivator.php       # ⭐ Cleanup routines (NEW)
│   ├── database/
│   │   └── schema.php                     # ⭐ Database schema helpers (NEW)
│   ├── models/
│   │   ├── class-booking.php              # Booking entity model
│   │   ├── class-location.php             # Location entity model
│   │   ├── class-table.php                # Table entity model
│   │   └── class-customer.php             # Customer entity model
│   └── services/
│       ├── class-analytics-service.php    # Analytics domain service
│       ├── class-calendar-service.php     # Calendar orchestration
│       └── class-notification-service.php # Notification delivery
├── admin/
│   ├── class-modern-admin.php             # Modern admin controller
│   └── partials/
│       ├── admin-dashboard.php            # Overview & charts
│       ├── bookings-table.php             # Booking table management
│       ├── locations-management.php       # Multi-location tools
│       ├── reports-analytics.php          # Analytics reporting
│       └── settings-panel.php             # Settings UI
├── public/
│   ├── class-modern-booking-manager.php   # Frontend booking entrypoint
│   ├── class-modern-booking-widget.php    # Customer booking modal
│   ├── class-modern-dashboard.php         # Manager dashboard surface
│   ├── class-modern-portal-auth.php       # Portal auth flows
│   └── class-modern-table-manager.php     # Table management frontend
├── public/partials/
│   ├── booking-modal.php                  # Booking modal template
│   ├── booking-management.php             # Booking management layout
│   ├── booking-calendar-view.php          # Availability calendar
│   ├── customer-profiles.php              # Customer detail view
│   ├── portal-dashboard.php               # Manager portal UI
│   └── table-management.php               # Table floor plan
├── assets/
│   ├── css/
│   │   ├── design-system.css              # Core design tokens
│   │   ├── animations.css                 # ⭐ Animation tokens (NEW)
│   │   ├── booking-modal.css              # Booking modal styles
│   │   ├── booking-management.css         # Back-office booking styles
│   │   ├── portal-dashboard.css           # Portal dashboard styles
│   │   ├── table-management.css           # Floor plan styles
│   │   ├── components.css                 # Shared component styles
│   │   └── modern-admin.css               # WordPress admin panel styles (NEW)
│   ├── js/
│   │   ├── theme-manager.js               # ✓ Theme persistence (Updated)
│   │   ├── booking-widget.js              # Booking widget logic
│   │   ├── portal-dashboard.js            # Portal interactions
│   │   ├── dashboard-charts.js            # Chart integrations
│   │   ├── booking-management.js          # Admin booking scripts
│   │   ├── table-management.js            # Table visualization scripts
│   │   └── modern-admin.js                # Admin enhancements
│   └── images/
│       ├── icons/                         # SVG icon set
│       └── illustrations/                 # UI illustrations
├── templates/
│   ├── emails/                            # Email templates
│   └── pdf/                               # PDF exports
├── languages/                             # Translation files
├── tests/
│   ├── unit/                              # PHPUnit tests
│   └── e2e/                               # Playwright tests
├── README.md                              # ✓ Tài liệu tổng quan
├── CHANGELOG.md                           # Lịch sử thay đổi
├── composer.json                          # PHP dependencies
├── package.json                           # JS tooling
└── .gitignore
```

## 🔧 Plugin Architecture & Initialization

### How Plugin Initializes

```text
1. restaurant-booking-manager.php (Bootstrap)
   ↓
2. Định nghĩa plugin constants & đường dẫn
   ↓
3. Require class-plugin-loader.php & class-plugin-manager.php
   ↓
4. Hook vào sự kiện plugins_loaded
   ↓
5. Restaurant_Booking_Plugin_Manager::instance() khởi tạo dependencies
   ↓
6. Đăng ký toàn bộ hooks/thành phần qua RB_Plugin_Loader
   ↓
7. Frontend, Admin & AJAX handlers sẵn sàng
```

### Plugin Manager Flow

```text
Restaurant_Booking_Plugin_Manager
├── Load Core Classes
│   ├── Booking
│   ├── Location
│   ├── Table
│   └── Customer
├── Load Services
│   ├── Analytics Service
│   ├── Calendar Service
│   └── Notification Service
├── Load Interfaces
│   ├── Modern Admin (is_admin)
│   ├── Modern Booking Widget (frontend)
│   ├── Modern Dashboard (frontend)
│   └── Modern Portal Auth (frontend)
└── Register All Hooks & Filters
```

### Key Classes

| Class | Purpose | Location |
|-------|---------|----------|
| `RB_Plugin_Loader` | Quản lý hooks & filters | `includes/class-plugin-loader.php` |
| `Restaurant_Booking_Plugin_Manager` | Bootstrap & dependency injection | `includes/class-plugin-manager.php` |
| `Restaurant_Booking_Plugin_Activator` | Tạo database & defaults | `includes/class-plugin-activator.php` |
| `Restaurant_Booking_Plugin_Deactivator` | Cleanup & scheduler removal | `includes/class-plugin-deactivator.php` |
| `RB_Modern_Admin` | Giao diện WordPress admin | `admin/class-modern-admin.php` |
| `RB_Modern_Booking_Widget` | Widget đặt bàn frontend | `public/class-modern-booking-widget.php` |
| `RB_Modern_Booking_Manager` | Điều phối booking frontend | `public/class-modern-booking-manager.php` |
| `RB_Modern_Portal_Auth` | Đăng nhập portal & session | `public/class-modern-portal-auth.php` |
| `RB_Modern_Dashboard` | Dashboard & analytics frontend | `public/class-modern-dashboard.php` |
| `RB_Analytics_Service` | Xử lý dữ liệu analytics | `includes/services/class-analytics-service.php` |

### Theme Manager Global Integration

- `assets/js/theme-manager.js` được enqueue ở admin, portal, dashboard và booking widget
- Lưu theme preference trong `localStorage` và đồng bộ với `prefers-color-scheme`
- Cung cấp API `window.rbThemeManager` với các phương thức `getCurrentTheme`, `setTheme`, `onThemeChange`
- Phát sự kiện `themechange` để component lắng nghe và cập nhật UI theo thời gian thực

### CSS Animation System

- `assets/css/animations.css` tập trung 20+ `@keyframes` tái sử dụng
- Bao gồm token tốc độ chuyển động (`--rb-transition-*`)
- Chứa hàm easing chuẩn hóa cho toàn bộ interface
- Tôn trọng `prefers-reduced-motion` để đảm bảo accessibility
- Cung cấp utility classes (`.rb-animate-fade-in`, `.rb-animate-slide-up`, ...)

### Asynchronous AJAX Architecture

```php
// 1. Verify nonce
check_ajax_referer( 'rb_nonce', 'nonce' );

// 2. Sanitize input
$param = sanitize_text_field( $_POST['param'] ?? '' );

// 3. Validate capability
if ( ! current_user_can( 'manage_bookings' ) ) {
    wp_send_json_error( [ 'message' => __( 'Permission denied', 'restaurant-booking' ) ], 403 );
}

// 4. Process request
$result = RB_Analytics_Service::get_instance()->process( $param );

// 5. Return JSON
wp_send_json_success( [ 'data' => $result ] );
```

## 🚀 Tính năng mới so với phiên bản cũ

### UI/UX Improvements
- ✅ **Complete UI Redesign** dựa trên Figma design system
- ✅ **Dark Mode Support** với smooth transitions
- ✅ **Mobile-First Design** với touch-friendly interactions
- ✅ **Modern Animations** và micro-interactions
- ✅ **Improved Accessibility** với proper ARIA labels

### Functional Enhancements
- ✅ **Advanced Dashboard** với real-time metrics
- ✅ **Calendar View** cho booking management
- ✅ **Visual Table Management** với floor plan
- ✅ **Advanced Analytics** với chart visualizations
- ✅ **Export Functionality** (CSV, PDF, Excel)
- ✅ **Real-time Notifications** cho staff
- ✅ **Multi-location Dashboard** cho super admin

### Technical Improvements
- ✅ **Modern CSS Architecture** với CSS Custom Properties
- ✅ **Component-based Structure** dễ maintain
- ✅ **Performance Optimized** với lazy loading
- ✅ **Better Error Handling** với user-friendly messages
- ✅ **Enhanced Security** với improved validation

## 🎯 Shortcodes mới

| Shortcode | Mô tả | Attributes |
|-----------|-------|------------|
| `[modern_restaurant_booking]` | Widget đặt bàn hiện đại | `theme="light/dark"`, `location="id"` |
| `[modern_booking_portal]` | Portal manager hiện đại | `dashboard="true/false"` |
| `[booking_calendar]` | Calendar view cho booking | `view="month/week/day"` |
| `[restaurant_analytics]` | Analytics dashboard | `period="7d/30d/90d"` |
| `[table_floor_plan]` | Visual table management | `location="id"`, `editable="true/false"` |

### 🔐 Quyền truy cập & thông báo đăng nhập

- Plugin tự động thêm capability `manage_bookings` cho vai trò **Administrator** và **Editor** mỗi khi khởi tạo.
- Administrator vẫn có thể vào menu và shortcode dù bị mất capability tùy chỉnh nhờ fallback `manage_options` mới (filter `map_meta_cap`).
- Người dùng không đủ quyền sẽ thấy thông báo kèm liên kết đăng nhập portal (`/portal/` hoặc URL tùy chỉnh qua filter `rb_portal_login_url`).
- Có thể điều chỉnh trang đăng nhập bằng cách thêm vào theme/plugin:
- Trang **Settings** giờ chạy trực tiếp trong backend WordPress thông qua Settings API với slug admin `restaurant-booking-settings`, xuất hiện cả trong menu **Bookings** của plugin lẫn mục **Settings → Restaurant Booking**. Có thể đổi slug bằng filter `restaurant_booking_settings_page_slug`; các URL cũ `admin.php?page=rb-settings` sẽ tự động chuyển hướng.
- Toàn bộ tùy chọn được lưu trong option `restaurant_booking_settings`, có thể truy cập qua helper `restaurant_booking_get_setting()` / `restaurant_booking_get_settings()` hoặc đổi giá trị mặc định bằng filter `restaurant_booking_default_settings`.

```php
add_filter( 'rb_portal_login_url', function( $url ) {
    return home_url( '/staff-login/' );
} );
```

```php
// Tùy chỉnh slug trang cấu hình admin
add_filter( 'restaurant_booking_settings_page_slug', function( $slug ) {
    return 'nha-hang-settings';
} );
```

## 🔧 Cấu hình Design System

### Theme Customization
```php
// Customize color scheme
add_filter('rb_theme_colors', function($colors) {
    return array(
        'primary' => '#2563eb',
        'secondary' => '#64748b', 
        'success' => '#10b981',
        'warning' => '#f59e0b',
        'error' => '#ef4444'
    );
});

// Enable/disable dark mode
add_filter('rb_enable_dark_mode', '__return_true');

// Customize breakpoints
add_filter('rb_breakpoints', function($breakpoints) {
    return array(
        'sm' => '640px',
        'md' => '768px', 
        'lg' => '1024px',
        'xl' => '1280px'
    );
});
```

### Component Customization
```php
// Customize booking steps
add_filter('rb_booking_steps', function($steps) {
    $steps['step4'] = array(
        'title' => 'Special Requests',
        'template' => 'booking-step-special-requests.php'
    );
    return $steps;
});

// Add custom dashboard widgets
add_action('rb_dashboard_widgets', function() {
    rb_add_dashboard_widget('weather', array(
        'title' => 'Weather Forecast',
        'callback' => 'render_weather_widget'
    ));
});
```

## 📊 Analytics & Reporting

### Built-in Analytics
- **Booking Trends**: Theo dõi xu hướng đặt bàn
- **Revenue Analytics**: Phân tích doanh thu theo thời gian
- **Customer Insights**: Hành vi và preference khách hàng
- **Performance Metrics**: Tỷ lệ conversion, cancellation
- **Staff Performance**: Hiệu suất xử lý của nhân viên

### Export Options
- **CSV Export**: Raw data cho Excel analysis
- **PDF Reports**: Professional báo cáo in ấn
- **JSON API**: Integration với external systems
- **Scheduled Reports**: Tự động gửi email báo cáo

## 🌐 Multi-language & Localization

### Supported Languages
- Vietnamese (vi_VN) - Default
- English (en_US)
- Japanese (ja_JP)
- Korean (ko_KR)
- Chinese Simplified (zh_CN)

### RTL Support
Plugin hỗ trợ Right-to-Left languages với:
- Automatic direction detection
- Mirrored layouts cho Arabic/Hebrew
- RTL-optimized animations

## 🔐 Security & Performance

### Security Features
- **Nonce Validation** cho tất cả AJAX requests
- **Input Sanitization** với WordPress functions
- **SQL Injection Protection** với prepared statements
- **CSRF Protection** với token validation
- **Rate Limiting** cho booking submissions

### Performance Optimizations
- **Lazy Loading** cho images và components
- **CSS/JS Minification** trong production
- **Database Query Optimization** với indexes
- **Caching Support** cho analytics data
- **CDN Ready** với static asset optimization

## 🧪 Testing & Quality Assurance

### Automated Testing
```bash
# PHPUnit tests
composer test

# JavaScript tests  
npm test

# E2E tests với Playwright
npm run test:e2e

# Accessibility testing
npm run test:a11y
```

### Browser Compatibility
- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ✅ Mobile Safari (iOS 14+)
- ✅ Chrome Mobile (Android 10+)

## 🚀 Installation & Setup

### Requirements
- WordPress 5.0 hoặc cao hơn
- PHP 7.2 hoặc cao hơn
- MySQL 5.6 hoặc cao hơn
- Trình duyệt hiện đại hỗ trợ ES6

### Installation

#### Method 1: WordPress Admin (Recommended)
1. Vào **Plugins → Add New**
2. Chọn **Upload Plugin**
3. Tải lên file `modern-restaurant-booking.zip`
4. Click **Install Now**
5. Click **Activate Plugin**
6. Vào menu **Restaurant Booking** trong WordPress admin

#### Method 2: Manual Upload
1. Tải bản phát hành mới nhất từ GitHub
2. Giải nén vào `wp-content/plugins/modern-restaurant-booking/`
3. Vào menu **Plugins**
4. Tìm "Modern Restaurant Booking Manager"
5. Click **Activate**

#### Method 3: CLI (Developer)
```bash
wp plugin install modern-restaurant-booking --activate
```

### Quick Start
1. Upload plugin folder `modern-restaurant-booking/` vào `wp-content/plugins/`
2. Vào menu **Plugins** trong WordPress admin
3. Tìm "Modern Restaurant Booking Manager" và click **Activate**
4. Plugin sẽ tự động:
   - Tạo database tables
   - Enqueue design system assets
   - Initialize theme manager
   - Register AJAX endpoints
5. Vào **Restaurant Booking → Settings** để cấu hình:
   - Thiết lập multiple locations
   - Customize booking settings
   - Cấu hình email templates

### First Time Setup

Sau khi kích hoạt, plugin sẽ tự động:
- ✅ Tạo database tables cần thiết
- ✅ Thiết lập cấu hình mặc định
- ✅ Đăng ký custom post types & taxonomies
- ✅ Khởi tạo design system assets
- ✅ Đăng ký AJAX endpoints

**Next Steps:**
1. **Configure Locations**
   - Vào **Restaurant Booking → Locations**
   - Thêm các chi nhánh
   - Cấu hình giờ hoạt động & số lượng bàn

2. **Customize Settings**
   - Vào **Restaurant Booking → Settings**
   - Thiết lập advance notice (mặc định: 90 ngày)
   - Cấu hình buffer time (mặc định: 30 phút)
   - Bật/tắt các tính năng bổ sung

3. **Setup Email Templates**
   - Vào **Restaurant Booking → Email Templates**
   - Tùy chỉnh nội dung email xác nhận
   - Thêm branding của nhà hàng

4. **Test Booking Widget**
   - Tạo một trang test
   - Thêm shortcode: `[modern_restaurant_booking]`
   - Kiểm tra 3 bước đặt bàn

### Advanced Setup

#### Development Environment
```bash
# Clone repository
git clone https://github.com/your-repo/modern-restaurant-booking.git
cd modern-restaurant-booking

# Install dependencies
composer install
npm install

# Build assets
npm run build

# Start development server
npm run dev

# Run tests
npm test
npm run test:e2e
```

#### Docker Setup (Optional)
```bash
docker-compose up -d
cd docker/wordpress
wordpress setup
```

### Migration from v1.x
Plugin sẽ tự động migrate dữ liệu khi nâng cấp:
1. Backup database trước khi nâng cấp
2. Deactivate phiên bản cũ
3. Upload phiên bản mới
4. Activate plugin
5. Vào **Restaurant Booking → Migration Status**
6. Plugin sẽ migrate toàn bộ bookings, customers, locations

**Lưu ý:** Shortcode widget cũ vẫn hoạt động nhưng sử dụng giao diện hiện đại mới.

### Troubleshooting Installation

**Plugin không xuất hiện trong menu:**
- Xóa cache trình duyệt
- Xóa WordPress transients: `wp transient delete-expired`
- Kiểm tra PHP error logs

**Database tables không được tạo:**
- Đảm bảo user WordPress có quyền CREATE TABLE
- Kiểm tra log trong `wp-content/debug.log`
- Chạy thủ công: `wp db query < includes/database/schema.sql`

**Theme không áp dụng:**
- Xóa toàn bộ cache (trình duyệt, plugin, server)
- Kiểm tra console browser xem có lỗi CSS/JS
- Xác minh `assets/css/design-system.css` đã được load

**Dark mode không hoạt động:**
- Xác minh `theme-manager.js` được enqueue
- Kiểm tra localStorage trong trình duyệt
- Thử ở chế độ incognito để loại bỏ extension

## 🎨 Customization Guide

### CSS Customization

#### Theme Colors
```css
/* In your child theme hoặc custom CSS */
:root {
  /* Primary colors */
  --rb-primary-50: #eff6ff;
  --rb-primary-500: #your-primary;
  --rb-primary-600: #your-primary-dark;

  /* Accent colors */
  --rb-success: #10b981;
  --rb-warning: #f59e0b;
  --rb-error: #ef4444;
}
```

#### Typography
```css
:root {
  --rb-font-sans: 'Your Font', system-ui, sans-serif;
  --rb-text-base: 1rem;
  --rb-text-lg: 1.125rem;
  --rb-text-sm: 0.875rem;
}
```

#### Animation Speed
```css
:root {
  --rb-transition-fast: 150ms ease-in-out;
  --rb-transition-base: 250ms ease-in-out;
  --rb-transition-slow: 350ms ease-in-out;
}

/* Disable animations for specific component */
.my-component {
  --rb-transition-base: 0ms;
}
```

### JavaScript Hooks

#### Booking Widget Events
```javascript
// Before booking submission
rbBooking.addHook('beforeSubmit', (data) => {
  gtag('event', 'booking_submit', data);
  return data;
});

// After successful booking
rbBooking.addHook('bookingSuccess', (response) => {
  alert(`Booking confirmed: ${response.booking_id}`);
});

// On booking error
rbBooking.addHook('bookingError', (error) => {
  console.error('Booking failed:', error);
});
```

#### Theme Change Events
```javascript
rbThemeManager.onThemeChange((theme) => {
  console.log('Theme changed to:', theme);
  // Update component state if needed
});

rbThemeManager.setTheme('dark');

const current = rbThemeManager.getCurrentTheme();
```

#### Dashboard Events
```javascript
rbDashboard.addHook('statsLoaded', (stats) => {
  mixpanel.track('Dashboard Stats', stats);
});

rbDashboard.addHook('locationChange', (locationId) => {
  console.log('Switched to location:', locationId);
});
```

### WordPress Filters

#### Customize Booking Settings
```php
add_filter( 'rb_booking_settings', function( $settings ) {
    $settings['advance_days'] = 60; // Instead of default 90
    $settings['buffer_time'] = 45;  // Instead of default 30
    return $settings;
} );
```

#### Modify Email Template
```php
add_filter( 'rb_confirmation_email_body', function( $body, $booking ) {
    $body .= '\nThank you for booking with us!';
    return $body;
}, 10, 2 );
```

#### Add Custom Dashboard Widget
```php
add_filter( 'rb_dashboard_widgets', function( $widgets ) {
    $widgets[] = [
        'id'    => 'weather',
        'title' => 'Restaurant Weather',
        'callback' => 'my_weather_widget',
    ];
    return $widgets;
} );

function my_weather_widget() {
    echo '<div id="weather-widget"></div>';
}
```

### Extending Components

#### Custom Booking Step
```php
add_filter( 'rb_booking_steps', function( $steps ) {
    $steps['step4'] = [
        'title'    => 'Special Requests',
        'template' => 'booking-step-4.php',
    ];
    return $steps;
} );
```

#### Custom Admin Page
```php
add_action( 'admin_menu', function() {
    add_submenu_page(
        'rb-dashboard',
        'Custom Reports',
        'Custom Reports',
        'manage_options',
        'rb-custom-reports',
        'render_custom_reports'
    );
} );

function render_custom_reports() {
    echo '<div class="wrap"><h1>Custom Reports</h1></div>';
}
```
 
## 🤝 Contributing

### Development Workflow
1. Fork repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

### Coding Standards
- **PHP**: WordPress Coding Standards
- **JavaScript**: ESLint + Prettier
- **CSS**: Stylelint với custom rules
- **Commit Messages**: Conventional Commits

## 📝 Changelog

### Version 2.0.0 (Current)
- ✨ **Complete UI redesign** dựa trên Figma community design
- ✨ **Dark mode support** với automatic detection
- ✨ **Advanced analytics** dashboard với charts
- ✨ **Calendar view** cho booking management
- ✨ **Visual table management** với floor plan
- ✨ **Multi-location** super admin dashboard
- 🔧 **Performance improvements** và code optimization
- 🐛 **Bug fixes** và stability improvements

### Migration từ v1.x
Plugin tự động migrate data khi upgrade. Backup database trước khi upgrade để đảm bảo an toàn.

## 🆘 Support & Documentation

- 📖 **Full Documentation**: [docs.yoursite.com](https://docs.yoursite.com)
- 🎥 **Video Tutorials**: [youtube.com/playlist](https://youtube.com/playlist)
- 💬 **Community Forum**: [community.yoursite.com](https://community.yoursite.com)
- 🐛 **Bug Reports**: [github.com/issues](https://github.com/issues)
- 📧 **Email Support**: support@yoursite.com

## 📄 License

GPL v2 or later. Free to use, modify and distribute.

---

**Built with ❤️ for modern restaurants**
*Powered by WordPress • Designed with Figma • Crafted for performance*
