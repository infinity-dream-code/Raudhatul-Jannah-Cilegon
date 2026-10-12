<script>
    window.ANGKATAN_CONFIG = @json(\App\Models\mst_thn_aka::angkatanConfig());

    (function () {
        const YEAR_RE = /^\s*(\d{4})\s*\/\s*\d{4}/;
        const UNIT_NAME_RE = /(^|\[)(sekolah|unit)(\]|$)/i;
        const EMPTY_VALUES = ['', 'all', 'semua'];

        function startYearFor(code) {
            const cfg = window.ANGKATAN_CONFIG || {};
            const units = cfg.units || {};
            const key = String(code || '').trim();
            if (key !== '' && units[key]) {
                return parseInt(units[key], 10);
            }
            return cfg.defaultStart ? parseInt(cfg.defaultStart, 10) : null;
        }

        function isYearOption(opt) {
            return YEAR_RE.test(opt.value || '');
        }

        function findUnitSelect(angkatanSelect) {
            const scope = angkatanSelect.closest('form') || angkatanSelect.closest('.card') || document;
            return Array.from(scope.querySelectorAll('select')).find(
                (sel) => sel !== angkatanSelect && UNIT_NAME_RE.test(sel.name || '')
            ) || null;
        }

        function refreshSelect2(select) {
            if (window.jQuery && jQuery(select).data('select2')) {
                jQuery(select).trigger('change.select2');
            }
        }

        function rebuild(angkatanSelect, unitSelect) {
            const original = angkatanSelect._angkatanOriginal;
            const unitValue = String(unitSelect.value || '').trim();
            const unitChosen = !EMPTY_VALUES.includes(unitValue.toLowerCase());
            const start = unitChosen ? startYearFor(unitValue) : null;
            const current = angkatanSelect.value;

            const fixed = original.filter((o) => !o.isYear);
            const years = original
                .filter((o) => o.isYear)
                .map((o) => {
                    const year = parseInt(YEAR_RE.exec(o.value)[1], 10);
                    const no = start ? year - start + 1 : null;
                    return {...o, no};
                })
                .filter((o) => !unitChosen || (o.no !== null && o.no >= 1));

            if (unitChosen) {
                years.sort((a, b) => a.no - b.no);
            }

            angkatanSelect.innerHTML = '';
            fixed.forEach((o) => angkatanSelect.add(new Option(o.text, o.value)));
            years.forEach((o) => {
                const text = unitChosen ? 'Angkatan ' + o.no + ' (' + o.value + ')' : o.text;
                const option = new Option(text, o.value);
                option.disabled = !unitChosen;
                angkatanSelect.add(option);
            });

            const stillValid = Array.from(angkatanSelect.options).some((o) => o.value === current && !o.disabled);
            angkatanSelect.value = stillValid ? current : (fixed[0]?.value ?? '');
            angkatanSelect.dataset.placeholder = unitChosen ? 'Pilih Angkatan' : 'Pilih unit dulu';
            refreshSelect2(angkatanSelect);
        }

        function init() {
            document.querySelectorAll('select').forEach((select) => {
                if (select.dataset.angkatanIgnore !== undefined || select._angkatanOriginal) {
                    return;
                }
                const options = Array.from(select.options);
                if (!options.some(isYearOption)) {
                    return;
                }
                const unitSelect = findUnitSelect(select);
                if (!unitSelect) {
                    return;
                }

                select._angkatanOriginal = options.map((o) => ({
                    value: o.value,
                    text: o.text,
                    isYear: isYearOption(o),
                }));

                const handler = () => rebuild(select, unitSelect);
                if (window.jQuery) {
                    jQuery(unitSelect).on('change', handler);
                } else {
                    unitSelect.addEventListener('change', handler);
                }
                handler();
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
</script>
