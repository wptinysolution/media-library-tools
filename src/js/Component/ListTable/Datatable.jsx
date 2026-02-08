import React, { useState } from "react";

import { columns, defaultBulkSubmitData } from '@/js/Utils/UtilData';

import Loader from "@/js/Utils/Loader";

import TheHeader from "@/js/Component/ListTable/TheHeader";

import { useStateValue } from "@/js/Utils/StateProvider";

import BulkModal from "@/js/Component/ListTable/BulkModal";

import BulkModalForCSV from "@/js/Component/ListTable/BulkModalForCSV";

import MainHeader from "@/js/Component/MainHeader";

import * as Types from "@/js/Utils/actionType";

export default function Datatable() {

    const [stateValue, dispatch] = useStateValue();
    const [jumpPage, setJumpPage] = useState('');

    const handlePagination = ( current ) => {
        dispatch({
            type: Types.GET_MEDIA_LIST,
            mediaData: {
                ...stateValue.mediaData,
                isLoading: true,
                postQuery : {
                    ...stateValue.mediaData.postQuery,
                    paged : current
                }
            },
        });
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: defaultBulkSubmitData,
        });
    }

    const thecolumn = columns();
    const tablecolumn = thecolumn.filter( ( currentValue) => {
        if( ! stateValue.options.media_table_column || 'CheckboxID' === currentValue.key ){
            return true;
        }
        return stateValue.options.media_table_column.includes( `${currentValue.key}` );
    } );

    const renderModal = () => {
        if (stateValue.bulkSubmitData.isModalOpen) {
            return <BulkModal />
        } else if (stateValue.bulkExport.isModalOpen) {
            return <BulkModalForCSV />
        }
        return null;
    };

    // Pagination calculations
    const totalPosts = stateValue.mediaData.total_post || 0;
    const postsPerPage = stateValue.mediaData.posts_per_page || 20;
    const currentPage = stateValue.mediaData.paged || 1;
    const totalPages = Math.ceil(totalPosts / postsPerPage);
    const rangeStart = totalPosts === 0 ? 0 : (currentPage - 1) * postsPerPage + 1;
    const rangeEnd = Math.min(currentPage * postsPerPage, totalPosts);

    const getPageNumbers = () => {
        const pages = [];
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

    const handleJumpPage = (e) => {
        if (e.key === 'Enter') {
            const page = parseInt(jumpPage);
            if (page >= 1 && page <= totalPages) {
                handlePagination(page);
            }
            setJumpPage('');
        }
    };

    const posts = stateValue.mediaData.posts || [];

    return (
        <>
            <MainHeader/>
            <div className="min-h-screen bg-gray-50">
                <TheHeader/>
                { stateValue.generalData.isLoading || stateValue.mediaData.isLoading ? <Loader/> :
                    <>
                    <div>
                        {/* Table */}
                        <div className="overflow-x-auto" style={{ minWidth: '1300px' }}>
                            <table className="w-full border-collapse bg-white text-sm">
                                <thead>
                                    <tr className="bg-gray-50 border-b border-gray-200">
                                        {tablecolumn.map((col) => (
                                            <th
                                                key={col.key + (col.dataIndex || '')}
                                                className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap"
                                                style={{
                                                    width: col.width || 'auto',
                                                    textAlign: col.align === 'center' ? 'center' : 'left',
                                                }}
                                            >
                                                {col.title}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {posts.length > 0 ? posts.map((record, rowIndex) => (
                                        <tr
                                            key={record.ID}
                                            className="border-b border-gray-100 hover:bg-gray-50 transition-colors"
                                        >
                                            {tablecolumn.map((col) => {
                                                const cellData = record[col.dataIndex];
                                                return (
                                                    <td
                                                        key={col.key + (col.dataIndex || '') + rowIndex}
                                                        className="px-4 py-3 text-sm text-gray-700"
                                                        style={{
                                                            width: col.width || 'auto',
                                                            textAlign: col.align === 'center' ? 'center' : 'left',
                                                            verticalAlign: col.align === 'top' ? 'top' : 'middle',
                                                        }}
                                                    >
                                                        {col.render
                                                            ? col.render(cellData, record, rowIndex)
                                                            : cellData
                                                        }
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan={tablecolumn.length} className="px-4 py-12 text-center text-gray-500">
                                                No data available
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {totalPages > 0 && (
                            <div className="flex items-center justify-between px-6 py-5 bg-white border-t border-gray-200">
                                <span className="text-sm text-gray-600">
                                    {rangeStart}-{rangeEnd} of {totalPosts} items
                                </span>
                                <div className="flex items-center gap-1">
                                    {/* Previous */}
                                    <button
                                        type="button"
                                        className="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-100 cursor-pointer transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                        disabled={currentPage <= 1}
                                        onClick={() => handlePagination(currentPage - 1)}
                                    >
                                        &lt;
                                    </button>

                                    {/* First page + ellipsis */}
                                    {getPageNumbers()[0] > 1 && (
                                        <>
                                            <button
                                                type="button"
                                                className="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-100 cursor-pointer transition-colors"
                                                onClick={() => handlePagination(1)}
                                            >
                                                1
                                            </button>
                                            {getPageNumbers()[0] > 2 && (
                                                <span className="px-2 text-gray-400">...</span>
                                            )}
                                        </>
                                    )}

                                    {/* Page numbers */}
                                    {getPageNumbers().map((page) => (
                                        <button
                                            key={page}
                                            type="button"
                                            className={`px-3 py-1.5 text-sm rounded-md cursor-pointer transition-colors ${
                                                page === currentPage
                                                    ? 'bg-blue-600 text-white border border-blue-600'
                                                    : 'border border-gray-300 hover:bg-gray-100'
                                            }`}
                                            onClick={() => handlePagination(page)}
                                        >
                                            {page}
                                        </button>
                                    ))}

                                    {/* Last page + ellipsis */}
                                    {getPageNumbers()[getPageNumbers().length - 1] < totalPages && (
                                        <>
                                            {getPageNumbers()[getPageNumbers().length - 1] < totalPages - 1 && (
                                                <span className="px-2 text-gray-400">...</span>
                                            )}
                                            <button
                                                type="button"
                                                className="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-100 cursor-pointer transition-colors"
                                                onClick={() => handlePagination(totalPages)}
                                            >
                                                {totalPages}
                                            </button>
                                        </>
                                    )}

                                    {/* Next */}
                                    <button
                                        type="button"
                                        className="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-100 cursor-pointer transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                        disabled={currentPage >= totalPages}
                                        onClick={() => handlePagination(currentPage + 1)}
                                    >
                                        &gt;
                                    </button>

                                    {/* Quick jump */}
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
                        )}
                    </div>
                    { renderModal() }
                    </>
                }
            </div>
        </>
    );
}
