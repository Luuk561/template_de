# Multi-Site Deployment Guide

## 🚨 Probleem met je oude script

Je oude script stopt zonder foutmelding omdat:

1. **Geen timeout protection** - Als composer/artisan vastloopt, wacht script oneindig
2. **Memory issues** - `COMPOSER_MEMORY_LIMIT=-1` kan server overbelasten bij concurrent sites
3. **Geen error logging** - Je ziet niet WAAR en WAAROM het stopt
4. **Geen recovery** - Bij crash moet je helemaal opnieuw beginnen
5. **Silent failures** - `|| true` maskeert echte problemen

## ✅ Nieuw Robuust Script

### Belangrijkste verbeteringen:

**1. Timeout Protection**
```bash
# Elke operatie heeft max tijd (voorkomt infinite hangs)
run_with_timeout 300 "composer install"  # Max 5 minuten
run_with_timeout 120 "php artisan migrate"  # Max 2 minuten
```

**2. Uitgebreide Logging**
```bash
LOG_FILE="/home/forge/deployment-logs/deploy-20250104-143022.log"
# Alles wordt gelogd met timestamps + kleuren
```

**3. Progress Tracking**
```bash
[3/27] Processing: beste-hometrainer.nl
✅ beste-hometrainer.nl deployed successfully in 47s

[4/27] Processing: beste-massagegun.nl
❌ Failed to deploy: beste-massagegun.nl
# Script gaat DOOR naar volgende site (oude script stopte hier)
```

**4. Health Checks**
```bash
# Test of site online blijft na deploy
🏥 Health check...
✅ Health check passed (200)
```

**5. Summary Report**
```bash
📊 DEPLOYMENT SUMMARY
Total sites: 27
✅ Successful: 25
❌ Failed: 2
Duration: 23m 15s
Average: 51s per site

Failed sites:
  - beste-massagegun.nl
  - kruimeldieftest.nl

Check log file: /home/forge/deployment-logs/deploy-20250104-143022.log
```

## 🚀 Gebruik

### Upload naar server

```bash
# Op je lokale machine
scp deploy-multi-site.sh forge@airfryermetdubbelelade.nl:/home/forge/

# SSH naar server
ssh forge@airfryermetdubbelelade.nl

# Maak executable
chmod +x /home/forge/deploy-multi-site.sh
```

### Basis deployment (alle sites)

```bash
cd /home/forge
./deploy-multi-site.sh
```

### Dry-run (test zonder te deployen)

```bash
./deploy-multi-site.sh main --dry-run
```

### Specifieke branch deployen

```bash
./deploy-multi-site.sh develop
```

## 📊 Logs Bekijken

### Laatste deployment

```bash
tail -f /home/forge/deployment-logs/deploy-*.log | tail -1
```

### Alle deployments

```bash
ls -lah /home/forge/deployment-logs/
```

### Zoek naar failures

```bash
grep "❌" /home/forge/deployment-logs/deploy-*.log
```

### Zoek naar specifieke site

```bash
grep "beste-massagegun.nl" /home/forge/deployment-logs/deploy-*.log
```

## 🔧 Troubleshooting

### Script stopt nog steeds zonder error

**Check timeout settings:**
```bash
# In script aanpassen (regel 18):
TIMEOUT_SECONDS=600  # Verhoog naar 10 minuten als sites traag zijn
```

### Composer memory errors

**Verhoog PHP memory limit:**
```bash
# Op server
sudo nano /etc/php/8.3/cli/php.ini

# Zoek: memory_limit
# Verander naar: memory_limit = 2G

# Restart
sudo service php8.3-fpm restart
```

### Git pull failures

**Check SSH keys per site:**
```bash
cd /home/forge/beste-massagegun.nl
git pull origin main  # Test handmatig
```

**Fix stale locks:**
```bash
cd /home/forge/beste-massagegun.nl
rm -f .git/index.lock
```

### Health check failures

**Check nginx configuratie:**
```bash
sudo nginx -t
sudo service nginx status
```

