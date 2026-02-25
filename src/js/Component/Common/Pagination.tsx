import React, { useState } from 'react';

interface PaginationProps {
    currentPage: number;
    totalPages: number;
    totalPosts: number;
    postsPerPage: number;
    onPageChange: (page: number) => void;
}

export default function Pagination({ currentPage, totalPages, totalPosts, postsPerPage, onPageChange }: PaginationProps) {
    const [jumpPage, setJumpPage] = useState('');

    if (totalPages <= 0) return null;

    const rangeStart = totalPosts === 0 ? 0 : (currentPage - 1) * postsPerPage + 1;
    const rangeEnd = Math.min(currentPage * postsPerPage, totalPosts);

    const getPageNumbers = (): number[] => {
        const pages: number[] = [];
        const maxVisible = 5;
        let start = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let end = Math.min(totalPages, start + maxVisible - 1);
        if (end - start + 1 < maxVisible) {
            start = Math.max(1, end - maxVisible + 1);
        }
        for (let i = start; i <= end; i++) {
            pages.push(i);
        }
        return pages;
    };

    const handleJumpPage = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            const page = parseInt(jumpPage);
            if (page >= 1 && page <= totalPages) {
                onPageChange(page);
            }
            setJumpPage('');
        }
    };

    const pageNumbers = getPageNumbers();

    const navBtnBase = 'inline-flex items-center justify-center w-8 h-8 text-sm border rounded-md transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed';
    const navBtnDefault = 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-gray-400';
    const navBtnActive = 'border-blue-600 bg-blue-600 text-white font-semibold shadow-sm';

    return (
        <div className="flex items-center justify-between px-6 py-4 bg-white border-t border-gray-200">
            <span className="text-sm text-gray-500">
                Showing <span className="font-medium text-gray-700">{rangeStart}–{rangeEnd}</span> of <span className="font-medium text-gray-700">{totalPosts}</span> items
            </span>

            <div className="flex items-center gap-1">
                <button
                    type="button"
                    className={`${navBtnBase} ${navBtnDefault}`}
                    disabled={currentPage <= 1}
                    onClick={() => onPageChange(currentPage - 1)}
                    title="Previous page"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                {pageNumbers[0] > 1 && (
                    <>
                        <button
                            type="button"
                            className={`${navBtnBase} ${navBtnDefault}`}
                            onClick={() => onPageChange(1)}
                        >
                            1
                        </button>
                        {pageNumbers[0] > 2 && <span className="px-1 text-gray-400 text-sm">…</span>}
                    </>
                )}

                {pageNumbers.map((page) => (
                    <button
                        key={page}
                        type="button"
                        className={`${navBtnBase} ${page === currentPage ? navBtnActive : navBtnDefault}`}
                        onClick={() => onPageChange(page)}
                    >
                        {page}
                    </button>
                ))}

                {pageNumbers[pageNumbers.length - 1] < totalPages && (
                    <>
                        {pageNumbers[pageNumbers.length - 1] < totalPages - 1 && (
                            <span className="px-1 text-gray-400 text-sm">…</span>
                        )}
                        <button
                            type="button"
                            className={`${navBtnBase} ${navBtnDefault}`}
                            onClick={() => onPageChange(totalPages)}
                        >
                            {totalPages}
                        </button>
                    </>
                )}

                <button
                    type="button"
                    className={`${navBtnBase} ${navBtnDefault}`}
                    disabled={currentPage >= totalPages}
                    onClick={() => onPageChange(currentPage + 1)}
                    title="Next page"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <span className="ml-3 text-sm text-gray-500">Go to</span>
                <input
                    type="number"
                    className="w-14 px-2 py-1.5 text-sm text-center border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    min={1}
                    max={totalPages}
                    value={jumpPage}
                    placeholder="–"
                    onChange={(e) => setJumpPage(e.target.value)}
                    onKeyDown={handleJumpPage}
                />
            </div>
        </div>
    );
}
