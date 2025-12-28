# Site Generator System

## Overview

Automated site setup system that generates ALL content via OpenAI - similar to the team generation system. Reduces setup time from **2+ hours to 5 minutes**.

---

## Quick Start

### Basic Usage (Interactive)

```bash
php artisan site:generate
```

You'll be prompted for:
- Niche (e.g., "airfryers met dubbele compartimenten")
- Unique Focus (optional, e.g., "geschikt voor zowel koelen als verwarmen")
- Domain (e.g., "airfryerxpert.nl")
- Primary color (default: #7c3aed)
- Bol.com Site ID (optional)

### Advanced Usage (Non-interactive)

```bash
php artisan site:generate \
  --niche="Airfryers met dubbele compartimenten" \
  --unique-focus="Geschikt voor gezinnen" \
  --domain="airfryerxpert.nl" \
  --primary-color="#7c3aed" \
  --bol-site-id="12345" \
  --fetch-products
```

---

## What Gets Generated

### 1. **Settings** (6 entries)
Generated via OpenAI based on niche:
- `site_name` - Domain name
- `site_niche` - Full niche description
- `primary_color` - Site color scheme
- `font_family` - Typography (Poppins)
- `tone_of_voice` - Conversion-optimized tone
- `target_audience` - Specific target audience

### 2. **Content Blocks** (23 entries)
All text content for:
- **Homepage**: hero, intro, seo1, seo2 (4 blocks)
- **Products Page**: titles, subtitles, buttons, info (5 blocks)
- **Top 5 Page**: hero, CTA, SEO content (4 blocks)
- **Brands Page**: titles, info blocks (4 blocks)
- **Reviews Page**: hero, intro, SEO (3 blocks)
- **Blogs Page**: hero, intro, SEO (3 blocks)

### 3. **Team Members** (3 fictional personas)
Uses existing `team:generate` command:
- 3 diverse team members with unique expertise
- Full bios (900-1200 words each)
- Different writing styles for content variety
- Legally compliant (fictional, redactioneel)

### 4. **Favicon** (SVG gradient)
Generated via `generate:favicon` command:
- SVG gradient with first letter of niche
- Saves to `/public/favicon.svg`
- Updates `favicon_url` setting in database
- Uses primary color with darker gradient

### 5. **Blog Variations** (~96 variations)
Backwards compatibility system:
- Doelgroepen, problemen, themas, stijlen
- Used by older blog generation logic
- Still functional for existing sites

### 6. **Blog Templates** (60 templates)
New template-based blog generation system:
- 60 reusable blog templates
- Each template: title, outline, SEO keywords
- Can be reused after 60 days
- Infinite blog possibilities with template rotation

### 7. **Product Blog Templates** (20 templates)
Product-focused blog templates:
- 20 templates for product-focused content
- Scenario-based (how-to, comparison, review style)
- Used by `php artisan app:generate-product-blog`
- Optimized for conversion

### 8. **Information Pages** (5-7 pages)
Decision-stage content pages:
- 5-7 complete information articles
- Topics like "Welke maat past bij jou?", "Enkele of dubbele lade?"
- Full content generation (intro, sections, conclusion)
- SEO-optimized with meta descriptions
- Help users make purchase decisions

### 9. **Seed Blog Posts** (3 draft ideas)
Blog post concepts ready for full generation:
- Koopgids, vergelijking, uitleg, tips
- SEO-optimized titles
- Suggested keywords
- Status: draft (generate full content later)

---

## Command Options

### Required (or will prompt)
- `--niche` - Site niche/focus
- `--domain` - Domain name
- `--primary-color` - Hex color code

### Optional
- `--unique-focus` - Additional USP/focus (e.g., "geschikt voor gezinnen")
- `--bol-site-id` - Bol.com affiliate ID (can set in .env later)
- `--bol-category-id` - Bol.com category ID for product fetching
- `--deploy-to-forge` - Automatically deploy site to Laravel Forge
- `--fetch-products` - Automatically fetch products after setup
- `--skip-team` - Don't generate team members
- `--skip-favicon` - Don't generate favicon
- `--skip-seed-blogs` - Don't generate blog ideas

### Safety
- `--force` - Overwrite existing content (requires confirmation)

---

## Forge Deployment (Fully Automated)

### Overview

The site generator can automatically deploy your site to Laravel Forge with a single command. This includes:
- Creating the site on Forge
- Installing Git repository
- Creating database with secure credentials
- Setting up all environment variables
- Obtaining SSL certificate (Let's Encrypt)
- Enabling scheduler (cron jobs)
- Deploying the code
- Running site generation remotely

**Total time: ~10-15 minutes** (including SSL certificate and content generation)

### Prerequisites

1. **Forge API Token**: Generate at https://forge.laravel.com/user-profile/api
2. **Forge Server**: You need a server already provisioned on Forge
3. **Git Repository**: Your template repo must be accessible
4. **Domain DNS**: Point your domain to your Forge server IP

### Configuration

Add to your `.env`:

```env
# Forge Configuration
FORGE_API_TOKEN=your_forge_api_token_here
FORGE_SERVER_ID=992514
FORGE_GIT_REPO_URL=https://github.com/Luuk561/template
FORGE_GIT_BRANCH=main
FORGE_PHP_VERSION=php84

# Shared Credentials (used for all sites)
BOL_CLIENT_ID=your_bol_client_id
BOL_CLIENT_SECRET=your_bol_client_secret
OPENAI_API_KEY=your_openai_key
```

### Usage

```bash
php artisan site:generate \
  --niche="Airfryers met dubbele compartimenten" \
  --domain="duofryer.nl" \
  --primary-color="#FF6B35" \
  --bol-site-id="52814" \
  --bol-category-id="73750" \
  --deploy-to-forge \
  --fetch-products
```

### What Happens During Deployment

1. **Forge API Validation** - Checks credentials
2. **Site Creation** - Creates PHP 8.4 site on Forge
3. **Git Installation** - Clones your template repository
4. **Database Creation** - Creates database with secure password
5. **Environment Setup** - Configures all `.env` variables
6. **Deployment Script** - Sets up deployment automation
7. **SSL Certificate** - Obtains Let's Encrypt SSL (waits for activation)
8. **Scheduler Setup** - Enables cron jobs for `artisan schedule:run`
9. **Initial Deployment** - Runs first deployment (composer, migrations, etc.)
10. **Remote Generation** - Runs `site:generate` on the remote server via SSH
11. **Product Fetching** - Fetches products from Bol.com (if `--fetch-products` used)

### Example Output

```
========================================
  AFFILIATE SITE GENERATOR
========================================

Forge API credentials validated successfully.

Generation Plan:
┌──────────────────┬────────────────────────────────────────┐
│ Item             │ Details                                │
├──────────────────┼────────────────────────────────────────┤
│ Niche            │ Airfryers met dubbele compartimenten   │
│ Domain           │ duofryer.nl                            │
│ Deploy to Forge  │ YES                                    │
│ BOL Site ID      │ 52814                                  │
│ BOL Category ID  │ 73750                                  │
└──────────────────┴────────────────────────────────────────┘

Continue with site generation? (yes/no) [yes]:
> yes

========================================
  DEPLOYING TO FORGE
========================================

Step 1/9: Creating site on Forge...
Domain: duofryer.nl
✓ Site created

Step 2/9: Installing Git repository...
✓ Git repository installed

Step 3/9: Creating database...
✓ Database created: duofryer_nl

Step 4/9: Updating environment variables...
✓ Environment configured

Step 5/9: Updating deployment script...
✓ Deployment script updated

Step 6/9: Enabling quick deploy...
✓ Quick deploy enabled

Step 7/9: Obtaining SSL certificate...
✓ SSL certificate obtained (waiting for activation...)
✓ SSL certificate activated

Step 8/9: Enabling scheduler...
✓ Scheduler enabled

Step 9/9: Deploying site...
✓ Deployment started (waiting for completion...)
✓ Deployment complete

========================================
  FORGE DEPLOYMENT COMPLETE!
========================================

Site created: https://duofryer.nl
Site ID: 123456
Database: duofryer_nl

Running site:generate on remote server via SSH...

Executing command on remote server (this may take 5-10 minutes)...
✓ Remote generation completed!

========================================
  SITE FULLY DEPLOYED AND CONFIGURED!
========================================

Visit your site: https://duofryer.nl
```

### Environment Variables (Automatically Configured)

The following variables are automatically set for each deployed site:

```env
APP_NAME="DuoFryer.nl"                          # From generator
APP_URL=https://duofryer.nl                     # From domain
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:xxx                              # Auto-generated

DB_DATABASE=duofryer_nl                         # Auto-generated
DB_USERNAME=forge
DB_PASSWORD=xxx                                 # Auto-generated (32 chars)

BOL_CLIENT_ID=xxx                               # From your .env (shared)
BOL_CLIENT_SECRET=xxx                           # From your .env (shared)
BOL_SITE_ID=52814                               # From command input
BOL_CATEGORY_ID=73750                           # From command input

OPENAI_API_KEY=xxx                              # From your .env (shared)

# All standard Laravel settings
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
LOG_CHANNEL=stack
```

### Deployment Script

The following deployment script is automatically configured:

```bash
cd /home/forge/$FORGE_SITE_PATH
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader --no-dev

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan config:cache
    $FORGE_PHP artisan route:cache
    $FORGE_PHP artisan view:cache
    $FORGE_PHP artisan sitemap:generate
fi
```

### Troubleshooting Forge Deployment

**API Token Invalid**
```
Error: Forge API token and server ID are required
```
Solution: Check that `FORGE_API_TOKEN` and `FORGE_SERVER_ID` are set in `.env`

**SSL Certificate Timeout**
```
Error: SSL certificate installation timed out after 60 seconds
```
Solution: SSL certificates can take 1-2 minutes. Try again, or manually activate in Forge UI.

**Deployment Timeout**
```
Error: Deployment timed out after 180 seconds
```
Solution: Large deployments may take longer. Check Forge UI for deployment status.

**Remote Generation Failed**
```
Error: Remote generation failed
```
Solution: SSH into the server and run manually:
```bash
ssh forge@duofryer.nl
cd duofryer.nl
php artisan site:generate --niche="..." --domain="..." --fetch-products
```

### Manual Steps After Forge Deployment

The deployment is fully automated, but you may want to:

1. **Check the site**: Visit `https://your-domain.nl` to verify
2. **Review Forge dashboard**: Check site settings, deployments, etc.
3. **Monitor first deployment**: Watch deployment logs in Forge
4. **Test product fetching**: Verify products are being fetched correctly

---

## Safety Features (Backwards Compatibility)

### For Existing Sites (30+ sites)
The command **WILL NOT RUN** if content exists, unless `--force` is used:

```bash
# Safe: checks for existing content
php artisan site:generate

# Output if content exists:
# WARNING: Existing content detected!
# Use --force to overwrite existing content (DANGEROUS!)
```

### Protection Checks
1. **Detects existing content:**
   - Settings table has entries
   - Content blocks exist
   - Team members exist
   - Blog posts exist

2. **Requires double confirmation:**
   - First: `--force` flag required
   - Second: Interactive "Are you ABSOLUTELY SURE?" prompt

3. **Transaction-based:**
   - All DB operations use transactions
   - Rollback on failure
   - Logging for audit trail

---

## Architecture

### Similar to Team Generation System

**Team System:**
```
User runs: php artisan team:generate
  ↓
OpenAIService generates profiles
  ↓
Save to team_members table
  ↓
Views read from database
```

**Site Generation System:**
```
User runs: php artisan site:generate
  ↓
SiteContentGeneratorService generates content via OpenAI
  ↓
SiteGeneratorService orchestrates & saves to DB
  ↓
Views read via getContent() and getSetting()
```

### Service Architecture

**SiteContentGeneratorService** (`app/Services/SiteContentGeneratorService.php`)
- Dedicated OpenAI service for site content
- Methods:
  - `generateSettings()` - Site configuration
  - `generateContentBlocks()` - All page content
  - `generateBlogVariations()` - Blog variations (backwards compat)
  - `generateBlogTemplates()` - 60 blog templates
  - `generateProductBlogTemplates()` - 20 product templates
  - `generateSeedBlogPosts()` - Blog ideas
- Uses circuit breaker pattern
- Fallback content if API fails

**SiteGeneratorService** (`app/Services/SiteGeneratorService.php`)
- Orchestrates full site setup
- Methods:
  - `checkExistingContent()` - Safety checks
  - `generateSettings()` - Save to DB
  - `generateContentBlocks()` - Save to DB
  - `generateBlogVariations()` - Save to DB
  - `generateBlogTemplates()` - Save to DB
  - `generateProductBlogTemplates()` - Save to DB
  - `generateInformationPages()` - Generate & save info pages
  - `generateSeedBlogPosts()` - Save to DB
- Transaction-based DB operations
- Cache invalidation

**GenerateSite Command** (`app/Console/Commands/Site/GenerateSite.php`)
- User-facing Artisan command
- Input validation
- Safety checks
- Progress reporting (14 steps)
- Delegates to existing commands (team:generate, generate:favicon, fetch:bol-products)

---

## Workflow Comparison

### OLD WORKFLOW (2+ hours)
```
1. Domeinnaam + server setup
2. SSH in server
3. Tinker: 23+ content blocks (handmatig typen!)
4. Tinker: 6+ settings (handmatig typen!)
5. Favicon maken en uploaden
6. .env configureren
7. php artisan fetch:bol-products
8. Cron jobs aanzetten
9. Deploy
```

### NEW WORKFLOW (5 minutes)
```
1. Domeinnaam + server setup
2. SSH in server
3. php artisan site:generate --niche="..." --domain="..." --fetch-products
4. Deploy - KLAAR!
```

**Time saved: ~90% reduction**

---

## Generation Steps (14 Total)

The command runs through 14 automated steps:

1. **[1/14]** Generate settings (6 entries)
2. **[2/14]** Generate content blocks (23 entries, 30-60 seconds)
3. **[3/14]** Generate team members (3 members via team:generate)
4. **[4/14]** Generate favicon (SVG gradient via generate:favicon)
5. **[8/14]** Generate blog variations (~96 variations, backwards compat)
6. **[9/14]** Generate blog templates (60 templates, 60-90 seconds)
7. **[10/14]** Generate product blog templates (20 templates, 60-90 seconds)
8. **[11/14]** Generate information pages (5-7 pages, 120-180 seconds)
9. **[12/14]** Generate seed blog posts (3 draft ideas)
10. **[13/14]** Fetch products (if --fetch-products flag used)
11. **[14/14]** Show final summary and next steps

**Total time: ~5-7 minutes** (depending on OpenAI API speed)

---

## Example Usage

### Example 1: New Airfryer Site
```bash
php artisan site:generate \
  --niche="Airfryers met dubbele compartimenten" \
  --domain="duofryer.nl" \
  --primary-color="#FF6B35" \
  --bol-site-id="52814" \
  --fetch-products
```

**Output:**
```
========================================
  AFFILIATE SITE GENERATOR
========================================

Safety check: Database is empty. Safe to proceed.

Generation Plan:
┌──────────────────┬────────────────────────────────────────┐
│ Item             │ Details                                │
├──────────────────┼────────────────────────────────────────┤
│ Niche            │ Airfryers met dubbele compartimenten   │
│ Domain           │ duofryer.nl                            │
│ Primary Color    │ #FF6B35                                │
│ Bol Site ID      │ 52814                                  │
│ Settings         │ 6 entries                              │
│ Content Blocks   │ 23 entries                             │
│ Team Members     │ 3 fictional team members               │
│ Favicon          │ SVG gradient                           │
│ Blog Variations  │ ~96 variations                         │
│ Blog Templates   │ 60 templates                           │
│ Product Templates│ 20 templates                           │
│ Info Pages       │ 5-7 pages                              │
│ Seed Blogs       │ 3 draft blog post ideas                │
│ Products         │ Will fetch after setup                 │
└──────────────────┴────────────────────────────────────────┘

Continue with site generation? (yes/no) [yes]:
> yes

[1/14] Generating settings...
✓ Settings generated (6 entries)

[2/14] Generating content blocks (30-60 seconds)...
✓ Generated 23 content blocks

[3/14] Generating team members...
✓ Created 3 team members

[4/14] Generating favicon...
✓ Favicon generated: /public/favicon.svg

[8/14] Generating blog variations...
✓ Generated 96 blog variations

[9/14] Generating 60 blog templates via OpenAI (60-90 seconds)...
✓ Generated 60 blog templates

[10/14] Generating 20 product blog templates via OpenAI (60-90 seconds)...
✓ Generated 20 product blog templates

[11/14] Generating 5-7 information pages via OpenAI (120-180 seconds)...
✓ Generated 6 information pages

[12/14] Generating seed blog posts...
✓ Generated 3 seed blog post ideas (status: draft)

[13/14] Fetching products from Bol.com...
✓ Fetched 142 products

========================================
  SITE GENERATION COMPLETE!
========================================

Site: DuoFryer.nl
Niche: Airfryers met dubbele compartimenten

What was generated:
  - 6 settings
  - 23 content blocks
  - 3 team members
  - Favicon (SVG gradient)
  - ~96 blog variations (backwards compatibility)
  - 60 general blog templates
  - 20 product blog templates
  - 6 information pages
  - 3 seed blog post ideas (draft)

Next steps:
  1. Configure .env with BOL_SITE_ID: 52814
  2. Enable cron jobs on Forge/server:
     - php artisan schedule:run (every minute)
  3. Visit your site: https://duofryer.nl

Site is ready to go live!
```

### Example 2: With Unique Focus
```bash
php artisan site:generate \
  --niche="Massage guns" \
  --unique-focus="Extra stil voor thuisgebruik" \
  --domain="massagegunxpert.nl" \
  --skip-seed-blogs
```

### Example 3: Overwrite Existing Site (DANGEROUS)
```bash
php artisan site:generate \
  --niche="Home trainers" \
  --domain="hometrainergids.nl" \
  --force
```

**Output:**
```
WARNING: Existing content detected!
┌────────────────┬──────────┐
│ Type           │ Status   │
├────────────────┼──────────┤
│ Settings       │ EXISTS   │
│ Content Blocks │ EXISTS   │
│ Team Members   │ EXISTS   │
└────────────────┴──────────┘

DANGER: You are about to OVERWRITE existing content!
This will affect Settings and Content Blocks.

Are you ABSOLUTELY SURE you want to continue? (yes/no) [no]:
> yes

[Continues with generation...]
```

---

## Post-Generation Steps

### 1. Configure .env
```env
BOL_SITE_ID=your_site_id_here
OPENAI_API_KEY=your_key_here
```

### 2. Enable Cron Jobs (Forge/Server)
```
* * * * * cd /path-to-site && php artisan schedule:run >> /dev/null 2>&1
```

### 3. Generate Full Blog Content (Optional)
```bash
# Seed posts are created as drafts
# Generate full content:
php artisan app:generate-blog
```

### 4. Generate Product Blogs (Optional)
```bash
# Generate product-focused blog:
php artisan app:generate-product-blog
```

---

## Troubleshooting

### OpenAI API Errors
- Circuit breaker prevents cascade failures
- Automatic retry with exponential backoff (5 attempts)
- Falls back to default content if all attempts fail
- Check logs: `storage/logs/laravel.log`

### Content Quality Issues
- Re-run specific parts:
  ```bash
  # Regenerate content blocks only
  php artisan site:generate --skip-team --skip-favicon --force
  ```

### Favicon Not Generated
- Requires write permissions on `/public/`
- Falls back gracefully if generation fails
- Manually upload favicon to `/public/favicon.svg` if needed

### Information Pages Not Generated
- Requires OpenAI API access
- Falls back gracefully if generation fails
- Manually run: `php artisan generate:all-information-pages --niche="your niche"`

---

## Files Involved

### Services
- `app/Services/SiteContentGeneratorService.php` - OpenAI content generation
- `app/Services/SiteGeneratorService.php` - Orchestration & DB operations

### Commands
- `app/Console/Commands/Site/GenerateSite.php` - Main command
- `app/Console/Commands/Site/GenerateFavicon.php` - Favicon generation
- `app/Console/Commands/GenerateAllInformationPages.php` - Info pages (standalone)

### Database Tables Used
- `settings` - Site configuration
- `content_blocks` - Page content
- `team_members` - Team profiles (via team:generate)
- `blog_variations` - Blog variations (backwards compat)
- `blog_templates` - 60 blog templates
- `product_blog_templates` - 20 product blog templates
- `information_pages` - Information articles
- `blog_posts` - Seed blog ideas

---

## Testing

### Test on Fresh Database (Recommended)
```bash
# 1. Backup current database
php artisan db:backup

# 2. Fresh migration
php artisan migrate:fresh

# 3. Test generation
php artisan site:generate \
  --niche="Test niche" \
  --domain="test.nl"

# 4. Verify results
php artisan tinker
>>> Setting::count()
>>> ContentBlock::count()
>>> TeamMember::count()
>>> BlogTemplate::count()
>>> InformationPage::count()

# 5. Restore database
php artisan db:restore
```

### Test Safety Checks
```bash
# Should fail (no --force)
php artisan site:generate

# Should require confirmation
php artisan site:generate --force
```

---

## Future Improvements

- [ ] Add `--preview` flag to show what would be generated without saving
- [ ] Support for custom content block templates
- [ ] Multi-language support (currently NL only)
- [ ] Export/import site configurations for reuse
- [ ] Analytics tracking setup automation

---

## Support

For issues or questions:
1. Check logs: `storage/logs/laravel.log`
2. Run with verbose: `php artisan site:generate -vvv`
3. Test individual services in tinker
4. Review this documentation

---

**Built with the same architecture as the team generation system - proven, reliable, and backwards compatible with 30+ existing sites.**
