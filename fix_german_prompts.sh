#!/bin/bash

# Fix SiteContentGeneratorService.php
sed -i '' 's|format = '\''html'\''|format = '\''hybrid'\''|g' app/Services/SiteContentGeneratorService.php
sed -i '' 's|Je bent conversion-strategist voor Nederlandse affiliate websites|Sie sind Conversion-Stratege für deutsche Affiliate-Websites|g' app/Services/SiteContentGeneratorService.php

# Fix ContentBlocksGeneratorService.php
sed -i '' "s|locale('nl')|locale('de')|g" app/Services/ContentBlocksGeneratorService.php
sed -i '' "s|Europe/Amsterdam|Europe/Berlin|g" app/Services/ContentBlocksGeneratorService.php

# Fix SiteGeneratorService.php information pages
sed -i '' 's|Je bent een SEO-expert voor Nederlandse affiliate websites|Sie sind ein SEO-Experte für deutsche Affiliate-Websites|g' app/Services/SiteGeneratorService.php
sed -i '' 's|Je bent een expert content strategist voor Nederlandse affiliate websites|Sie sind ein Experte Content-Stratege für deutsche Affiliate-Websites|g' app/Services/SiteGeneratorService.php
sed -i '' 's|Nederlandse taal|Deutsche Sprache|g' app/Services/SiteGeneratorService.php
sed -i '' 's|UNIEKE FOCUS|EINZIGARTIGER FOKUS|g' app/Services/SiteGeneratorService.php
sed -i '' 's|Nederlandse|deutsche|g' app/Services/SiteGeneratorService.php

echo "German prompts fixed!"
