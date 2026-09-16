@php($currentAnswers = old('answers', []))
<div class="space-y-5" data-dynamic-form>
    @foreach($form->fields as $field)
        @php
            $conditionJson = $field->condition ? json_encode($field->condition, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : '';
            $value = $currentAnswers[$field->field_key] ?? null;
            $options = collect($field->options ?? [])->map(fn($option) => is_array($option) ? ['value'=>(string)($option['value'] ?? $option['label'] ?? ''),'label'=>(string)($option['label'] ?? $option['value'] ?? '')] : ['value'=>(string)$option,'label'=>(string)$option]);
        @endphp
        <div class="form-field-block" data-field-key="{{ $field->field_key }}" @if($conditionJson) data-condition='{{ $conditionJson }}' @endif>
            @if($field->field_type === 'heading')
                <div class="border-b border-slate-200 pb-2"><h2 class="text-xl font-bold text-slate-900">{{ $field->label }}</h2>@if($field->help_text)<p class="mt-1 text-sm text-slate-500">{{ $field->help_text }}</p>@endif</div>
            @elseif($field->field_type === 'info')
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4"><p class="font-semibold text-blue-950">{{ $field->label }}</p>@if($field->help_text)<p class="mt-1 whitespace-pre-line text-sm text-blue-900/80">{{ $field->help_text }}</p>@endif</div>
            @else
                <label class="cv-label" for="field-{{ $field->id }}">{{ $field->label }} @if($field->is_required)<span class="text-red-600">*</span>@endif</label>
                @if($field->field_type === 'textarea')
                    <textarea id="field-{{ $field->id }}" class="cv-input min-h-32" name="answers[{{ $field->field_key }}]" placeholder="{{ $field->placeholder }}" @required($field->is_required)>{{ is_array($value) ? '' : $value }}</textarea>
                @elseif($field->field_type === 'email')
                    <input id="field-{{ $field->id }}" class="cv-input" type="email" name="answers[{{ $field->field_key }}]" value="{{ is_array($value) ? '' : $value }}" placeholder="{{ $field->placeholder }}" @required($field->is_required)>
                @elseif($field->field_type === 'number')
                    <input id="field-{{ $field->id }}" class="cv-input" type="number" step="any" name="answers[{{ $field->field_key }}]" value="{{ is_array($value) ? '' : $value }}" placeholder="{{ $field->placeholder }}" @required($field->is_required)>
                @elseif($field->field_type === 'date')
                    <input id="field-{{ $field->id }}" class="cv-input" type="date" name="answers[{{ $field->field_key }}]" value="{{ is_array($value) ? '' : $value }}" @required($field->is_required)>
                @elseif($field->field_type === 'select')
                    <select id="field-{{ $field->id }}" class="cv-input" name="answers[{{ $field->field_key }}]" @required($field->is_required)><option value="">Bitte wählen</option>@foreach($options as $option)<option value="{{ $option['value'] }}" @selected((string)$value === $option['value'])>{{ $option['label'] }}</option>@endforeach</select>
                @elseif($field->field_type === 'radio')
                    <div class="mt-2 grid gap-2">@foreach($options as $index => $option)<label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2"><input id="field-{{ $field->id }}-{{ $index }}" type="radio" name="answers[{{ $field->field_key }}]" value="{{ $option['value'] }}" @checked((string)$value === $option['value']) @required($field->is_required)><span>{{ $option['label'] }}</span></label>@endforeach</div>
                @elseif($field->field_type === 'checkbox')
                    <input type="hidden" name="answers[{{ $field->field_key }}]" value="0"><label class="mt-2 flex items-start gap-3 rounded-lg border border-slate-200 px-4 py-3"><input id="field-{{ $field->id }}" type="checkbox" name="answers[{{ $field->field_key }}]" value="1" @checked((string)$value === '1') @required($field->is_required)><span>{{ $field->placeholder ?: 'Ja' }}</span></label>
                @elseif($field->field_type === 'multiselect')
                    <select id="field-{{ $field->id }}" class="cv-input min-h-32" name="answers[{{ $field->field_key }}][]" multiple @required($field->is_required)>@foreach($options as $option)<option value="{{ $option['value'] }}" @selected(in_array($option['value'], (array)$value, true))>{{ $option['label'] }}</option>@endforeach</select>
                @elseif($field->field_type === 'file')
                    <input id="field-{{ $field->id }}" class="cv-input" type="file" name="files[{{ $field->field_key }}]" @required($field->is_required)>
                @else
                    <input id="field-{{ $field->id }}" class="cv-input" type="text" name="answers[{{ $field->field_key }}]" value="{{ is_array($value) ? '' : $value }}" placeholder="{{ $field->placeholder }}" @required($field->is_required)>
                @endif
                @if($field->help_text)<p class="mt-1.5 text-xs text-slate-500">{{ $field->help_text }}</p>@endif
                @error('answers.'.$field->field_key)<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                @error('files.'.$field->field_key)<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            @endif
        </div>
    @endforeach
</div>
<script>
(() => {
    const root = document.querySelector('[data-dynamic-form]');
    if (!root) return;
    const blocks = [...root.querySelectorAll('.form-field-block')];
    const values = () => {
        const result = {};
        blocks.forEach(block => {
            const key = block.dataset.fieldKey;
            const inputs = [...block.querySelectorAll('input,select,textarea')].filter(el => !el.disabled && el.type !== 'file' && el.type !== 'hidden');
            if (!inputs.length) return;
            if (inputs[0].type === 'radio') {
                result[key] = inputs.find(el => el.checked)?.value ?? '';
            } else if (inputs[0].type === 'checkbox') {
                result[key] = inputs[0].checked ? '1' : '0';
            } else if (inputs[0].multiple) {
                result[key] = [...inputs[0].selectedOptions].map(option => option.value);
            } else {
                result[key] = inputs[0].value;
            }
        });
        return result;
    };
    const evaluate = (condition, data) => {
        if (!condition) return true;
        if (Array.isArray(condition.all)) return condition.all.every(item => evaluate(item, data));
        if (Array.isArray(condition.any)) return condition.any.some(item => evaluate(item, data));
        const actual = data[condition.field_key] ?? '';
        const expected = condition.value ?? '';
        switch (condition.operator) {
            case 'not_equals': return String(actual) !== String(expected);
            case 'contains': return Array.isArray(actual) ? actual.map(String).includes(String(expected)) : String(actual).includes(String(expected));
            case 'not_contains': return Array.isArray(actual) ? !actual.map(String).includes(String(expected)) : !String(actual).includes(String(expected));
            case 'filled': return Array.isArray(actual) ? actual.length > 0 : String(actual).trim() !== '';
            case 'empty': return Array.isArray(actual) ? actual.length === 0 : String(actual).trim() === '';
            default: return String(actual) === String(expected);
        }
    };
    const refresh = () => {
        const data = values();
        blocks.forEach(block => {
            if (!block.dataset.condition) return;
            let condition;
            try { condition = JSON.parse(block.dataset.condition); } catch (_) { return; }
            const visible = evaluate(condition, data);
            block.classList.toggle('hidden', !visible);
            block.querySelectorAll('input,select,textarea').forEach(input => {
                if (!input.dataset.originalRequired) input.dataset.originalRequired = input.required ? '1' : '0';
                input.disabled = !visible;
                input.required = visible && input.dataset.originalRequired === '1';
            });
        });
    };
    root.addEventListener('input', refresh);
    root.addEventListener('change', refresh);
    refresh();
})();
</script>
