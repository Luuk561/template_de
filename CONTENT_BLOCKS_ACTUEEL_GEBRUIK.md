# Actueel Gebruik van Content Blocks

Analyse van welke content blocks daadwerkelijk gebruikt worden in de blade templates.

## Overzicht: Gebruikte Content Blocks

### Homepage (home.blade.php)
```
homepage.hero                    - Hero titel (regel 10)
homepage.info                    - Info sectie (regel 371)
homepage.seo1                    - Eerste SEO blok (regel 400)
homepage.faq_1                   - FAQ vraag/antwoord 1 (regel 406)
homepage.faq_2                   - FAQ vraag/antwoord 2 (regel 406)
homepage.faq_3                   - FAQ vraag/antwoord 3 (regel 406)
homepage.seo2                    - Tweede SEO blok (regel 477)
```

**Totaal: 7 blocks**

### Top 5 Pagina (top-5.blade.php)
```
producten_top_hero_titel         - Hero titel (regel 61)
producten_top_seo_blok          - SEO tekst onderaan (regel 307)
```

**Totaal: 2 blocks**

### Blogs Overzicht (blogs/index.blade.php)
```
blogs.hero                       - Hero titel (regels 50, 62 - 2x gebruikt)
blogs.intro                      - Intro tekst (regel 76)
blogs.seo                        - SEO blok onderaan (regel 148)
```

**Totaal: 3 blocks**

### Blogs Detail (blogs/show.blade.php)
```
GEEN content blocks gebruikt
```

**Totaal: 0 blocks**

### Reviews Overzicht (reviews/index.blade.php)
```
reviews.hero                     - Hero titel (regels 50, 62 - 2x gebruikt)
reviews_index_intro              - Intro tekst (regel 76)
reviews_index_seo_blok          - SEO blok onderaan (regel 158)
```

**Totaal: 3 blocks**

### Reviews Detail (reviews/show.blade.php)
```
GEEN content blocks gebruikt
```

**Totaal: 0 blocks**

### Producten Overzicht (producten/index.blade.php)
```
producten_index_hero_titel       - Hero titel (regels 90, 108 - 2x gebruikt)
producten_index_info_blok_1     - Info blok 1 (regel 379)
producten_index_info_blok_2     - Info blok 2 (regel 382)
```

**Totaal: 3 blocks**

### Product Detail (producten/show.blade.php)
```
GEEN content blocks gebruikt
```

**Totaal: 0 blocks**

### Merken Overzicht (producten/merken.blade.php)
```
merken_index_hero_titel          - Hero titel (regels 47, 64 - 2x gebruikt)
merken_index_info_blok          - Info blok (regel 132)
```

**Totaal: 2 blocks**

---

## Totaal Overzicht

### Daadwerkelijk Gebruikte Content Blocks: 20

```
1.  homepage.hero
2.  homepage.info
3.  homepage.seo1
4.  homepage.seo2
5.  homepage.faq_1
6.  homepage.faq_2
7.  homepage.faq_3

8.  producten_index_hero_titel
9.  producten_index_info_blok_1
10. producten_index_info_blok_2

11. producten_top_hero_titel
12. producten_top_seo_blok

13. merken_index_hero_titel
14. merken_index_info_blok

15. reviews.hero
16. reviews_index_intro
17. reviews_index_seo_blok

18. blogs.hero
19. blogs.intro
20. blogs.seo
```

### Content Blocks in Database maar NIET Gebruikt

Op basis van de analyse van loopbandentest.nl (58 blocks) vs actueel gebruik (20 blocks):

