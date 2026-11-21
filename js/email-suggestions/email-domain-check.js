// SPDX-FileCopyrightText: 2026 Ugo Parsi
// SPDX-License-Identifier: AFL-3.0

(() => {
    'use strict';

    const { endpoint, formKey, message } = document.currentScript.dataset;
    const fields = 'input[type="email"]:is([id="billing:email"], #email_address_register, [data-email-suggestions])';
    const warnings = new WeakMap();
    const requests = new WeakMap();

    document.addEventListener('input', ({ target: field }) => {
        warnings.get(field)?.remove();
        warnings.delete(field);
        requests.delete(field);
    });

    document.addEventListener('change', async ({ target: field }) => {
        if (!(field instanceof HTMLInputElement) || !field.matches(fields)
            || field.multiple || field.matches(':disabled, [readonly]') || !field.validity.valid || !field.value) {
            return;
        }

        const address = field.value;
        const domain = address.slice(address.lastIndexOf('@') + 1).toLowerCase();
        if (!domain.includes('.') || requests.get(field)?.domain === domain) {
            return;
        }
        const request = { domain };
        requests.set(field, request);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                redirect: 'error',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ domain, form_key: formKey }),
            });
            const status = response.ok ? (await response.json()).status : 'unknown';
            if (status === 'no_mail' && requests.get(field) === request && field.isConnected && field.value === address) {
                const warning = document.createElement('span');
                warning.setAttribute('role', 'status');
                warning.textContent = message;
                field.after(warning);
                warnings.set(field, warning);
            }
        } catch {
        }
    });
})();
