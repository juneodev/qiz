import { onMounted, ref } from 'vue';

type Appearance = 'dark';

export function updateTheme(_value: Appearance) {
    if (typeof window === 'undefined') {
        return;
    }

    // Only one theme is supported: dark
    void _value;
    document.documentElement.classList.add('dark');
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};



export function initializeTheme() {
    if (typeof window === 'undefined') {
        return;
    }

    // Single dark theme only
    updateTheme('dark');
}

const appearance = ref<Appearance>('dark');

export function useAppearance() {
    onMounted(() => {
        appearance.value = 'dark';
        updateTheme('dark');
        localStorage.setItem('appearance', 'dark');
        setCookie('appearance', 'dark');
    });

    function updateAppearance(_value: Appearance) {
        // Only dark theme is supported
        appearance.value = 'dark';

        // Store in localStorage for client-side persistence...
        localStorage.setItem('appearance', 'dark');

        // Store in cookie for SSR...
        setCookie('appearance', 'dark');

        // prevent unused param lint
        void _value;

        updateTheme('dark');
    }

    return {
        appearance,
        updateAppearance,
    };
}
