# Team System - E-E-A-T Enhancement

Een volledig geautomatiseerd systeem om fictieve teamleden te genereren voor affiliate sites ter verbetering van E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness).

## 🎯 Wat Doet Dit Systeem?

Het team systeem genereert automatisch **3 fictieve redactieleden** per site met:
- AI-gegenereerde profielfoto's (DALL-E 3)
- Unieke persoonlijkheden en expertises
- Volledige profielpagina's (900-1200 woorden)
- Automatische koppeling aan blogs en reviews

## 📋 Juridische Compliance

Het systeem is ontworpen om **volledig juridisch waterdicht** te zijn:

### ✅ Wat WEL Mag
- Fictieve persona's als redactionele karakters
- Redactionele expertise tonen ("Volgens Lisa van het team...")
- Teamleden als testers/redacteuren presenteren

### ❌ Wat NIET Mag
- Persoonlijke ervaringen claimen als consument ("Ik gebruik dit al 3 maanden...")
- Nep-reviews of testimonials
- Suggereren dat het echte consumenten zijn

### 🛡️ Juridische Safeguards
- Duidelijke disclaimer op `/team` pagina
- Redactionele taal in alle prompts
- Geen persoonlijke koopdecisies als consumer
- AI-gegenereerde foto's (geen echte mensen)

## 🚀 Quick Start

### 1. Migraties Draaien
```bash
php artisan migrate
```

### 2. Team Genereren
```bash
php artisan team:generate
```

Dit command:
- Genereert 3 unieke teamleden
- Maakt AI-profielfoto's
- Schrijft volledige bio's (900-1200 woorden)
- Slaat alles op in database

### 3. Team Bekijken
```
/team              → Overzichtspagina
/team/{slug}       → Individueel profiel
```

## 📊 Database Structuur

### Team Members Tabel
```php
- id
- name                 // Voornaam (bijv. "Lisa")
- slug                 // URL-slug (bijv. "lisa")
- role                 // Rol (bijv. "Productliefhebber")
- bio                  // Volledige profieltekst (HTML)
- quote                // Korte motiverende quote (1 zin)
- focus                // Expertise (gebruiksgemak|techniek|duurzaamheid)
- tone                 // Schrijfstijl (bijv. "vriendelijk en praktisch")
- photo_url            // URL naar profielfoto
```

### Relaties
```php
TeamMember hasMany BlogPost
TeamMember hasMany Review
BlogPost belongsTo TeamMember
Review belongsTo TeamMember
```

## 🎨 Frontend Integratie

### Author Byline Component
Er is een herbruikbaar component beschikbaar:

```blade
{{-- Compact versie (bovenaan artikel) --}}
<x-author-byline
    :teamMember="$post->teamMember"
    :date="$post->created_at"
    :compact="true"
/>

{{-- Volledige versie (onderaan artikel) --}}
<x-author-byline
    :teamMember="$post->teamMember"
/>
```

### In Blog/Review Templates
De component is al geïntegreerd in:
- `resources/views/blogs/show.blade.php`
- `resources/views/reviews/show.blade.php`

## 🤖 Automatische Toewijzing

Nieuwe blogs en reviews krijgen **automatisch** een random team member toegewezen:

- `GenerateBlogPost` command: regel 180
- `GenerateReview` command: regel 96

### Logica
```php
private function getRandomTeamMemberId(): ?int
{
    $teamMember = TeamMember::inRandomOrder()->first();
    return $teamMember?->id;
}
```

## 🔄 Workflow

### Voor Eerste Gebruik
```bash
# 1. Migreer database
php artisan migrate

# 2. Genereer team (eenmalig per site)
php artisan team:generate

# 3. Genereer content (teamleden worden automatisch toegewezen)
php artisan app:generate-blog
php artisan generate:review {product_id}
```

### Team Regenereren
```bash
# Forceer nieuwe teamleden (verwijdert oude)
php artisan team:generate --force
```

## 📄 Team Pagina's

### Team Overzicht (`/team`)
- Grid met alle 3 teamleden
- Foto, naam, rol, quote
- Focus tags (gebruiksgemak/techniek/duurzaamheid)
- Juridische disclaimer
- Link naar individuele profielen

### Team Profiel (`/team/{slug}`)
- Grote profielfoto + info card
- Volledige bio (900-1200 woorden)
- Stats (aantal reviews/blogs)
- Laatste 10 reviews
- Laatste 10 blogs

## 🎯 E-E-A-T Strategie

### Diversiteit
Elk teamlid heeft unieke focus:
- **Teamlid 1**: Gebruiksgemak & praktische toepassingen
- **Teamlid 2**: Technische specificaties & prestaties
- **Teamlid 3**: Duurzaamheid & design

