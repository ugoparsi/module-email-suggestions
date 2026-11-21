// SPDX-FileCopyrightText: 2026 Ugo Parsi
// SPDX-License-Identifier: AFL-3.0

(() => {
    'use strict';

    const domains = new Set([
        'aol.com', 'comcast.net', 'free.fr', 'gmail.com', 'gmx.de', 'hotmail.com',
        'icloud.com', 'live.com', 'mail.com', 'orange.fr', 'outlook.com', 'proton.me',
        'protonmail.com', 'web.de', 'yahoo.com', 'yahoo.fr', 'yandex.com',
    ]);
    const fields = 'input[type="email"]:is([id="billing:email"], #email_address_register, [data-email-suggestions])';
    const suggestions = new WeakMap();

    function differsByOneEdit(value, candidate) {
        if (value.length > candidate.length) {
            return differsByOneEdit(candidate, value);
        }
        if (candidate.length - value.length > 1) {
            return false;
        }

        let firstDifference = 0;
        while (firstDifference < value.length && value[firstDifference] === candidate[firstDifference]) {
            firstDifference++;
        }
        if (value.length !== candidate.length) {
            return value.slice(firstDifference) === candidate.slice(firstDifference + 1);
        }
        if (firstDifference === value.length) {
            return false;
        }
        if (value.slice(firstDifference + 1) === candidate.slice(firstDifference + 1)) {
            return true;
        }

        return value[firstDifference] === candidate[firstDifference + 1]
            && value[firstDifference + 1] === candidate[firstDifference]
            && value.slice(firstDifference + 2) === candidate.slice(firstDifference + 2);
    }

    function suggestDomain(domain) {
        const [label, suffix, ...rest] = domain.split('.');
        if (domains.has(domain) || !suffix || rest.length !== 0) {
            return null;
        }

        const matches = [...domains].filter((knownDomain) => {
            const [knownLabel, knownSuffix] = knownDomain.split('.');
            return suffix === knownSuffix && differsByOneEdit(label, knownLabel);
        });

        return matches.length === 1 ? matches[0] : null;
    }

    function clearSuggestion(field) {
        suggestions.get(field)?.button.remove();
        suggestions.delete(field);
    }

    function showSuggestion({ target: field }) {
        if (!(field instanceof HTMLInputElement) || !field.matches(fields)) {
            return;
        }

        if (field.multiple || field.matches(':disabled, [readonly]') || field.validity.typeMismatch || !field.value) {
            clearSuggestion(field);
            return;
        }

        const originalAddress = field.value;
        if (suggestions.get(field)?.originalAddress === originalAddress) {
            return;
        }
        clearSuggestion(field);
        const at = originalAddress.lastIndexOf('@');
        const suggestedDomain = suggestDomain(originalAddress.slice(at + 1).toLowerCase());
        if (suggestedDomain === null) {
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'button2';
        button.textContent = window.Translator?.translate('Use @%s', suggestedDomain) ?? `Use @${suggestedDomain}`;
        button.addEventListener('click', () => {
            clearSuggestion(field);
            if (!field.isConnected || field.value !== originalAddress || field.matches(':disabled, [readonly]')) {
                return;
            }

            field.value = `${originalAddress.slice(0, at + 1)}${suggestedDomain}`;
            field.focus();
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
        field.after(button);
        suggestions.set(field, { button, originalAddress });
    }

    document.addEventListener('input', showSuggestion);
    document.addEventListener('change', showSuggestion);
})();
