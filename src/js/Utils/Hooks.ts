import { useState, useEffect } from "react";

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
