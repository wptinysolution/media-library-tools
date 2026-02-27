import { useState, useEffect } from "react";

export function useWpMenuWidth(): number {
    const [width, setWidth] = useState(() => {
        const wrap = document.getElementById('adminmenuwrap');
        return wrap ? wrap.offsetWidth : 160;
    });

    useEffect(() => {
        const wrap = document.getElementById('adminmenuwrap');
        if (!wrap) return;
        const observer = new ResizeObserver(() => setWidth(wrap.offsetWidth));
        observer.observe(wrap);
        return () => observer.disconnect();
    }, []);

    return width;
}

/**
 * Returns [debouncedValue, rawInputValue, setter].
 *
 * - debouncedValue  — null or the settled search string (use for API calls)
 * - rawInputValue   — the live input value (bind to the input's value prop)
 * - setter          — call on every keystroke (or pass '' to clear)
 */
export function useSearchDebounce(): [string | null, string, (value: string) => void] {
    const [debounced, setDebounced] = useState<string | null>(null);
    const [raw, setRaw] = useState('');
    const delay = 500;

    useEffect(() => {
        const timer = setTimeout(() => setDebounced(raw || null), delay);
        return () => clearTimeout(timer);
    }, [raw]);

    return [debounced, raw, setRaw];
}
