@if(getSetting('fathom_site_id'))
    <script>
    window.addEventListener('load', () => {
        document.querySelectorAll('a[rel*="nofollow sponsored"]').forEach(item => {
            item.addEventListener('click', event => {
                try {
                    let url = new URL(item.getAttribute('href'), window.location.href);

                    if (url.hostname !== window.location.hostname) {
                        let productName = item.getAttribute('data-product') || item.textContent.trim();
                        let domainParts = url.hostname.split('.');
                        let domainName = domainParts.length > 1 ? domainParts[domainParts.length - 2] : domainParts[0];
                        let shortProductName = productName.length > 50
                            ? productName.substring(0, 50) + '...'
                            : productName;

                        if (typeof fathom !== 'undefined' && fathom.trackEvent) {
                            fathom.trackEvent(`Affiliate click: ${domainName} - ${shortProductName}`);
                        }
                    }
                } catch (e) {
                    console.debug('Fathom tracking error:', e);
                }
            });
        });
    });
    </script>
@endif
