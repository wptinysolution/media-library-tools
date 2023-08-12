import React, { useState, useEffect } from "react";
export function useSearchDebounce() {
    const [search, setSearch] = useState(null);
    const [searchQuery, setSearchQuery] = useState(null);
    const delay = 500;
    useEffect(() => {
        const delayFn = setTimeout(() => setSearch(searchQuery), delay);
        return () => clearTimeout(delayFn);
    }, [searchQuery, delay]);

    return [search, setSearchQuery];
}