**Check PHP-FPM:**
```bash
sudo service php8.3-fpm status
```

## 🎯 Best Practices

### 1. Test eerst met dry-run

```bash
./deploy-multi-site.sh main --dry-run
# Check of alle directories bestaan
# Check of git repos toegankelijk zijn
```

### 2. Deploy tijdens rustige uren

```bash
# Beste tijden (minste traffic):
# - 02:00-05:00 (nacht)
# - 14:00-16:00 (middag)

# Vermijd:
# - 09:00-12:00 (ochtend - piek)
# - 19:00-22:00 (avond - piek)
```

### 3. Monitor tijdens deployment

```bash
# Terminal 1: Run deployment
./deploy-multi-site.sh

# Terminal 2: Monitor logs
tail -f /home/forge/deployment-logs/deploy-*.log

# Terminal 3: Monitor server resources
htop
# Of:
watch -n 1 'free -h && df -h'
```

### 4. Backup voor grote updates

```bash
# Voor major Laravel upgrades of database migraties
for site in /home/forge/*.nl; do
  cd "$site"
  mysqldump -u forge -p$(cat .env | grep DB_PASSWORD | cut -d= -f2) \
    $(cat .env | grep DB_DATABASE | cut -d= -f2) \
    > backup-$(date +%Y%m%d).sql
done
```

## 🚨 Emergency Rollback

### Als deployment faalt voor veel sites:

**1. Stop het script (Ctrl+C)**

**2. Check welke sites gefaald zijn:**
```bash
grep "Failed to deploy" /home/forge/deployment-logs/deploy-*.log
```

**3. Rollback per site handmatig:**
```bash
cd /home/forge/FAILED_SITE
git log --oneline -5  # Zie laatste commits
git reset --hard HEAD~1  # Ga 1 commit terug
composer install --no-dev
php artisan migrate:rollback  # Alleen als nodig
php artisan config:cache
sudo service php8.3-fpm reload
```

## 🔄 Automatische Deployments (Optioneel)

### Via cron (dagelijkse deploy op 03:00)

```bash
crontab -e

# Voeg toe:
0 3 * * * /home/forge/deploy-multi-site.sh main >> /home/forge/deployment-logs/cron.log 2>&1
```

### Via GitHub Actions (bij push naar main)

Maak `.github/workflows/deploy.yml`:

```yaml
name: Deploy All Sites

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to Forge
        uses: appleboy/ssh-action@master
        with:
          host: airfryermetdubbelelade.nl
          username: forge
          key: ${{ secrets.FORGE_SSH_KEY }}
          script: |
            /home/forge/deploy-multi-site.sh main
```

## 📈 Performance Tips

### Parallel deployments (gevorderd)

Voor snellere deployments kun je sites parallel deployen:

```bash
# Deploy 3 sites tegelijk (pas op voor server load!)
./deploy-multi-site.sh main --parallel=3
```

**Let op:** Dit vereist aanpassingen in het script + meer server resources.

### Skip unchanged sites

Voeg check toe voor git changes:

```bash
# In deploy_site() functie, voor git pull:
local current_commit=$(git rev-parse HEAD)
git fetch origin $BRANCH
local remote_commit=$(git rev-parse origin/$BRANCH)

if [ "$current_commit" = "$remote_commit" ]; then
  log_info "No changes, skipping..."
  return 0
fi
```

## 🎉 Success Criteria

Je deployment is succesvol als:

✅ Summary toont 27/27 successful
✅ Alle health checks passed (200/302)
✅ PHP-FPM reloaded zonder errors
✅ Alle sites zijn bereikbaar na deployment
✅ Log file bevat geen ❌ errors

---

## 📞 Support

Bij problemen:
1. Check deployment log: `/home/forge/deployment-logs/deploy-*.log`
2. Test failed site handmatig: `cd /home/forge/SITE && git pull && composer install`
3. Check server resources: `htop` of `free -h`
4. Check PHP-FPM errors: `sudo tail -f /var/log/php8.3-fpm.log`

**Emergency contact:** Je oude script werkt nog, gebruik die als backup!
