// MGS — shared line-item rows for order/purchase create forms.
// Wires the repeating .product-row block: add-row cloning, searchable select
// re-init, per-row defaults (price/lot autofill), currency toggle and totals.
window.MGSLineItems = {
    /**
     * @param {Object}   opts
     * @param {string}   opts.afnLabel              Localized AFN currency label.
     * @param {string}   [opts.currencySymbol='$']  USD symbol.
     * @param {boolean}  [opts.priceAutofill=false] Autofill unit price from the
     *        selected product's data-price (order form behavior).
     * @param {boolean}  [opts.filterByCurrency=false] Hide products priced in
     *        the other currency from the picker and clear rows that no longer
     *        match the selected currency (purchase form behavior).
     * @param {Object|null} [opts.productLots=null] Map of productId -> lot numbers;
     *        enables the datalist suggestions + hint line (order form behavior).
     * @param {string}   [opts.availableLotsLabel=''] Label prefix for the lot hint.
     * @param {string}   [opts.lotHintId='lot-hint']
     * @param {string}   [opts.lotSuggestionsId='lot-suggestions']
     */
    init({
        afnLabel,
        currencySymbol = '$',
        priceAutofill = false,
        filterByCurrency = false,
        productLots = null,
        availableLotsLabel = '',
        lotHintId = 'lot-hint',
        lotSuggestionsId = 'lot-suggestions',
    }) {
        let productIndex = 1;
        const container = document.getElementById('products-container');

        const formCurrency = () =>
            document.querySelector('input[name="currency"]:checked')?.value === 'USD'
                ? 'USD'
                : 'AFN';

        const getCurrencySymbol = () =>
            formCurrency() === 'USD' ? currencySymbol : afnLabel;

        // A lot is priced in exactly one currency; its price only autofills
        // when the document is in that same currency.
        function optionPrice(opt) {
            if (!opt || (opt.dataset.priceCurrency || 'AFN') !== formCurrency()) return NaN;
            return parseFloat(opt.dataset.price);
        }

        function applyRowDefaults(row) {
            const select = row.querySelector('.product-select');
            const priceInput = row.querySelector('.product-price');
            const lotInput = row.querySelector('.product-lot');

            if (!(select.value && select.selectedIndex >= 0)) {
                if (productLots) {
                    lotInput.value = '';
                    document.getElementById(lotHintId).textContent = '';
                }
                return;
            }

            const opt = select.options[select.selectedIndex];

            if (priceAutofill && !priceInput.value) {
                const price = optionPrice(opt);
                if (!isNaN(price)) priceInput.value = price;
            }
            const lot = opt.dataset.lot;
            if (lot && !lotInput.value) lotInput.value = lot;

            if (productLots) {
                const lots = productLots[select.value] || [];
                if (lot && !lots.includes(lot)) lots.unshift(lot);
                document.getElementById(lotSuggestionsId).innerHTML =
                    lots.map((l) => `<option value="${l}">`).join('');
                document.getElementById(lotHintId).textContent = lots.length
                    ? availableLotsLabel + ': ' + lots.join(', ')
                    : '';
            }
        }

        // A product is priced in exactly one currency: buying it in the other
        // would open a pool it can never be sold from. Reset a row whose
        // picked product is priced in the currency just switched away from.
        function clearRowProduct(row) {
            const searchable = row.querySelector('[data-searchable]');
            const select = row.querySelector('.product-select');
            select.value = '';
            select.dispatchEvent(new Event('change', { bubbles: true }));
            if (searchable) {
                const input = searchable.querySelector('[data-searchable-input]');
                if (input) input.value = '';
                const clearBtn = searchable.querySelector('[data-searchable-clear]');
                if (clearBtn) clearBtn.classList.add('hidden');
                const chevron = searchable.querySelector('[data-searchable-chevron]');
                if (chevron) chevron.classList.remove('hidden');
            }
            row.querySelector('.product-price').value = '';
            row.querySelector('.product-qty').value = 1;
            row.querySelector('.product-lot').value = '';
        }

        function applyCurrencyFilter() {
            if (!filterByCurrency) return;
            const currency = formCurrency();
            container.querySelectorAll('.product-row').forEach((row) => {
                const searchable = row.querySelector('[data-searchable]');
                if (searchable) searchable.setAttribute('data-searchable-currency', currency);
                const select = row.querySelector('.product-select');
                if (select.value && select.selectedIndex >= 0) {
                    const priced = select.options[select.selectedIndex].dataset.priceCurrency || '';
                    if (priced && priced !== currency) clearRowProduct(row);
                }
            });
        }

        function updateTotal() {
            let subtotal = 0;
            document.querySelectorAll('.product-row').forEach((row) => {
                const select = row.querySelector('.product-select');
                const qty = row.querySelector('.product-qty');
                const priceInput = row.querySelector('.product-price');
                if (priceAutofill) {
                    // Order form: row counts once a product + qty exist; unit price
                    // falls back to the option's data-price when left untouched.
                    if (!(select.value && qty.value)) return;
                    const price =
                        parseFloat(priceInput.value) ||
                        optionPrice(select.options[select.selectedIndex]) ||
                        0;
                    subtotal += price * parseInt(qty.value);
                } else {
                    // Purchase form: row counts only with qty AND an entered price.
                    if (!(qty.value && priceInput.value)) return;
                    subtotal += parseFloat(priceInput.value) * parseInt(qty.value);
                }
            });

            const text =
                subtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) +
                ' ' +
                getCurrencySymbol();
            document.getElementById('subtotal-amount').textContent = text;
            document.getElementById('total-amount').textContent = text;
        }

        function wireRow(row) {
            row.querySelector('.product-select').addEventListener('change', function () {
                applyRowDefaults(this.closest('.product-row'));
                updateTotal();
            });
            row.querySelector('.product-price').addEventListener('input', updateTotal);
            row.querySelector('.product-qty').addEventListener('input', updateTotal);
            row.querySelector('.remove-product').addEventListener('click', function () {
                if (container.querySelectorAll('.product-row').length > 1) {
                    this.closest('.product-row').remove();
                    updateTotal();
                }
            });
        }

        function cloneRow() {
            const firstRow = container.querySelector('.product-row');
            const newRow = firstRow.cloneNode(true);

            newRow.querySelector('.product-select').name = `products[${productIndex}][product_id]`;
            newRow.querySelector('.product-select').value = '';
            newRow.querySelector('.product-price').name = `products[${productIndex}][unit_price]`;
            newRow.querySelector('.product-price').value = '';
            newRow.querySelector('.product-qty').name = `products[${productIndex}][quantity]`;
            newRow.querySelector('.product-qty').value = 1;
            newRow.querySelector('.product-lot').name = `products[${productIndex}][lot_number]`;
            newRow.querySelector('.product-lot').value = '';

            newRow.classList.add('flex-wrap');

            const searchable = newRow.querySelector('[data-searchable]');
            if (searchable) {
                delete searchable.dataset.searchableInitialized;
                window.initSearchableSelect(searchable);
            }

            wireRow(newRow);
            container.appendChild(newRow);
            productIndex++;
        }

        document.querySelectorAll('input[name="currency"]').forEach((radio) => {
            radio.addEventListener('change', () => {
                // Re-run autofill so rows with an untouched price pick up the
                // product price in the newly selected currency (if any).
                if (priceAutofill) {
                    container.querySelectorAll('.product-row').forEach(applyRowDefaults);
                }
                applyCurrencyFilter();
                updateTotal();
            });
        });
        document.getElementById('add-product').addEventListener('click', cloneRow);
        container.querySelectorAll('.product-row').forEach(wireRow);

        applyCurrencyFilter();
        updateTotal();
    },
};
