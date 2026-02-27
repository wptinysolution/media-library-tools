import type React from 'react';

export interface ColumnDef<T = Record<string, unknown>> {
    title: React.ReactNode;
    key: string;
    dataIndex: string;
    width?: string;
    minWidth?: number;
    align?: 'left' | 'center' | 'top';
    fixed?: boolean;
    render?: (value: unknown, record: T, index: number) => React.ReactNode;
}

interface DataTableProps<T extends Record<string, unknown>> {
    columns: ColumnDef<T>[];
    data: T[];
    rowKey: string | ((record: T, index: number) => string | number);
    minWidth?: string;
    emptyText?: string;
    loading?: boolean;
    loadingRows?: number;
}

function stickyStyle(fixed: boolean | undefined, isOdd: boolean): React.CSSProperties {
    if (!fixed) return {};
    return {
        position: 'sticky',
        left: 0,
        zIndex: 1,
        background: isOdd ? '#f9fafb' : '#ffffff',
        boxShadow: '2px 0 4px -1px rgba(0,0,0,0.08)',
    };
}

function textAlign(align?: string): 'left' | 'center' {
    return align === 'center' ? 'center' : 'left';
}

function verticalAlign(align?: string): 'top' | 'middle' {
    return align === 'top' ? 'top' : 'middle';
}

export default function DataTable<T extends Record<string, unknown>>({
    columns, data, rowKey,
    minWidth = '1300px',
    emptyText = 'No data available',
    loading = false,
    loadingRows = 8,
}: DataTableProps<T>) {

    const getRowKey = (record: T, index: number): string | number => {
        if (typeof rowKey === 'function') return rowKey(record, index);
        if (typeof rowKey === 'string') return record[rowKey] as string | number;
        return index;
    };

    return (
        <div className="bg-white border border-gray-200 rounded-t-lg overflow-hidden shadow-sm">
            <div className="overflow-x-auto">
                <table className="w-full border-collapse text-sm" style={{ minWidth }}>
                    <thead>
                        <tr className="bg-gray-50 border-b-2 border-gray-200">
                            {columns.map((col) => (
                                <th
                                    key={col.key + col.dataIndex}
                                    className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap"
                                    style={{
                                        width: col.width || 'auto',
                                        minWidth: col.minWidth,
                                        textAlign: textAlign(col.align),
                                        ...(col.fixed ? {
                                            position: 'sticky',
                                            left: 0,
                                            zIndex: 3,
                                            background: '#f9fafb',
                                            boxShadow: '2px 0 4px -1px rgba(0,0,0,0.08)',
                                        } : {}),
                                    }}
                                >
                                    {col.title}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {loading ? (
                            Array.from({ length: loadingRows }).map((_, rowIndex) => {
                                const isOdd = rowIndex % 2 !== 0;
                                return (
                                    <tr key={rowIndex} className={`border-b border-gray-100 ${isOdd ? 'bg-gray-50/50' : 'bg-white'}`}>
                                        {columns.map((col, colIndex) => (
                                            <td key={col.key + colIndex} className="px-4 py-3.5">
                                                <div
                                                    className="h-4 bg-gray-200 rounded-md animate-pulse"
                                                    style={{ width: `${55 + ((rowIndex + colIndex) % 4) * 12}%` }}
                                                />
                                            </td>
                                        ))}
                                    </tr>
                                );
                            })
                        ) : data.length > 0 ? (
                            data.map((record, rowIndex) => {
                                const isOdd = rowIndex % 2 !== 0;
                                return (
                                    <tr
                                        key={getRowKey(record, rowIndex)}
                                        className={`border-b border-gray-100 hover:bg-blue-50/40 transition-colors ${isOdd ? 'bg-gray-50/50' : 'bg-white'}`}
                                    >
                                        {columns.map((col) => {
                                            const cellData = record[col.dataIndex];
                                            return (
                                                <td
                                                    key={col.key + col.dataIndex + rowIndex}
                                                    className="px-4 py-3 text-sm text-gray-700"
                                                    style={{
                                                        width: col.width || 'auto',
                                                        minWidth: col.minWidth,
                                                        textAlign: textAlign(col.align),
                                                        verticalAlign: verticalAlign(col.align),
                                                        ...stickyStyle(col.fixed, isOdd),
                                                    }}
                                                >
                                                    {col.render
                                                        ? col.render(cellData, record, rowIndex)
                                                        : (cellData as React.ReactNode)
                                                    }
                                                </td>
                                            );
                                        })}
                                    </tr>
                                );
                            })
                        ) : (
                            <tr>
                                <td colSpan={columns.length} className="px-4 py-16 text-center">
                                    <div className="flex flex-col items-center gap-3 text-gray-400">
                                        <svg className="w-12 h-12 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                        <span className="text-sm font-medium">{emptyText}</span>
                                    </div>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
