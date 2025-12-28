# Fathom Affiliate Click Tracking

## 📊 Wat is geïmplementeerd

Automatische tracking van alle affiliate clicks naar Bol.com (en andere partners) via Fathom Analytics.

## ✅ Setup

### 1. Fathom Site ID configureren

Voeg toe aan je `.env`:

```env
FATHOM_SITE_ID=JOUWSITEID
```

Of sla het op in de database via Settings:
- Key: `fathom_site_id`
- Value: Je Fathom site ID (bijv. `ABCDEFGH`)

### 2. Hoe het werkt

Het systeem tracked automatisch **alle links met `rel="nofollow sponsored"`**.

**Tracking script**: [resources/views/layouts/app.blade.php:764-802](resources/views/layouts/app.blade.php#L764-L802)

**Wat wordt getracked:**
- Alle affiliate links (automatisch herkend via `rel="nofollow sponsored"`)
- Product naam (via `data-product` attribuut of link text)
- Partner domein (bijv. "bol", "coolblue", "amazon")

## 📈 Fathom Dashboard Events

Je ziet in je Fathom dashboard events als:

```
Affiliate click: bol - Philips Airfryer XXL HD9270/96
Affiliate click: bol - Tefal Easy Fry Dual AF268870
Affiliate click: coolblue - Samsung Galaxy S24
```

**Event naam formaat:**
```
Affiliate click: {partner} - {product naam}
```

**Partner namen:**
- `bol` voor partner.bol.com
- `coolblue` voor coolblue.nl
- `amazon` voor amazon.nl
- etc.

## 🔧 Components met tracking

De volgende Blade components hebben automatisch `data-product` tracking:

✅ [product-primary.blade.php](resources/views/components/cta/product-primary.blade.php#L22) - Primaire CTA
✅ [product-inline.blade.php](resources/views/components/cta/product-inline.blade.php#L13) - Inline CTA

### Andere affiliate links toevoegen

Voor custom affiliate links, voeg `data-product` toe:

```blade
<a href="{{ $affiliateUrl }}"
   rel="nofollow sponsored"
   data-product="{{ $product->title }}">
   Bekijk product
</a>
```

Het tracking script pikt dit automatisch op!

## 🎯 Filtering & Rapportage

### In Fathom Dashboard

**Alle affiliate clicks zien:**
1. Ga naar je site in Fathom
2. Klik op "Events" tab
3. Zoek naar "Affiliate click"

**Specifieke partner filteren:**
- Filter op event naam: `Affiliate click: bol`
- Of zoek op productnaam: `Affiliate click: bol - Philips`

**Per pagina bekijken:**
- Klik op een event
- Zie welke pagina's de meeste clicks genereren
- Zie conversie per apparaat (mobile/desktop)

## 🧪 Testen

**Lokaal testen werkt NIET** - Fathom events vereisen https/http (geen localhost).

**Test in productie:**
1. Deploy naar je live site
2. Open DevTools Console (F12)
3. Klik op een affiliate link
4. Check console voor: `Fathom tracking error` (er zou GEEN error moeten zijn)
5. Check Fathom dashboard na 1-2 minuten

**Debug mode:**
Als je wilt zien of tracking werkt, voeg console.log toe in app.blade.php:

```javascript
// In het tracking script (regel 789)
if (typeof fathom !== 'undefined' && fathom.trackEvent) {
    console.log('Tracking:', `Affiliate click: ${domainName} - ${shortProductName}`);
    fathom.trackEvent(`Affiliate click: ${domainName} - ${shortProductName}`);
}
```

## ⚙️ Technische Details

**Hoe detectie werkt:**
1. Script zoekt alle links met `rel="nofollow sponsored"`
2. Bij click: extract product naam van `data-product` attribuut of link text
3. Extract partner domein van URL (bijv. `partner.bol.com` → `bol`)
4. Fire Fathom event met format: `Affiliate click: {partner} - {product}`

**Product naam truncation:**
- Max 50 tekens om dashboard overzichtelijk te houden
- Langere namen krijgen `...` suffix

**Error handling:**
- Try-catch blok voorkomt dat tracking errors de site breken
- Errors worden alleen in console gelogd (debug mode)

## 📊 Data Exporteren

**CSV export in Fathom:**
1. Dashboard → Je site
2. Click op "Export" knop
3. Selecteer "Events" in export opties
4. Download CSV met alle event data

**Data bevat:**
- Event naam
- Aantal clicks
- Datum/tijd
- Pagina waar geklikt werd
- Apparaat type (mobile/desktop)
- Browser
- Land

## 🚨 Troubleshooting

**Events verschijnen niet in Fathom:**
- ✅ Check of `fathom_site_id` is ingesteld
- ✅ Check of je op HTTPS/HTTP test (niet localhost)
- ✅ Wacht 1-2 minuten (Fathom is niet realtime)
- ✅ Check browser console voor errors
- ✅ Controleer of Fathom script geladen is (check Network tab)

**Verkeerde productnaam:**
- ✅ Check of `data-product="{{ $product->title }}"` attribute aanwezig is
- ✅ Als attribute ontbreekt, wordt link text gebruikt (minder nauwkeurig)

**Dubbele tracking:**
- Normale affiliate link tracking telt 1x per click
- Als je zowel `onclick` als dit script gebruikt, kan dubbel tellen optreden
- Verwijder `onclick="fathom.trackEvent(...)"` uit je templates

## 🎉 Multi-Site Network

Dit systeem werkt perfect voor je 20+ affiliate sites:

✅ **Automatisch**: Geen handmatige event setup per link
✅ **Consistent**: Zelfde event format voor alle sites
✅ **Schaalbaat**: Nieuwe partners worden automatisch herkend
✅ **Privacy-friendly**: Fathom is cookieless en GDPR-compliant

**Per site krijg je inzicht in:**
- Welke producten meest geklikt worden
- Welke pagina's best converteren naar clicks
- Mobile vs desktop conversie
- Geographic data (welke landen klikken meest)

Vergelijk data tussen sites om te zien welke niches best presteren! 🚀
