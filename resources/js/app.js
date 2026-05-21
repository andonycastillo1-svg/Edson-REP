import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

function normalizeText(value) {
    return (value || '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

function enhanceSearchableSelect(select) {
    if (!select || select.dataset.searchEnhanced === '1') return;
    select.dataset.searchEnhanced = '1';

    const wrapper = document.createElement('div');
    wrapper.className = 'space-y-1';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);

    const input = document.createElement('input');
    input.type = 'text';
    input.placeholder = select.dataset.searchPlaceholder || 'Buscar...';
    input.className = 'w-full rounded-lg border border-slate-200 px-3 py-2 text-sm';
    wrapper.insertBefore(input, select);

    const options = [...select.options];
    const ranked = (query) => {
        const q = normalizeText(query);
        if (!q) return options;
        return [...options].sort((a, b) => {
            const at = normalizeText(a.text);
            const bt = normalizeText(b.text);
            const aName = at.indexOf(q);
            const bName = bt.indexOf(q);
            const aCode = normalizeText(a.value).indexOf(q);
            const bCode = normalizeText(b.value).indexOf(q);
            const aScore = aName >= 0 ? aName : (aCode >= 0 ? 1000 + aCode : 9999);
            const bScore = bName >= 0 ? bName : (bCode >= 0 ? 1000 + bCode : 9999);
            return aScore - bScore;
        });
    };

    input.addEventListener('input', () => {
        const current = select.value;
        const q = normalizeText(input.value);
        const filtered = ranked(q).filter((opt) => {
            if (opt.value === '') return true;
            return normalizeText(opt.text).includes(q) || normalizeText(opt.value).includes(q);
        });
        select.innerHTML = '';
        filtered.forEach((opt) => select.appendChild(opt));
        if ([...select.options].some((opt) => opt.value === current)) {
            select.value = current;
        }
    });
}
window.enhanceSearchableSelect = enhanceSearchableSelect;

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('select[data-searchable="true"]').forEach(enhanceSearchableSelect);
});
