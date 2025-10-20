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
restaurant-booking-manager/
├── restaurant-booking-manager.php      # Plugin bootstrap
├── admin/
│   ├── class-admin.php                 # Modern admin interface
│   ├── class-admin-dashboard.php       # Super admin dashboard
│   └── partials/
│       ├── admin-dashboard.php         # Main admin view
│       ├── locations-management.php    # Multi-location manager
│       ├── analytics-reports.php       # Advanced analytics
│       └── email-templates.php         # Email customization
├── includes/
│   ├── class-booking-manager.php       # Core booking logic
│   ├── class-modern-ui.php             # UI component manager
│   ├── class-theme-manager.php         # Dark/Light mode handler
│   ├── class-analytics.php             # Advanced analytics engine
│   ├── class-notification-system.php   # Real-time notifications
│   └── services/
│       ├── class-calendar-service.php  # Calendar operations
│       ├── class-analytics-service.php # Data analysis
│       └── class-export-service.php    # Data export functionality
├── public/
│   ├── class-modern-booking-widget.php # New booking interface
│   ├── class-modern-portal.php         # Restaurant manager portal
│   └── partials/
│       ├── modern-booking-modal.php    # Booking widget template
│       ├── modern-dashboard.php        # Manager dashboard
│       ├── calendar-view.php           # Calendar interface
│       └── table-management.php        # Table floor plan
├── assets/
│   ├── css/
│   │   ├── modern-booking.css          # Customer booking styles
│   │   ├── modern-portal.css           # Manager portal styles
│   │   ├── modern-admin.css            # Admin panel styles
│   │   └── components/                 # Reusable CSS components
│   ├── js/
│   │   ├── modern-booking.js           # Booking widget logic
│   │   ├── modern-portal.js            # Portal interactions
│   │   ├── analytics-charts.js         # Charts & visualization
│   │   └── theme-switcher.js           # Dark/Light mode toggle
│   └── images/
│       ├── icons/                      # SVG icon set
│       └── illustrations/              # UI illustrations
├── templates/
│   ├── emails/                         # Modern email templates
│   └── pdf/                           # PDF receipt templates
└── languages/                         # Multi-language support
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

### Quick Start
1. Upload plugin folder vào `wp-content/plugins/`
2. Activate plugin trong WordPress admin
3. Vào **Restaurant Booking → Setup Wizard**
4. Follow guided setup cho initial configuration
5. Customize design trong **Appearance → Theme Settings**

### Advanced Setup
```bash
# Clone repository
git clone https://github.com/your-repo/modern-restaurant-booking.git

# Install dependencies
composer install
npm install

# Build assets
npm run build

# Setup development environment
npm run dev
```

## 🎨 Customization Guide

### CSS Customization
```css
/* Override CSS variables */
:root {
  --rb-primary: #your-color;
  --rb-border-radius: 8px;
  --rb-font-family: 'Your Font', sans-serif;
}

/* Custom component styles */
.rb-booking-widget.my-theme {
  /* Your custom styles */
}
```

### JavaScript Hooks
```javascript
// Before booking submission
rbBooking.addHook('beforeSubmit', function(data) {
  // Modify booking data
  return data;
});

// After successful booking
rbBooking.addHook('bookingSuccess', function(response) {
  // Custom success handling
});
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
