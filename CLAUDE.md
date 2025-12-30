# Project Context: German Affiliate Sites Template

## CRITICAL: THIS IS THE GERMAN VERSION
**This codebase is specifically for GERMAN-language affiliate sites targeting the German market (Germany, Austria, Switzerland). All content, routes, and text MUST be in GERMAN.**

## Overview
This Laravel project serves as a **comprehensive template for German-language affiliate websites** for the Amazon PartnerNet (Amazon.de) affiliate program. It's designed to be reused across **multiple niche-specific affiliate sites** with sophisticated content management and comparison functionality.

**Key Differences from Dutch Version:**
- Language: German (Deutsch, not Dutch/Nederlands)
- Locale: `lang="de"` and `Carbon::setLocale('de')`
- Affiliate Programs: Amazon PartnerNet (Amazon.de) - primary focus
- Routes: German URLs (`/produkte` not `/producten`, `/beste-marken`, `/vergleichen`)
- Number Format: German/Euro style (€1.299,99 - same as Dutch)
- Target Markets: Germany, Austria, Switzerland (DACH region)

## Business Model & Strategy
- **Affiliate Programs**: Amazon PartnerNet (Amazon.de) - primary focus
- **Multi-Site Network**: Reusable template for different product niches
- **Niche Examples**:
  - Dual basket air fryers
  - Massage guns
  - Home trainers
  - Robot lawn mowers
  - Other consumer electronics/appliances
- **Differentiation Strategy**: Same codebase, different databases per site. Name, content, product images, product text, blogs, reviews - everything managed by the database.
- **Revenue Model**: Commission-based affiliate sales through Amazon PartnerNet (Amazon.de)

## Technical Architecture

### Core Framework
- **Backend**: Laravel (PHP)
- **Frontend**: Tailwind CSS + Alpine.js for interactivity
- **Database**: MySQL (separate database per site)
- **Deployment**: Single codebase deployed multiple times

### Database Structure
- **Products**: Core product data with EAN (later ASIN), specifications, images, prices
- **Product Specifications**: Grouped specifications (`group` field) for organized display
- **Content Blocks**: Dynamic content management (`key` → `content` mapping) - German content
- **Settings**: Site-specific configuration (`getSetting()` helper)
- **Reviews & Blogs**: Content types for SEO and user engagement - German content

### Content Management System
- **Dynamic Content**: All text content stored in database via `getContent()` helper
- **AI-Generated Descriptions**: Prevents duplicate manufacturer content
- **Multi-Format Support**: HTML/Markdown content with intelligent parsing
- **Fallback System**: Graceful content fallbacks for missing entries

## Key Features & Components

### 1. Smart Product Selection Algorithms
- **Homepage Smart Picks**: 4-tier intelligent selection
  - Best savings (highest euro discount + good rating)
  - Best rated (4.5+ stars)
  - Newest additions (< 14 days)
  - Popular fallback (random quality products)
- **Top 5 Algorithm**: Excellence scoring with exclusions to prevent overlap
- **Dynamic Badging**: Context-aware badges (Angebot, Top-Wahl, Neu, Beliebt)

### 2. Advanced Product Comparison System
- **Multi-Device Support**: 
  - Desktop: Full table layout with expandable descriptions
  - Mobile/Tablet: Scrollable table with compact columns
- **Specification Grouping**: Organized by categories with collapsible sections
- **Smart Text Handling**: Auto-expanding for long specifications (>50 chars)
- **Visual Comparison**: Side-by-side spec comparison for easy decision making

### 3. Responsive Design Excellence
- **Mobile-First**: Optimized for mobile conversion
- **Breakpoint Strategy**: 
  - Mobile: < 768px (hamburger menu, sticky CTAs)
  - Tablet: 768px-1024px (hamburger menu, sidebar CTAs)
  - Desktop: ≥1024px (full navigation, desktop layout)
- **Touch Optimization**: 44px+ touch targets, proper spacing
- **Performance**: Optimized images, lazy loading, minimal JS

### 4. SEO Optimization Framework
- **Technical SEO**:
  - Dynamic meta tags with character limits (60/155)
  - Pagination meta tags (`rel="prev/next"`)
  - Schema.org markup (Product, CollectionPage, ItemList)
  - Canonical URLs for filtered pages