### SEO Voordelen
- Multiple author signals voor Google
- Autoriteitspagina's per teamlid
- Interne link structure
- Author schema markup mogelijk

### Content Variatie
- Verschillende schrijfstijlen per teamlid
- Diverse perspectieven
- Natuurlijker ogende content mix

## 🛠️ Aanpassingen

### Team Size Wijzigen
Het systeem genereert altijd **exact 3 teamleden**. Om dit aan te passen:

```php
// In GenerateTeam command, regel 54
$teamProfiles = $this->generateTeamProfiles($niche, $siteName);

// Update de prompt om meer/minder teamleden te genereren
```

### Profielpagina Lengte
Standaard: **900-1200 woorden**

Aanpassen in `GenerateTeam.php`, regel 291:
```php
SEO DOELEN:
- Lengte: 900-1200 woorden (voor autoriteit)
```

### Focus Areas
Standaard focuses:
- `gebruiksgemak`
- `techniek`
- `duurzaamheid`

Aanpassen in prompt regel 126-129.

## 📊 Routes

Routes zijn automatisch toegevoegd aan:
- `routes/web.php` (regel 42-43)
- `sitemap.xml` (regel 96, 117-120)

## 🔍 SEO Optimalisatie

### Profielpagina's
- **900-1200 woorden** voor autoriteit
- Longtail keywords: "expert {niche}", "specialist {niche}"
- 3-4 interne links per pagina
- H2 headings voor structuur
- Natuurlijke keyword integratie

### Schema Markup
Author schema markup kan toegevoegd worden aan blog/review pagina's:

```php
Schema::article()
    ->author(
        Schema::person()
            ->name($teamMember->name)
            ->url(route('team.show', $teamMember->slug))
    )
```

## 🧪 Testing

### Lokaal Testen
```bash
# Genereer test team
php artisan team:generate

# Check database
php artisan tinker
>>> App\Models\TeamMember::count()
>>> App\Models\TeamMember::with('blogPosts', 'reviews')->get()

# Test pagina's
# Bezoek /team
# Bezoek /team/{slug}
```

### Validatie Checklist
- [ ] 3 teamleden aangemaakt
- [ ] Profielfoto's gegenereerd
- [ ] Bio's bevatten 900+ woorden
- [ ] Juridische disclaimer zichtbaar op `/team`
- [ ] Author byline zichtbaar op blogs/reviews
- [ ] Nieuwe content krijgt automatisch teamlid

## ⚠️ Troubleshooting

### "Team already exists"
```bash
php artisan team:generate --force
```

### Geen profielfoto's
Check OpenAI API key en DALL-E 3 access:
```bash
php artisan test:openai
```

### Teamleden niet zichtbaar op content
Check of `team_member_id` is ingevuld:
```bash
php artisan tinker
>>> App\Models\BlogPost::whereNull('team_member_id')->count()
```

## 📝 Content Blocks

Optionele content blocks voor customization:

```php
// Team hero
{!! getContent('team.hero', [
    'siteName' => $siteName,
    'niche' => $niche
]) !!}

// Team intro
{!! getContent('team.intro', [
    'siteName' => $siteName,
    'niche' => $niche
]) !!}

// Why us section
{!! getContent('team.why_us', [
    'siteName' => $siteName,
    'niche' => $niche
]) !!}
```

## 🎓 Best Practices

### DO's ✅
- Genereer team vóór content generatie
- Gebruik consistent redactionele taal
- Test responsive design (mobile/desktop)
- Check juridische disclaimer
- Varieer toewijzing van teamleden

### DON'Ts ❌
- Geen persoonlijke ervaringen als consumer
- Geen echte foto's gebruiken
- Geen specifieke privégegevens (leeftijd/woonplaats)
- Geen certificaten/opleidingen claimen
- Geen "verified purchase" suggesties

## 🔮 Toekomstige Uitbreidingen

Mogelijke verbeteringen:
- [ ] Author schema markup toevoegen
- [ ] Team statistics dashboard
- [ ] Individuele teamlid statistieken
- [ ] Content performance per teamlid
- [ ] A/B testing verschillende persona's
- [ ] Multiple photo variations per teamlid

## 📞 Support

Voor vragen of problemen:
1. Check deze README eerst
2. Bekijk prompt engineering in `GenerateTeam.php`
3. Test lokaal met `--force` flag
4. Valideer juridische compliance

---

**Versie**: 1.0
**Laatst Bijgewerkt**: Oktober 2025
**Compatibel met**: Laravel 10+, PHP 8.1+
