import React from 'react';

export interface ColumnDef<T = Record<string, unknown>> {
    title: React.ReactNode;
    key: string;
    dataIndex: string;
    width?: string;
    align?: string;
    minWidth?: number;
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
        <div className="overflow-x-auto" style={{ minWidth }}>
            <table className="w-full border-collapse bg-white text-sm">
                <thead>
                    <tr className="bg-gray-50 border-b border-gray-200">
                        {columns.map((col) => (
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
                    {data.length > 0 ? data.map((record, rowIndex) => (
                        <tr
                            key={getRowKey(record, rowIndex)}
                            className="border-b border-gray-100 hover:bg-gray-50 transition-colors"
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
                                            verticalAlign: col.align === 'top' ? 'top' : 'middle',
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
                            <td colSpan={columns.length} className="px-4 py-12 text-center text-gray-500">
                                {emptyText}
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