- **Content SEO**:
  - AI-generated unique descriptions
  - Descriptive image alt texts
  - Proper heading hierarchy (H1 → H2 conversion for affiliate content)
- **Affiliate Compliance**: Consistent `rel="nofollow sponsored"` attributes

### 5. Black Friday Campaign System
- **Configuration**: Centralized config with yearly date windows
- **Conditional Display**: Based on date ranges or preview flags (`?bf=on`)
- **Cross-Site Consistency**: Shared Black Friday styling and functionality
- **Performance**: Minimal impact when inactive

## Development Patterns & Best Practices

### Responsive Development
```php
// Standard grid pattern
grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4

// Breakpoint philosophy
- Mobile: Single column, essential content only
- Tablet: Dual column, hamburger menu
- Desktop: Multi-column, full navigation
```

### Content Management
```php
// Dynamic content pattern
{!! getContent('homepage.hero', ['maand' => $huidigeMaand]) !!}

// Settings pattern  
{{ getSetting('site_name', 'Default Name') }}
```

### SEO Patterns
```php
// Image alt text template (German)
alt="{{ $product->title }} - {{ $product->brand ?? 'Produkt' }} Test und Vergleich - €{{ number_format($product->price ?? 0, 2, ',', '.') }}"

// Affiliate link standard
rel="nofollow sponsored" target="_blank"
```

### Database Relationships
- `Product hasMany ProductSpecification`
- `Product hasOne Review`
- `Product belongsToMany BlogPost`
- `ProductSpecification` has `group` field for categorization

## File Structure & Key Components

### Controllers
- **HomeController**: Smart Picks algorithm
- **ProductController**: Product listing, filtering, Top 5 algorithm
- **Comparison**: Advanced product comparison logic

### Views Architecture
- **layouts/app.blade.php**: Master template with SEO, navigation, mobile menu (German UI)
- **home.blade.php**: Smart Picks showcase (German)
- **produkte/index.blade.php**: Product filtering and grid (German)
- **produkte/show.blade.php**: Product details with grouped specifications (German)
- **vergleichen.blade.php**: Responsive comparison table (German)

### Key Features Implementation
- **Hamburger Menu**: Alpine.js with smooth animations, backdrop blur
- **Product Cards**: Consistent height, hover effects, conversion optimization
- **Sticky Mobile CTAs**: Context-aware positioning for mobile conversion
- **Collapsible Specs**: Progressive disclosure with Alpine.js transitions

## Content Strategy

### AI Content Integration
- **Product Descriptions**: AI-generated to avoid duplicate content
- **Content Parsing**: Intelligent HTML/text detection and formatting
- **Fallback System**: Graceful degradation for missing content

### SEO Content Patterns
- **Dynamic Titles**: Template-based with niche insertion
- **Meta Descriptions**: Contextual with proper character limits
- **Structured Data**: Product, Review, FAQ, and Collection schemas

## Development Guidelines

### Making Changes
1. **Consider Multi-Site Impact**: Changes affect 20+ affiliate sites
2. **Test Responsiveness**: Always test mobile, tablet, desktop
3. **SEO Awareness**: Maintain/improve search engine optimization
4. **Performance First**: Optimize for mobile conversion
5. **Accessibility**: Maintain WCAG compliance

### Database Considerations
- **Migration Planning**: Schema changes require careful coordination
- **Content Consistency**: Ensure content templates work across all niches
- **Performance**: Optimize queries for product listings and comparisons

### Debugging & Testing
- **Multi-Device Testing**: Essential for responsive features
- **Content Variations**: Test with different product data sets
- **SEO Validation**: Use schema validators and SEO tools
- **Affiliate Link Testing**: Ensure proper tracking and compliance

## Common Commands & Tools

### Laravel Artisan Commands
```bash
# Current: Fetch Bol.com products (temporary, for demo site)
php artisan app:fetch-bol-product {ean}

# Future: Amazon.de product data (TO BE IMPLEMENTED after approval)
php artisan fetch:amazon-products

# Update pricing (TO BE IMPLEMENTED)
php artisan update:prices

# Generate content (German)
php artisan generate:blog
php artisan generate:review
php artisan generate:content-blocks

# SEO maintenance
php artisan seo:fix-all
```

