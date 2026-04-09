import { useStore } from '@/js/Utils/store';

export default function MediaTableSettings() {
    const { options, setOptions } = useStore();

    const onChangeOthersFileList = (value: string) => {
        const currentList = options.others_file_support || [];
        const newList = currentList.includes(value)
            ? currentList.filter(item => item !== value)
            : [...currentList, value];
        setOptions({ others_file_support: newList });
    };

    return (
        <>
            <div className="p-6 space-y-6 border-t border-gray-200">
                <div className="flex items-start gap-8">
                    <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-50">
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
                        <p className="text-sm mt-0! text-gray-500">Svg And Others File Upload.</p>
                    </div>
                </div>
            </div>
        </>
    );
}
