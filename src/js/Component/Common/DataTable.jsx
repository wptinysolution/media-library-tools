import React from "react";

export default function DataTable({ columns, data, rowKey, minWidth = '1300px', emptyText = 'No data available' }) {

    const getRowKey = (record, index) => {
        if (typeof rowKey === 'function') return rowKey(record, index);
        if (typeof rowKey === 'string') return record[rowKey];
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
                                            : cellData
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
