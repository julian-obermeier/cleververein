<x-layouts.app title="Dokumentvorlage">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <a href="{{ route('documents.index') }}" class="text-sm font-semibold text-blue-700 hover:underline">← Dokumente</a>
            <h1 class="mt-2 text-3xl font-bold tracking-tight">{{ $template->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">Visueller Vorlageneditor · Elemente per Drag & Drop positionieren, Eigenschaften rechts bearbeiten.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <select id="preview-member" class="cv-input w-64"><option value="">Vorschau ohne Mitglied</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->member_number }} · {{ $member->person->display_name }}</option>@endforeach</select>
            <button type="button" id="open-preview" class="cv-button border border-slate-300 bg-white text-slate-700">PDF-Vorschau</button>
            <button type="submit" form="template-form" class="cv-button-primary">Vorlage speichern</button>
        </div>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form id="template-form" method="post" action="{{ route('documents.templates.update', $template) }}" class="mt-6">@csrf @method('put')
        <input type="hidden" name="layout_json" id="layout-json">
        <section class="cv-panel p-5">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <label class="xl:col-span-2"><span class="cv-label">Vorlagenname *</span><input required class="cv-input" name="name" value="{{ old('name', $template->name) }}"></label>
                <label><span class="cv-label">Kategorie</span><input class="cv-input" name="category" value="{{ old('category', $template->category) }}"></label>
                <label><span class="cv-label">Format</span><select id="page-size" class="cv-input" name="page_size">@foreach(['A4','A5','Letter'] as $size)<option @selected($template->page_size === $size)>{{ $size }}</option>@endforeach</select></label>
                <label><span class="cv-label">Ausrichtung</span><select id="orientation" class="cv-input" name="orientation"><option value="portrait" @selected($template->orientation === 'portrait')>Hochformat</option><option value="landscape" @selected($template->orientation === 'landscape')>Querformat</option></select></label>
                <div class="flex items-end gap-2"><button type="button" id="add-text" class="cv-button w-full border border-slate-300 bg-white text-slate-700">+ Textfeld</button><button type="button" id="add-line" class="cv-button w-full border border-slate-300 bg-white text-slate-700">+ Linie</button></div>
                <label class="md:col-span-2 xl:col-span-6"><span class="cv-label">Beschreibung</span><textarea class="cv-input min-h-16" name="description">{{ old('description', $template->description) }}</textarea></label>
            </div>
        </section>

        <div class="mt-4 grid gap-4 2xl:grid-cols-[270px_minmax(650px,1fr)_320px]">
            <aside class="cv-panel self-start p-4 2xl:sticky 2xl:top-20">
                <h2 class="font-bold">Platzhalter</h2>
                <p class="mt-1 text-xs text-slate-500">Klick fügt den Platzhalter in das ausgewählte Textfeld ein.</p>
                <div class="mt-4 max-h-[68vh] space-y-1 overflow-y-auto pr-1">
                    @foreach($placeholders as $token => $label)
                        <button type="button" data-placeholder="{{ $token }}" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-left hover:border-blue-300 hover:bg-blue-50">
                            <span class="block text-xs font-semibold text-blue-700">{{ $token }}</span><span class="mt-0.5 block text-xs text-slate-500">{{ $label }}</span>
                        </button>
                    @endforeach
                </div>
            </aside>

            <section class="cv-panel overflow-auto bg-slate-200 p-5">
                <div class="mb-3 flex items-center justify-between text-xs text-slate-600"><span>Arbeitsfläche · Raster 5 mm</span><span>Element anklicken und ziehen</span></div>
                <div id="document-stage" class="relative mx-auto origin-top bg-white shadow-xl ring-1 ring-slate-300" style="background-image:linear-gradient(to right,rgba(148,163,184,.12) 1px,transparent 1px),linear-gradient(to bottom,rgba(148,163,184,.12) 1px,transparent 1px);background-size:15px 15px;"></div>
            </section>

            <aside class="cv-panel self-start p-4 2xl:sticky 2xl:top-20">
                <div class="flex items-center justify-between"><h2 class="font-bold">Element</h2><button type="button" id="delete-block" class="text-sm font-semibold text-red-700 disabled:opacity-30" disabled>Löschen</button></div>
                <p id="no-selection" class="mt-4 text-sm text-slate-500">Wählen Sie ein Element auf der Arbeitsfläche aus.</p>
                <div id="properties" class="mt-4 hidden space-y-4">
                    <label id="text-property"><span class="cv-label">Inhalt</span><textarea id="prop-text" class="cv-input min-h-40" placeholder="Text und Platzhalter"></textarea></label>
                    <div class="grid grid-cols-2 gap-3">
                        <label><span class="cv-label">X (mm)</span><input id="prop-x" type="number" min="0" step="0.5" class="cv-input"></label>
                        <label><span class="cv-label">Y (mm)</span><input id="prop-y" type="number" min="0" step="0.5" class="cv-input"></label>
                        <label><span class="cv-label">Breite (mm)</span><input id="prop-w" type="number" min="5" step="0.5" class="cv-input"></label>
                        <label><span class="cv-label">Höhe (mm)</span><input id="prop-h" type="number" min="1" step="0.5" class="cv-input"></label>
                    </div>
                    <div id="text-style-properties" class="space-y-4">
                        <label><span class="cv-label">Schriftgröße</span><input id="prop-font-size" type="number" min="6" max="48" step="0.5" class="cv-input"></label>
                        <label><span class="cv-label">Schriftgewicht</span><select id="prop-font-weight" class="cv-input"><option value="400">Normal</option><option value="600">Halbfett</option><option value="700">Fett</option></select></label>
                        <label><span class="cv-label">Ausrichtung</span><select id="prop-align" class="cv-input"><option value="left">Links</option><option value="center">Zentriert</option><option value="right">Rechts</option></select></label>
                    </div>
                    <label><span class="cv-label">Farbe</span><input id="prop-color" type="color" class="h-10 w-full rounded-lg border border-slate-300 bg-white p-1"></label>
                </div>
                <div class="mt-6 border-t border-slate-200 pt-4 text-xs text-slate-500">
                    <p class="font-semibold text-slate-700">Hinweis</p><p class="mt-1">Das Layout wird serverseitig erneut validiert. Externes HTML, JavaScript und Remote-Inhalte werden nicht in PDFs ausgeführt.</p>
                </div>
            </aside>
        </div>
    </form>

    <script>
        (() => {
            const initial = @json($template->layout ?? ['blocks' => []]);
            const scale = 3;
            let blocks = Array.isArray(initial.blocks) ? structuredClone(initial.blocks) : [];
            let selectedId = null;
            let drag = null;
            const stage = document.getElementById('document-stage');
            const form = document.getElementById('template-form');
            const sizeSelect = document.getElementById('page-size');
            const orientationSelect = document.getElementById('orientation');
            const properties = document.getElementById('properties');
            const noSelection = document.getElementById('no-selection');
            const deleteButton = document.getElementById('delete-block');
            const textProperty = document.getElementById('text-property');
            const textStyleProperties = document.getElementById('text-style-properties');
            const fields = {
                text: document.getElementById('prop-text'), x: document.getElementById('prop-x'), y: document.getElementById('prop-y'),
                w: document.getElementById('prop-w'), h: document.getElementById('prop-h'), font_size: document.getElementById('prop-font-size'),
                font_weight: document.getElementById('prop-font-weight'), align: document.getElementById('prop-align'), color: document.getElementById('prop-color'),
            };
            const pageSizes = { A4: [210, 297], A5: [148, 210], Letter: [216, 279] };
            const uid = () => 'b-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 7);
            const selected = () => blocks.find(block => block.id === selectedId) || null;
            const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
            const dimensions = () => {
                let [w, h] = pageSizes[sizeSelect.value] || pageSizes.A4;
                if (orientationSelect.value === 'landscape') [w, h] = [h, w];
                return [w, h];
            };
            const snap = value => Math.max(0, Math.round(value / 5) * 5);

            function renderStage() {
                const [pageW, pageH] = dimensions();
                stage.style.width = `${pageW * scale}px`;
                stage.style.height = `${pageH * scale}px`;
                stage.innerHTML = '';
                blocks.forEach(block => {
                    const element = document.createElement('div');
                    element.dataset.blockId = block.id;
                    element.className = 'absolute cursor-move overflow-hidden border ' + (block.id === selectedId ? 'border-blue-600 ring-2 ring-blue-200' : 'border-transparent hover:border-blue-300');
                    element.style.left = `${Number(block.x || 0) * scale}px`;
                    element.style.top = `${Number(block.y || 0) * scale}px`;
                    element.style.width = `${Number(block.w || 10) * scale}px`;
                    element.style.height = `${Math.max(1, Number(block.h || 1)) * scale}px`;
                    element.style.color = block.color || '#0f172a';
                    if (block.type === 'line') {
                        element.innerHTML = '<div style="margin-top:2px;border-top:1px solid currentColor"></div>';
                    } else {
                        element.style.fontSize = `${Math.max(6, Number(block.font_size || 11)) * .8}px`;
                        element.style.fontWeight = block.font_weight || '400';
                        element.style.textAlign = block.align || 'left';
                        element.style.whiteSpace = 'pre-wrap';
                        element.style.lineHeight = '1.2';
                        element.innerHTML = escapeHtml(block.text || 'Textfeld');
                    }
                    element.addEventListener('pointerdown', event => startDrag(event, block));
                    element.addEventListener('click', event => { event.stopPropagation(); selectBlock(block.id); });
                    stage.appendChild(element);
                });
            }

            function selectBlock(id) {
                selectedId = id;
                const block = selected();
                properties.classList.toggle('hidden', !block);
                noSelection.classList.toggle('hidden', !!block);
                deleteButton.disabled = !block;
                if (!block) return renderStage();
                Object.entries(fields).forEach(([key, input]) => { if (input) input.value = block[key] ?? ''; });
                const isText = block.type !== 'line';
                textProperty.classList.toggle('hidden', !isText);
                textStyleProperties.classList.toggle('hidden', !isText);
                renderStage();
            }

            function startDrag(event, block) {
                event.preventDefault();
                selectBlock(block.id);
                drag = { id: block.id, startX: event.clientX, startY: event.clientY, x: Number(block.x || 0), y: Number(block.y || 0) };
                event.currentTarget.setPointerCapture?.(event.pointerId);
            }

            document.addEventListener('pointermove', event => {
                if (!drag) return;
                const block = blocks.find(item => item.id === drag.id);
                if (!block) return;
                const [pageW, pageH] = dimensions();
                block.x = Math.min(Math.max(0, drag.x + (event.clientX - drag.startX) / scale), Math.max(0, pageW - Number(block.w || 0)));
                block.y = Math.min(Math.max(0, drag.y + (event.clientY - drag.startY) / scale), Math.max(0, pageH - Number(block.h || 0)));
                block.x = Math.round(block.x * 2) / 2;
                block.y = Math.round(block.y * 2) / 2;
                fields.x.value = block.x; fields.y.value = block.y;
                renderStage();
            });
            document.addEventListener('pointerup', () => { drag = null; });
            stage.addEventListener('click', () => selectBlock(null));

            document.getElementById('add-text').addEventListener('click', () => {
                const block = { id: uid(), type: 'text', x: 20, y: 20, w: 80, h: 18, text: 'Neues Textfeld', font_size: 11, font_weight: '400', align: 'left', color: '#0f172a' };
                blocks.push(block); selectBlock(block.id);
            });
            document.getElementById('add-line').addEventListener('click', () => {
                const block = { id: uid(), type: 'line', x: 20, y: 40, w: 80, h: 2, text: '', font_size: 11, font_weight: '400', align: 'left', color: '#94a3b8' };
                blocks.push(block); selectBlock(block.id);
            });
            deleteButton.addEventListener('click', () => {
                if (!selectedId) return;
                blocks = blocks.filter(block => block.id !== selectedId); selectedId = null; selectBlock(null);
            });

            Object.entries(fields).forEach(([key, input]) => input?.addEventListener('input', () => {
                const block = selected(); if (!block) return;
                block[key] = ['x','y','w','h','font_size'].includes(key) ? Number(input.value) : input.value;
                renderStage();
            }));
            document.querySelectorAll('[data-placeholder]').forEach(button => button.addEventListener('click', () => {
                const block = selected();
                if (!block || block.type === 'line') return;
                block.text = `${block.text || ''}${button.dataset.placeholder}`;
                fields.text.value = block.text;
                renderStage();
            }));
            [sizeSelect, orientationSelect].forEach(input => input.addEventListener('change', renderStage));
            form.addEventListener('submit', () => { document.getElementById('layout-json').value = JSON.stringify({ blocks }); });
            document.getElementById('open-preview').addEventListener('click', () => {
                const member = document.getElementById('preview-member').value;
                const url = new URL(@json(route('documents.templates.preview', $template)), window.location.origin);
                if (member) url.searchParams.set('member_id', member);
                window.open(url.toString(), '_blank', 'noopener');
            });
            document.addEventListener('keydown', event => {
                if ((event.key === 'Delete' || event.key === 'Backspace') && selectedId && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement?.tagName)) deleteButton.click();
            });
            renderStage();
        })();
    </script>
</x-layouts.app>