### Development Workflow
1. **Local Development**: Test with sample product data
2. **Responsive Review**: Check mobile/tablet/desktop layouts
3. **SEO Validation**: Verify meta tags and schema markup
4. **Cross-Site Testing**: Ensure compatibility across niches
5. **Performance Check**: Validate loading times and optimization

## Performance & Optimization

### Frontend Optimization
- **Tailwind CSS**: Utility-first, production builds
- **Alpine.js**: Minimal JavaScript footprint
- **Image Optimization**: Lazy loading, proper sizing
- **Critical CSS**: Above-fold optimization

### Database Optimization
- **Efficient Queries**: Eager loading, pagination
- **Caching Strategy**: Content blocks, settings caching
- **Index Strategy**: Optimized for product filtering and search

## Security Considerations

### Affiliate Link Security
- **Proper Attribution**: Consistent `rel="nofollow sponsored"`
- **Link Validation**: Secure affiliate URL generation
- **User Privacy**: Respect tracking preferences

### General Security
- **Input Validation**: Secure form handling
- **CSRF Protection**: Laravel built-in protection
- **Environment Separation**: Production vs development configs

---

## Quick Reference

### Key Helpers
- `getSetting($key, $default)` - Site-specific settings
- `getContent($key, $variables)` - Dynamic content blocks (in GERMAN/Deutsch)
- `getBolAffiliateLink($url, $title)` - Bol.com affiliate links (TEMPORARY for demo)
- `getAmazonDeAffiliateLink($asin, $title)` - Amazon.de affiliate links (TO BE IMPLEMENTED)
- `formatPrice($amount)` - Format price in German/Euro style (€1.299,99)

### Common Patterns
- Responsive grids: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`
- Mobile menu: Hamburger until 1024px (`lg:hidden`)
- SEO images: Descriptive alt text with product info
- Specifications: Grouped, collapsible sections

### SEO Checklist
- ✅ Meta tags with proper character limits
- ✅ Schema.org markup for products/listings
- ✅ Canonical URLs for filtered pages
- ✅ Pagination meta tags
- ✅ Affiliate link compliance (`nofollow sponsored`)
- ✅ Descriptive image alt texts

---

## CRITICAL: NO EMOJIS POLICY

**NEVER use emojis in ANY context:**
- NO emojis in code comments
- NO emojis in UI/views (use SVG icons instead)
- NO emojis in terminal output or logs
- NO emojis in documentation
- NO emojis in error messages
- NO emojis in responses to user

**Always use:**
- SVG icons for visual elements in UI
- Plain text for status messages
- Professional, emoji-free communication

**This is non-negotiable. The user has explicitly forbidden all emoji usage.**

---

## CRITICAL: GERMAN LANGUAGE POLICY

**EVERYTHING must be in GERMAN (Deutsch):**
- All content blocks in database (German text)
- All AI prompts for content generation (German output)
- All routes and URLs (use `/produkte` NOT `/products`)
- All meta tags and SEO content (German)
- All user-facing text in views (German)
- All database seed content (German)
- Comments in code can remain English for developer clarity

**Route Translations (Dutch → German):**
- `/producten` → `/produkte`
- `/producten/{slug}` → `/produkte/{slug}`
- `/beste-merken` → `/beste-marken` (same as Dutch!)
- `/informatie/{slug}` → `/information/{slug}`
- `/vergelijken` → `/vergleichen`
- `/reviews` → `/testberichte` (or keep `/reviews`)
- `/blogs` → `/blog` or `/ratgeber`

**Affiliate Program:**
- PRIMARY: Amazon PartnerNet (Amazon.de)
- TEMPORARY: Bol.com (for demo site before Amazon approval)
- Helper: `getAmazonDeAffiliateLink()` (to be implemented)
- Current: `getBolAffiliateLink()` (temporary)

**Number Formatting:**
- German/Euro: €1.299,99 (SAME as Dutch - comma for decimals, period for thousands)
- Use `number_format($price, 2, ',', '.')` for prices

**Locale Settings:**
```php
Carbon::setLocale('de'); // German locale
<html lang="de"> // German language
```

**Development Strategy:**
1. PHASE 1 (NOW): German UI/content + Bol.com products (for demo/approval)
2. PHASE 2 (AFTER APPROVAL): Switch to Amazon.de API + ASIN support

**This is the GERMAN template - double check everything is in German (Deutsch) before deploying!**
