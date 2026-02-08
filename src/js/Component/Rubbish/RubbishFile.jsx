import React, { useEffect, useState } from "react";

import { useStateValue } from "@/js/Utils/StateProvider";

import RubbishHeader from "./RubbishHeader";

import Loader from "@/js/Utils/Loader";

import * as Types from "@/js/Utils/actionType";

import { RubbishFileColumns } from "@/js/Utils/UtilData";

import { getRubbishFile } from "@/js/Utils/Data";

import DirectoryModal from "./DirectoryModal";

import RubbishNotice from "./RubbishNotice";

import MainHeader from "@/js/Component/MainHeader";

function RubbishFile() {

    const [stateValue, dispatch] = useStateValue();
    const [jumpPage, setJumpPage] = useState('');

    const getTheRubbishFile = async () => {
        const rubbishFile = await getRubbishFile(stateValue.rubbishMedia.postQuery);
        await dispatch({
            type: Types.RUBBISH_MEDIA,
            rubbishMedia: {
                ...stateValue.rubbishMedia,
                isLoading: false,
                mediaFile: rubbishFile.mediaFile,
                paged: rubbishFile.paged,
                totalPost: rubbishFile.totalPost,
                postsPerPage: rubbishFile.postsPerPage
            }
        });
        dispatch({
            type: Types.BALK_RUBBISH,
            bulkRubbishData: {
                ...stateValue.bulkRubbishData,
                bulkChecked: false,
                files: [],
                ids: [],
            },
        });
        console.log('getTheRubbishFile');
    };

    const handlePagination = (current) => {
        dispatch({
            type: Types.RUBBISH_MEDIA,
            rubbishMedia: {
                ...stateValue.rubbishMedia,
                postQuery: {
                    ...stateValue.rubbishMedia.postQuery,
                    paged: current,
                    isQueryUpdate: true
                }
            },
        });
    };

    const rubbishColumns = RubbishFileColumns();

    useEffect(() => {
        getTheRubbishFile();
    }, [stateValue.rubbishMedia.postQuery, stateValue.saveType]);

    // Pagination calculations
    const totalPosts = stateValue.rubbishMedia.totalPost || 0;
    const postsPerPage = stateValue.rubbishMedia.postsPerPage || 20;
    const currentPage = stateValue.rubbishMedia.paged || 1;
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

    const posts = stateValue.rubbishMedia.mediaFile || [];

    return (
        <>
            <MainHeader/>
            <div className="min-h-screen bg-gray-50">
                <RubbishHeader />
                {stateValue.rubbishMedia.isLoading ? <Loader/> : (
                    <>
                        <div className="overflow-x-auto" style={{ minWidth: '1300px' }}>
                            <table className="w-full border-collapse bg-white text-sm">
                                <thead>
                                    <tr className="bg-gray-50 border-b border-gray-200">
                                        {rubbishColumns.map((col) => (
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
                                            key={(Math.random() + 1).toString(36).substring(7)}
                                            className="border-b border-gray-100 hover:bg-gray-50 transition-colors"
                                        >
                                            {rubbishColumns.map((col) => {
                                                const cellData = record[col.dataIndex];
                                                return (
                                                    <td
                                                        key={col.key + (col.dataIndex || '') + rowIndex}
                                                        className="px-4 py-3 text-sm text-gray-700"
                                                        style={{
                                                            width: col.width || 'auto',
                                                            textAlign: col.align === 'center' ? 'center' : 'left',
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
                                            <td colSpan={rubbishColumns.length} className="px-4 py-12 text-center text-gray-500">
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
                                    <button
                                        type="button"
                                        className="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-100 cursor-pointer transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                        disabled={currentPage <= 1}
                                        onClick={() => handlePagination(currentPage - 1)}
                                    >
                                        &lt;
                                    </button>
                                    {getPageNumbers()[0] > 1 && (
                                        <>
                                            <button type="button" className="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-100 cursor-pointer transition-colors" onClick={() => handlePagination(1)}>1</button>
                                            {getPageNumbers()[0] > 2 && <span className="px-2 text-gray-400">...</span>}
                                        </>
                                    )}
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
                                    {getPageNumbers()[getPageNumbers().length - 1] < totalPages && (
                                        <>
                                            {getPageNumbers()[getPageNumbers().length - 1] < totalPages - 1 && <span className="px-2 text-gray-400">...</span>}
                                            <button type="button" className="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-100 cursor-pointer transition-colors" onClick={() => handlePagination(totalPages)}>{totalPages}</button>
                                        </>
                                    )}
                                    <button
                                        type="button"
                                        className="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-100 cursor-pointer transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                        disabled={currentPage >= totalPages}
                                        onClick={() => handlePagination(currentPage + 1)}
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
                        )}
                    </>
                )}
                <DirectoryModal />
                <RubbishNotice/>
            </div>
        </>
    );
}
export default RubbishFile;
