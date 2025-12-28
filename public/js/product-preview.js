// Product Link Hover Preview
document.addEventListener('DOMContentLoaded', function() {
    // Create preview container
    const preview = document.createElement('div');
    preview.id = 'product-preview';
    preview.className = 'fixed z-50 pointer-events-none transition-opacity duration-200 opacity-0';
    preview.style.cssText = 'max-width: 320px;';
    document.body.appendChild(preview);

    let currentLink = null;
    let hideTimeout = null;

    // Get all product links
    const productLinks = document.querySelectorAll('a.product-link[data-product-preview]');

    productLinks.forEach(link => {
        link.addEventListener('mouseenter', function(e) {
            clearTimeout(hideTimeout);
            currentLink = this;

            try {
                const data = JSON.parse(this.dataset.productPreview);

                // Build preview HTML
                const html = `
                    <div class="bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden transform scale-95 transition-transform duration-200">
                        <div class="p-4">
                            <div class="flex gap-4">
                                <div class="flex-shrink-0">
                                    <img src="${data.image || '/images/placeholder.jpg'}"
                                         alt="${data.title}"
                                         class="w-24 h-24 object-contain rounded-lg bg-gray-50">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-sm font-semibold text-gray-900 mb-2 line-clamp-2">
                                        ${data.title}
                                    </h4>
                                    ${data.price ? `
                                        <div class="text-lg font-bold text-blue-600 mb-1">
                                            €${parseFloat(data.price).toFixed(2).replace('.', ',')}
                                        </div>
                                    ` : ''}
                                    ${data.rating ? `
                                        <div class="flex items-center gap-1 text-xs text-gray-600">
                                            <svg class="w-4 h-4 text-yellow-400 fill-current" viewBox="0 0 20 20">
                                                <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                                            </svg>
                                            <span class="font-medium">${parseFloat(data.rating).toFixed(1)}</span>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-100">
                                <span class="text-xs text-gray-500 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                    </svg>
                                    Klik voor volledige specificaties
                                </span>
                            </div>
                        </div>
                    </div>
                `;

                preview.innerHTML = html;
                positionPreview(e);

                // Show with animation
                setTimeout(() => {
                    preview.style.opacity = '1';
                    preview.querySelector('div').style.transform = 'scale(1)';
                }, 10);

            } catch (err) {
                console.error('Failed to parse product preview data:', err);
            }
        });

        link.addEventListener('mousemove', function(e) {
            if (currentLink === this) {
                positionPreview(e);
            }
        });

        link.addEventListener('mouseleave', function() {
            currentLink = null;
            hideTimeout = setTimeout(() => {
                preview.style.opacity = '0';
                preview.querySelector('div').style.transform = 'scale(0.95)';
            }, 100);
        });
    });

    function positionPreview(e) {
        const padding = 20; // Distance from cursor
        const previewWidth = 320;
        const previewHeight = preview.offsetHeight || 200;

        let x = e.clientX + padding;
        let y = e.clientY - previewHeight / 2;

        // Keep within viewport horizontally
        if (x + previewWidth > window.innerWidth - padding) {
            x = e.clientX - previewWidth - padding;
        }

        // Keep within viewport vertically
        if (y < padding) {
            y = padding;
        } else if (y + previewHeight > window.innerHeight - padding) {
            y = window.innerHeight - previewHeight - padding;
        }

        preview.style.left = x + 'px';
        preview.style.top = y + 'px';
    }
});