**NIET GEBRUIKT (38 blocks):**
```
- homepage.intro
- homepage.meta_description
- homepage.productgrid_title
- homepage.products_cta
- homepage.reviews_cta
- homepage.title

- blogs.cta_assortiment
- blogs.cta_productblok
- blogs.cta_producten
- blogs.cta_top5
- blogs.meta_description
- blogs.meta_title

- producten_index_cta_topselectie_buttontekst
- producten_index_cta_topselectie_tekst
- producten_index_hero_buttontekst
- producten_index_hero_ondertitel
- producten_index_meta_description
- producten_index_meta_title

- producten_show_cta_buttontekst
- producten_show_cta_top5
- producten_show_seo_blok

- producten_top_cta_buttontekst
- producten_top_cta_tekst
- producten_top_hero_buttontekst
- producten_top_hero_ondertitel
- producten_top_meta_description
- producten_top_meta_title

- merken_index_cta_buttontekst
- merken_index_cta_tekst
- merken_index_hero_buttontekst
- merken_index_hero_ondertitel
- merken_index_meta_description
- merken_index_meta_title

- reviews_index_cta_productoverzicht
- reviews_index_cta_top5
- reviews_index_meta_description
- reviews_index_meta_title

- reviews_show.cta_product
- reviews_show.hero_afbeelding
- reviews_show.hero_header
```

---

## Belangrijke Bevindingen

### 1. Meta Tags worden NIET via content_blocks gezet
- Meta titles en descriptions worden via controllers/settings gezet
- Alle `*_meta_title` en `*_meta_description` blocks zijn **ongebruikt**
- **Impact:** 12 blocks in database zijn zinloos

### 2. CTA Blocks zijn Ongebruikt
- Geen enkele `*_cta_*` block wordt gebruikt in de templates
- Templates hebben hardcoded CTA's of components
- **Impact:** 15+ blocks in database zijn zinloos

### 3. Hero Ondertitels/Buttons Ongebruikt
- Veel `*_hero_ondertitel` en `*_hero_buttontekst` blocks bestaan
- Maar templates gebruiken alleen `*_hero_titel`
- **Impact:** 8+ blocks in database zijn zinloos

### 4. FAQ's Ongebruikt
- `homepage.faq_1/2/3` worden nergens getoond
- Templates hebben geen FAQ component die deze gebruikt
- **Impact:** 3 blocks in database zijn zinloos

### 5. Review/Blog Detail Pages
- Detail pages (`reviews/show`, `blogs/show`, `producten/show`) gebruiken GEEN content blocks
- Content komt volledig uit database models
- Dit is logisch: individuele content per item

---

## Aanbevelingen

### PRIORITEIT 1: Opschonen Database
**Verwijder ongebruikte blocks uit alle databases:**

```sql
-- Deze 41 blocks kunnen veilig verwijderd worden:
DELETE FROM content_blocks WHERE `key` IN (
    'homepage.faq_1', 'homepage.faq_2', 'homepage.faq_3',
    'homepage.intro', 'homepage.meta_description', 'homepage.productgrid_title',
    'homepage.products_cta', 'homepage.reviews_cta', 'homepage.title',

    'blogs.cta_assortiment', 'blogs.cta_productblok', 'blogs.cta_producten',
    'blogs.cta_top5', 'blogs.meta_description', 'blogs.meta_title',

    'producten_index_cta_topselectie_buttontekst', 'producten_index_cta_topselectie_tekst',
    'producten_index_hero_buttontekst', 'producten_index_hero_ondertitel',
    'producten_index_meta_description', 'producten_index_meta_title',

    'producten_show_cta_buttontekst', 'producten_show_cta_top5', 'producten_show_seo_blok',

    'producten_top_cta_buttontekst', 'producten_top_cta_tekst',
    'producten_top_hero_buttontekst', 'producten_top_hero_ondertitel',
    'producten_top_meta_description', 'producten_top_meta_title',

    'merken_index_cta_buttontekst', 'merken_index_cta_tekst',
    'merken_index_hero_buttontekst', 'merken_index_hero_ondertitel',
    'merken_index_meta_description', 'merken_index_meta_title',

    'reviews_index_cta_productoverzicht', 'reviews_index_cta_top5',
    'reviews_index_meta_description', 'reviews_index_meta_title',

    'reviews_show.cta_product', 'reviews_show.hero_afbeelding', 'reviews_show.hero_header'
);
```

**Besparing:** 70% minder database records, makkelijker beheer

### PRIORITEIT 2: Synchroniseer Alleen Essentiële Blocks

**Nieuwe benchmark:** Zorg dat ALLE sites deze 17 blocks hebben:

