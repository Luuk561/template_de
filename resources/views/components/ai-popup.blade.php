<!-- Trigger (onzichtbaar) -->
<button id="ai-Fazit-btn" class="hidden"></button>

<!-- AI Hint Box -->
<div id="ai-hint-box" class="fixed bottom-6 right-6 z-40 bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 text-white rounded-2xl shadow-2xl px-6 py-5 w-[320px] max-w-full animate-fade-in ring-1 ring-white/10 backdrop-blur-sm transition-transform transform hover:scale-[1.02] hidden">
    <button type="button"
        onclick="document.getElementById('ai-hint-box').classList.add('hidden');"
        class="absolute top-3 right-3 text-white/80 hover:text-white text-lg font-bold transition">
        &times;
    </button>
    <div class="flex items-center gap-3 mb-2">
        <div class="bg-white/20 rounded-full p-2">
            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 4v1m0 14v1m8-8h1M4 12H3m15.364-5.364l.707.707M6.343 17.657l-.707.707m12.021 0l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z" />
            </svg>
        </div>
        <p class="text-base font-semibold">Schwer zu entscheiden?</p>
    </div>
    <p class="text-sm text-white/90 leading-snug mb-4">
        Lassen Sie unsere smarte AI in Sekunden Ihre beste Wahl finden.
    </p>
    <button id="ai-hint-start"
        class="w-full text-sm font-bold bg-white text-blue-800 hover:text-white hover:bg-blue-900 px-4 py-2 rounded-xl shadow-md transition-all duration-200">
        🤖 AI-Hilfe starten
    </button>
</div>

<!-- AI Reopen Button -->
<button id="ai-reopen-btn"
    class="fixed bottom-6 right-6 z-40 bg-blue-700 text-white rounded-full w-14 h-14 flex items-center justify-center shadow-xl hover:bg-blue-800 transition-all hidden"
    aria-label="AI-Analyse erneut anzeigen">
    <span class="text-3xl font-regular">+</span>
</button>

<!-- AI Fazit Modaal -->
<div id="ai-Fazit-modal"
    class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden flex items-center justify-center p-4">
<div id="ai-Fazit-box"
    class="relative w-full max-w-xl max-h-[80vh] overflow-y-auto bg-white rounded-2xl shadow-2xl border border-blue-300 animate-fade-in-up">

        <!-- Sluitknop -->
        <button type="button"
            onclick="closeAiFazitModal()"
            class="absolute top-4 right-4 text-blue-700 hover:text-red-500 text-2xl font-extrabold transition">
            &times;
        </button>

        <!-- Inhoud -->
        <div class="p-6 md:p-10 space-y-6">
            <div class="flex items-center gap-4">
                <div class="relative w-11 h-11">
                    <div class="absolute inset-0 rounded-full bg-blue-600 opacity-30 animate-ping"></div>
                    <div class="relative w-11 h-11 flex items-center justify-center rounded-full bg-blue-700 text-white font-bold shadow-lg text-sm">
                        AI
                    </div>
                </div>
                <h2 id="ai-Fazit-title" class="text-2xl font-extrabold text-blue-800">
                    AI-Analyse läuft
                </h2>
            </div>

            <div id="ai-Fazit-content"
                class="text-gray-800 text-base md:text-[17px] leading-relaxed space-y-4 transition-all duration-300">
                <div class="text-center">
                    <p class="text-blue-700 text-sm md:text-base font-medium">
                        Unsere smarte AI denkt für Sie mit... Einen Moment bitte, während wir Ihre perfekte Wahl berechnen.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="border-t border-blue-100 bg-blue-50 text-blue-700 text-xs text-center py-2 px-4">
            Hinweis: AI-Analysen basieren auf generierten Interpretationen und können Ungenauigkeiten enthalten.
        </div>
       
        <!-- Scroll fade overlays voor mobiel -->
        <div id="ai-scroll-fade-top"></div>
        <div id="ai-scroll-fade-bottom"></div>
    </div>
</div>

