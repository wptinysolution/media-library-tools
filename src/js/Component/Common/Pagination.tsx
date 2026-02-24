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

    return (
        <div className="flex items-center justify-between px-6 py-5 bg-white border-t border-gray-200">
            <span className="text-sm text-gray-600">
                {rangeStart}-{rangeEnd} of {totalPosts} items
            </span>
            <div className="flex items-center gap-1">
                <button
                    type="button"
                    className="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-100 cursor-pointer transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                    disabled={currentPage <= 1}
                    onClick={() => onPageChange(currentPage - 1)}
                >
                    &lt;
                </button>

                {pageNumbers[0] > 1 && (
                    <>
                        <button
                            type="button"
                            className="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-100 cursor-pointer transition-colors"
                            onClick={() => onPageChange(1)}
                        >
                            1
                        </button>
                        {pageNumbers[0] > 2 && <span className="px-2 text-gray-400">...</span>}
                    </>
                )}

                {pageNumbers.map((page) => (
                    <button
                        key={page}
                        type="button"
                        className={`px-3 py-1.5 text-sm rounded-md cursor-pointer transition-colors ${
                            page === currentPage
                                ? 'bg-blue-600 text-white border border-blue-600'
                                : 'border border-gray-300 hover:bg-gray-100'
                        }`}
                        onClick={() => onPageChange(page)}
                    >
                        {page}
                    </button>
                ))}

                {pageNumbers[pageNumbers.length - 1] < totalPages && (
                    <>
                        {pageNumbers[pageNumbers.length - 1] < totalPages - 1 && (
                            <span className="px-2 text-gray-400">...</span>
                        )}
                        <button
                            type="button"
                            className="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-100 cursor-pointer transition-colors"
                            onClick={() => onPageChange(totalPages)}
                        >
                            {totalPages}
                        </button>
                    </>
                )}

                <button
                    type="button"
                    className="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-100 cursor-pointer transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                    disabled={currentPage >= totalPages}
                    onClick={() => onPageChange(currentPage + 1)}
                >
                    &gt;
                </button>

                <span className="ml-3 text-sm text-gray-600">Go to</span>
                <input
                    type="number"
                    className="w-14 px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    min={1}
                    max={totalPages}
                    value={jumpPage}
                    onChange={(e) => setJumpPage(e.target.value)}
                    onKeyDown={handleJumpPage}
                />
            </div>
        </div>
    );
}
