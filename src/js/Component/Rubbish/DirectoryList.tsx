
export const trimPath = (fullPath: string): string => {
    const marker = 'wp-content/';
    const idx = fullPath.indexOf(marker);
    return idx !== -1 ? fullPath.slice(idx) : fullPath;
};
