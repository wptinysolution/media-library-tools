import React from 'react';

export interface ColumnDef<T = Record<string, unknown>> {
    title: React.ReactNode;
    key: string;
    dataIndex: string;
    width?: string;
    align?: string;
    minWidth?: number;
    fixed?: boolean;
    render?: (value: unknown, record: T, index: number) => React.ReactNode;
}

interface DataTableProps<T extends Record<string, unknown>> {
    columns: ColumnDef<T>[];
    data: T[];
    rowKey: string | ((record: T, index: number) => string | number);
    minWidth?: string;
    emptyText?: string;
}

export default function DataTable<T extends Record<string, unknown>>({ columns, data, rowKey, minWidth = '1300px', emptyText = 'No data available' }: DataTableProps<T>) {

    const getRowKey = (record: T, index: number): string | number => {
        if (typeof rowKey === 'function') return rowKey(record, index);
        if (typeof rowKey === 'string') return record[rowKey] as string | number;
        return index;
    };

    return (
        <div className="bg-white shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
                <table className="w-full border-collapse text-sm" style={{ minWidth }}>
                    <thead>
                        <tr className="bg-gray-50 border border-gray-200">
                            {columns.map((col) => (
                                <th
                                    key={col.key + (col.dataIndex || '')}
                                    className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap"
                                    style={{
                                        width: col.width || 'auto',
                                        textAlign: col.align === 'center' ? 'center' : 'left',
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
                        {data.length > 0 ? data.map((record, rowIndex) => (
                            <tr
                                key={getRowKey(record, rowIndex)}
                                className={`border-b border-gray-100 hover:bg-blue-50/30 transition-colors ${rowIndex % 2 !== 0 ? 'bg-gray-50/50' : 'bg-white'}`}
                            >
                                {columns.map((col) => {
                                    const cellData = record[col.dataIndex];
                                    return (
                                        <td
                                            key={col.key + (col.dataIndex || '') + rowIndex}
                                            className="px-4 py-3 text-sm text-gray-700"
                                            style={{
                                                width: col.width || 'auto',
                                                textAlign: col.align === 'center' ? 'center' : 'left',
                                                verticalAlign: 'middle',
                                                ...(col.fixed ? {
                                                    position: 'sticky',
                                                    left: 0,
                                                    zIndex: 1,
                                                    background: rowIndex % 2 !== 0 ? '#f9fafb' : '#ffffff',
                                                    boxShadow: '2px 0 4px -1px rgba(0,0,0,0.08)',
                                                } : {}),
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
                        )) : (
                            <tr>
                                <td colSpan={columns.length} className="px-4 py-16 text-center">
                                    <div className="flex flex-col items-center gap-3 text-gray-400">
                                        <svg className="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
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