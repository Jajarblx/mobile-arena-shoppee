
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toast').forEach((toast) => {
        window.setTimeout(() => {
            toast.style.transition = 'opacity .35s ease, transform .35s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-8px)';
            window.setTimeout(() => toast.remove(), 400);
        }, 3500);
    });

    const deliveryAddress = document.querySelector('textarea[name="address"]');
    const fulfillmentRadios = document.querySelectorAll('input[name="fulfillment"]');
    const syncAddress = () => {
        if (!deliveryAddress) return;
        const selected = document.querySelector('input[name="fulfillment"]:checked');
        const delivery = selected?.value === 'delivery_request';
        deliveryAddress.required = delivery;
        deliveryAddress.closest('label')?.classList.toggle('field-highlight', delivery);
    };
    fulfillmentRadios.forEach((radio) => radio.addEventListener('change', syncAddress));
    syncAddress();

    const deliveryOptions = document.querySelector('[data-delivery-options]');
    const savedAddress = document.querySelector('[data-saved-address]');
    const newAddress = document.querySelector('[data-new-address]');
    const savedSelect = savedAddress?.querySelector('select');
    const checkoutPricing = document.querySelector('[data-checkout-pricing]');
    const cashOnPickup = document.querySelector('input[name="payment_method"][value="cash_on_pickup"]');
    const payAtStore = document.querySelector('input[name="payment_method"][value="pay_at_store"]');
    const cashOnDelivery = document.querySelector('input[name="payment_method"][value="cash_on_delivery"]');
    const formatPeso = (amount) => `â‚±${Number(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const syncDeliveryPrice = () => {
        if (!checkoutPricing) return;
        const delivery = document.querySelector('input[name="fulfillment"]:checked')?.value === 'delivery_request';
        const feeLabel = checkoutPricing.querySelector('[data-delivery-fee]');
        const totalLabel = checkoutPricing.querySelector('[data-order-total]');
        const subtotal = Number(checkoutPricing.dataset.subtotal);
        if (!delivery) {
            feeLabel.textContent = 'Free pickup';
            totalLabel.textContent = formatPeso(subtotal);
            return;
        }
        const choice = document.querySelector('input[name="address_choice"]:checked')?.value;
        let fee;
        if (choice === 'saved') {
            fee = Number(savedSelect?.selectedOptions[0]?.dataset.fee);
        } else {
            const field = (name) => checkoutPricing.querySelector(`[name="new_address[${name}]"]`)?.value.trim().toLocaleLowerCase();
            const region = field('region');
            const province = field('province');
            const city = field('city');
            if (region && province && city) {
                const origin = JSON.parse(checkoutPricing.dataset.origin);
                const fees = JSON.parse(checkoutPricing.dataset.fees);
                const same = (value, fieldName) => value === origin[fieldName].toLocaleLowerCase();
                const band = same(province, 'province') && same(city, 'city') ? 'local'
                    : same(province, 'province') ? 'province' : same(region, 'region') ? 'regional' : 'national';
                fee = Number(fees[band]);
            }
        }
        feeLabel.textContent = Number.isFinite(fee) ? formatPeso(fee) : 'Enter an address';
        totalLabel.textContent = Number.isFinite(fee) ? formatPeso(subtotal + fee) : 'Calculated with address';
    };
    const syncCheckoutDelivery = () => {
        if (!deliveryOptions) return;
        const delivery = document.querySelector('input[name="fulfillment"]:checked')?.value === 'delivery_request';
        const choice = document.querySelector('input[name="address_choice"]:checked')?.value;
        deliveryOptions.hidden = !delivery;
        if (savedAddress) savedAddress.hidden = !delivery || choice !== 'saved';
        if (savedSelect) {
            savedSelect.disabled = !delivery || choice !== 'saved';
            savedSelect.required = delivery && choice === 'saved';
        }
        if (newAddress) {
            newAddress.hidden = !delivery || choice !== 'new';
            newAddress.disabled = !delivery || choice !== 'new';
        }
        if (cashOnPickup) cashOnPickup.disabled = delivery;
        if (payAtStore) payAtStore.disabled = delivery;
        if (cashOnDelivery) cashOnDelivery.disabled = !delivery;
        const checkedMethod = document.querySelector('input[name="payment_method"]:checked');
        if (checkedMethod?.disabled) (delivery ? cashOnDelivery : payAtStore).checked = true;
        syncDeliveryPrice();
    };
    document.querySelectorAll('input[name="fulfillment"], input[name="address_choice"]').forEach((radio) => radio.addEventListener('change', syncCheckoutDelivery));
    savedSelect?.addEventListener('change', syncDeliveryPrice);
    checkoutPricing?.querySelectorAll('[name^="new_address["]').forEach((field) => field.addEventListener('input', syncDeliveryPrice));
    syncCheckoutDelivery();

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = button.parentElement?.querySelector('input');
            if (!input) return;
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.textContent = visible ? 'Hide' : 'Show';
            button.setAttribute('aria-label', `${visible ? 'Hide' : 'Show'} ${input.name.replaceAll('_', ' ')}`);
        });
    });

    document.querySelectorAll('form[data-submit-once]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"]');
            if (!button) return;
            window.setTimeout(() => {
                button.disabled = true;
                button.textContent = button.dataset.busyLabel || 'Please waitâ€¦';
            }, 0);
        });
    });

    const filterToggle = document.querySelector('[data-filter-toggle]');
    const filterPanel = document.querySelector('#catalog-filters');
    filterToggle?.addEventListener('click', () => {
        const expanded = filterToggle.getAttribute('aria-expanded') === 'true';
        filterToggle.setAttribute('aria-expanded', String(!expanded));
        filterPanel?.classList.toggle('filter-open', !expanded);
        if (!expanded) filterPanel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    const mainImage = document.querySelector('[data-gallery-main]');
    document.querySelectorAll('[data-gallery-image]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!mainImage) return;
            mainImage.src = button.dataset.galleryImage;
            mainImage.alt = button.dataset.galleryAlt;
            document.querySelectorAll('[data-gallery-image]').forEach((thumb) => {
                const selected = thumb === button;
                thumb.classList.toggle('active', selected);
                thumb.setAttribute('aria-pressed', String(selected));
            });
        });
    });
});

