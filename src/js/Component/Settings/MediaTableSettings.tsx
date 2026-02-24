import React from 'react';
import { useStore } from '@/js/Utils/store';
import { columnList } from '@/js/Utils/UtilData';

export default function MediaTableSettings() {
    const { options, setOptions } = useStore();

    const plainOptions = columnList.map((currentValue) => currentValue.key);
    const isCheckedDiff = Object.keys(plainOptions).length === Object.keys(options.media_table_column).length;

    const onChangeColumnList = (key: string) => {
        const currentColumn = options.media_table_column;
        const newColumn = currentColumn.includes(key)
            ? currentColumn.filter(item => item !== key)
            : [...currentColumn, key];
        setOptions({ media_table_column: newColumn });
    };

    const onCheckAllColumn = (e: React.ChangeEvent<HTMLInputElement>) => {
        setOptions({ media_table_column: e.target.checked ? plainOptions : [] });
    };

    const onChangeOthersFileList = (value: string) => {
        const currentList = options.others_file_support || [];
        const newList = currentList.includes(value)
            ? currentList.filter(item => item !== value)
            : [...currentList, value];
        setOptions({ others_file_support: newList });
    };

    return (
        <>
            <div className="p-6 space-y-6">
                <div className="flex items-start gap-8">
                    <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-[200px]">
                        Media Table Column:
                    </label>
                    <div className="flex-1 space-y-4">
                        <label className="inline-flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                onChange={onCheckAllColumn}
                                checked={isCheckedDiff}
                            />
                            <span className="text-base text-gray-900">Check all</span>
                        </label>
                        <div className="flex flex-wrap gap-x-6 gap-y-3">
                            {columnList.map((column) => (
                                <label key={column.key} className="inline-flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                        checked={options.media_table_column.includes(column.key)}
                                        onChange={() => onChangeColumnList(column.key)}
                                    />
                                    <span className="text-base text-gray-900">{column.title}</span>
                                </label>
                            ))}
                        </div>
                    </div>
                </div>
            </div>

            <div className="p-6 space-y-6 border-t border-gray-200">
                <div className="flex items-start gap-8">
                    <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-[200px]">
                        Others File Support:
                    </label>
                    <div className="flex-1 space-y-2">
                        <label className="inline-flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                checked={(options.others_file_support || []).includes('svg')}
                                onChange={() => onChangeOthersFileList('svg')}
                            />
                            <span className="text-base text-gray-900">SVG</span>
                        </label>
                        <p className="text-sm text-gray-500">Svg And Others File Upload.</p>
                    </div>
                </div>
            </div>
        </>
    );
}