<script>
    let aiAnalyseGestart = false;

    function closeAiFazitModal() {
        document.getElementById('ai-Fazit-modal').classList.add('hidden');
        document.body.classList.remove('modal-open');
        document.getElementById('ai-reopen-btn').classList.remove('hidden');
    }

    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            const hintBox = document.getElementById('ai-hint-box');
            if (hintBox) hintBox.classList.remove('hidden');
        }, 6000);

        document.getElementById('ai-hint-start')?.addEventListener('click', () => {
            aiAnalyseGestart = true;
            const e = new Event('startAiAnalyse');
            document.dispatchEvent(e);
            document.getElementById('ai-hint-box')?.classList.add('hidden');
            document.getElementById('ai-reopen-btn')?.classList.remove('hidden');
        });

        document.querySelector('#ai-hint-box button[onclick*="classList.add"]')?.addEventListener('click', () => {
            document.getElementById('ai-hint-box')?.classList.add('hidden');
            document.getElementById('ai-reopen-btn')?.classList.remove('hidden');
        });

        document.getElementById('ai-reopen-btn')?.addEventListener('click', () => {
            if (!aiAnalyseGestart) {
                // Nog geen analyse gestart → hint opnieuw tonen
                document.getElementById('ai-hint-box')?.classList.remove('hidden');
                document.getElementById('ai-reopen-btn')?.classList.add('hidden');
                return;
            }

            const analyseTitle = document.getElementById('ai-Fazit-title');
            const analyseContent = document.getElementById('ai-Fazit-content');
            const analyseBox = document.getElementById('ai-Fazit-box');
            const modal = document.getElementById('ai-Fazit-modal');
            const eansMeta = document.querySelector('meta[name="ai-eans"]');
            const eans = eansMeta ? JSON.parse(eansMeta.content) : [];

            if (!eans || eans.length === 0) return;

            const cacheKey = 'ai_Fazit_' + eans.sort().join('-');
            const cached = localStorage.getItem(cacheKey);
            if (!cached) return;

            analyseTitle.textContent = 'AI-Analyse Ergebnis';
            modal.classList.remove('hidden');
            analyseBox.classList.remove('hidden');
            analyseContent.innerHTML = cached;
            document.getElementById('ai-reopen-btn')?.classList.add('hidden');
        });
    });

    document.addEventListener('startAiAnalyse', function () {
        aiAnalyseGestart = true;

        const analyseBox = document.getElementById('ai-Fazit-box');
        const analyseContent = document.getElementById('ai-Fazit-content');
        const analyseTitle = document.getElementById('ai-Fazit-title');
        const modal = document.getElementById('ai-Fazit-modal');
        const reopenBtn = document.getElementById('ai-reopen-btn');
        const eansMeta = document.querySelector('meta[name="ai-eans"]');
        const eans = eansMeta ? JSON.parse(eansMeta.content) : [];

        reopenBtn?.classList.add('hidden');

        if (!eans || eans.length === 0) {
            analyseTitle.textContent = 'Geen Produkte geselecteerd';
            analyseContent.innerHTML = `<p class="text-sm text-red-500 text-center">Wählen Sie zuerst Produkte zum Vergleichen aus.</p>`;
            modal.classList.remove('hidden');
            return;
        }

        const cacheKey = 'ai_Fazit_' + eans.sort().join('-');

        function showResult(html) {
            analyseTitle.textContent = 'AI-Analyse Ergebnis';
            modal.classList.remove('hidden');
            analyseBox.classList.remove('hidden');
            analyseContent.innerHTML = html;
        }

        const cached = localStorage.getItem(cacheKey);
        if (cached) {
            showResult(cached);
            return;
        }

        modal.classList.remove('hidden');
        analyseBox.classList.remove('hidden');
        document.body.classList.add('modal-open');
        analyseTitle.textContent = 'AI-Analyse läuft';
        analyseContent.innerHTML = `
            <div class="flex flex-col items-center space-y-4">
                <div class="relative w-10 h-10">
                    <div class="absolute inset-0 rounded-full bg-blue-600 opacity-30 animate-ping"></div>
                    <div class="relative w-10 h-10 flex items-center justify-center rounded-full bg-blue-700 text-white font-bold text-sm shadow-md">
                        AI
                    </div>
                </div>
                <p class="text-sm text-blue-700 text-center">AI-Analyse wird geladen…</p>
            </div>
        `;

        fetch("{{ route('ai.conclusie') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ eans })
        })
        .then(res => res.json())
        .then(data => {
            localStorage.setItem(cacheKey, data.html);
            showResult(data.html);
        })
        .catch(() => {
            analyseTitle.textContent = 'Fehler beim Laden';
            analyseContent.innerHTML = '<p class="text-red-600 text-sm text-center">AI-Analyse konnte nicht geladen werden. Bitte versuchen Sie es später erneut.</p>';
        });
    });

    function updateAiScrollFade() {
        const box = document.getElementById('ai-Fazit-box');
        const topFade = document.getElementById('ai-scroll-fade-top');
        const bottomFade = document.getElementById('ai-scroll-fade-bottom');

        if (!box || !topFade || !bottomFade) return;

        const scrollTop = box.scrollTop;
        const scrollHeight = box.scrollHeight;
        const offsetHeight = box.offsetHeight;

        topFade.style.display = (scrollTop > 5) ? 'block' : 'none';
        bottomFade.style.display = (scrollTop + offsetHeight < scrollHeight - 5) ? 'block' : 'none';
    }

    if (window.innerWidth <= 640) {
        const box = document.getElementById('ai-Fazit-box');
        if (box) {
            box.addEventListener('scroll', updateAiScrollFade);
            window.addEventListener('resize', updateAiScrollFade);
            setTimeout(updateAiScrollFade, 300); // Delay voor initiële load
        }
    }
</script>

<style>
    @keyframes fade-in {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in, .animate-fade-in-up {
        animation: fade-in 0.4s ease-out both;
    }

    @media (max-width: 640px) {
        #ai-hint-box {
            left: 50% !important;
            right: auto !important;
            transform: translateX(-50%) !important;
            width: 90% !important;
            max-width: 380px;
            padding: 1.25rem 1.25rem !important;
            border-radius: 1.25rem !important;
        }
    }

    @media (max-width: 640px) {
        #ai-Fazit-box {
            width: 100% !important;
            max-width: 100% !important;
            height: auto !important;
            max-height: 60vh !important;
            border-radius: 1rem !important;
            margin: 0 !important;
        }
    }

    @media (max-width: 640px) {
        #ai-Fazit-box {
            position: relative;
        }

        #ai-scroll-fade-top,
        #ai-scroll-fade-bottom {
            content: '';
            position: absolute;
            left: 0;
            width: 100%;
            height: 30px;
            pointer-events: none;
            z-index: 10;
        }

        #ai-scroll-fade-top {
            top: 0;
            background: linear-gradient(to bottom, white, transparent);
            display: none;
        }

        #ai-scroll-fade-bottom {
            bottom: 0;
            background: linear-gradient(to top, white, transparent);
            display: none;
        }
    }

    body.modal-open {
        overflow: hidden;
    }

    #ai-Fazit-box::-webkit-scrollbar {
        width: 8px;
    }

    #ai-Fazit-box::-webkit-scrollbar-thumb {
        background-color: rgba(0, 0, 0, 0.1);
        border-radius: 4px;
    }

    #ai-Fazit-box::-webkit-scrollbar-track {
        background: transparent;
    }

    #ai-Fazit-box {
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
    }
</style>