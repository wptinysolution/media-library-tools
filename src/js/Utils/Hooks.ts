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

export function useSearchDebounce(): [string | null, (value: string) => void] {
    const [search, setSearch] = useState<string | null>(null);
    const [searchQuery, setSearchQuery] = useState<string | null>(null);
    const delay = 500;

    useEffect(() => {
        const delayFn = setTimeout(() => setSearch(searchQuery), delay);
        return () => clearTimeout(delayFn);
    }, [searchQuery, delay]);

    return [search, setSearchQuery as (value: string) => void];
}
