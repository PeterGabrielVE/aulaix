import { nextTick, onBeforeUnmount, watch } from 'vue';

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * Keeps the keyboard focus inside `container` while `active` is true, and
 * gives it back to whatever had it when the trap closes (WCAG 2.4.3,
 * SPEC-013 NAV-NF04). Escape calls `onEscape`.
 */
export function useFocusTrap(container, active, onEscape) {
    let returnTo = null;

    const focusables = () => [...(container.value?.querySelectorAll(FOCUSABLE) ?? [])];

    const onKeydown = (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            onEscape();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const items = focusables();

        if (items.length === 0) {
            event.preventDefault();
            return;
        }

        const first = items[0];
        const last = items[items.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    watch(active, async (isActive) => {
        if (isActive) {
            returnTo = document.activeElement;
            document.addEventListener('keydown', onKeydown);
            await nextTick();
            focusables()[0]?.focus();
        } else {
            document.removeEventListener('keydown', onKeydown);
            returnTo?.focus?.();
            returnTo = null;
        }
    });

    onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
}