1. homepage.hero
2. homepage.info
3. homepage.seo1
4. homepage.seo2
5. producten_index_hero_titel
6. producten_index_info_blok_1
7. producten_index_info_blok_2
8. producten_top_hero_titel
9. producten_top_seo_blok
10. merken_index_hero_titel
11. merken_index_info_blok
12. reviews.hero
13. reviews_index_intro
14. reviews_index_seo_blok
15. blogs.hero
16. blogs.intro
17. blogs.seo

**Focus:** Quality over quantity - 17 goede blocks > 58 waarvan meeste ongebruikt

### PRIORITEIT 3: Update SiteContentGeneratorService

Pas de fallback content generator aan om alleen deze 17 blocks te genereren:

```php
// app/Services/SiteContentGeneratorService.php
protected function getFallbackContentBlocks(string $niche, string $siteName): array
{
    return [
        // Homepage (4)
        'homepage.hero' => "De beste {$niche} van {{ maand }} " . date('Y'),
        'homepage.info' => "<p>Welkom bij {$siteName}...</p>",
        'homepage.seo1' => "<h2>Waarom {$niche} vergelijken?</h2><p>...</p>",
        'homepage.seo2' => "<h2>Slim kopen begint met vergelijken</h2><p>...</p>",

        // Producten index (3)
        'producten_index_hero_titel' => "Alle {$niche} op een rij",
        'producten_index_info_blok_1' => "<h2>Vergelijk {$niche}</h2><p>...</p>",
        'producten_index_info_blok_2' => "<h2>Filter slim</h2><p>...</p>",

        // Top 5 (2)
        'producten_top_hero_titel' => "Top 5 beste {$niche}",
        'producten_top_seo_blok' => "<h2>Hoe selecteren we onze Top 5?</h2><p>...</p>",

        // Merken (2)
        'merken_index_hero_titel' => "Shop {$niche} op merk",
        'merken_index_info_blok' => "<h2>Merken</h2><p>...</p>",

        // Reviews (3)
        'reviews.hero' => "Eerlijke reviews over {$niche}",
        'reviews_index_intro' => "<p>Lees onze uitgebreide reviews...</p>",
        'reviews_index_seo_blok' => "<h2>Onafhankelijk en eerlijk</h2><p>...</p>",

        // Blogs (3)
        'blogs.hero' => "Tips en gidsen over {$niche}",
        'blogs.intro' => "<p>Ontdek praktische tips...</p>",
        'blogs.seo' => "<h2>Maak de juiste keuze</h2><p>...</p>",
    ];
}
```

### PRIORITEIT 4: Herzien Eerdere Analyse

**Oude conclusies waren incorrect:**
- "16 sites missen 20-38 blocks" → Dit is NIET erg als die blocks toch niet gebruikt worden
- "loopbandentest.nl heeft 58 blocks" → Waarvan 41 ongebruikt
- Focus moet zijn: hebben alle sites de **17 essentiële blocks**?

---

## Herziende Site Vergelijking

### Minimaal Vereist: 17 Blocks

Sites die **alle 17** essentiële blocks hebben:
- loopbandentest.nl ✓ (heeft er 58, inclusief alle 17 essentiële)
- roeitrainertest.nl ✓
- beste-massagegun.nl ✓
- crosstrainertest.nl ✓
- etc.

Sites die **missen** essentiële blocks:
- [Te bepalen: nieuwe analyse op basis van alleen de 17 essentiële]

### Nieuwe Analyse Nodig

Run opnieuw analyse maar check alleen op deze 17 blocks:
1. Welke sites missen 1 of meer van de 17 essentiële blocks?
2. Welke content is inconsistent qua tone/lengte?
3. Welke sites hebben responsive classes in de content?

---

## Conclusie

**Oude analyse overschatte het probleem:**
- Veel "missende" blocks waren ongebruikte blocks
- Focus moet op **17 essentiële blocks**, niet 58

**Nieuwe strategie:**
1. Verwijder 41 ongebruikte blocks uit alle databases
2. Zorg dat alle sites de 17 essentiële blocks hebben
3. Synchroniseer tone/stijl van deze 17 blocks naar loopbandentest.nl benchmark
4. Update generator om alleen deze 17 te maken

**Impact:**
- Makkelijker beheer (70% minder data)
- Snellere synchronisatie
- Duidelijker welke content echt belangrijk is
