import { useEffect } from 'react';
import type { ChangeEvent } from 'react';
import { useStore } from "@/js/Utils/store";
import { getRegisteredImageSizes } from "@/js/Utils/Data";

function DisableSize() {
    const { options, setOptions, generalData, setGeneralData, saveType } = useStore();

    const checkedList = options?.deregistered_image_sizes || [];
    const sizes = (generalData?.allImageSizes || {}) as Record<string, string>;

    const getTheSizes = async () => {
        const response = await getRegisteredImageSizes() as { data: Record<string, string> };
        setGeneralData({ allImageSizes: response.data });
    };

    const onCheckbox = (e: ChangeEvent<HTMLInputElement>, value: string) => {
        let val = e.target.checked ? [...checkedList, value] : checkedList.filter(i => i !== value);
        val = [...new Set(val)];
        setOptions({ deregistered_image_sizes: val });
    };

    useEffect(() => {
        getTheSizes();
    }, [saveType]);

    const sizeEntries = Object.keys(sizes).filter(item => sizes[item].length > 0);

    return (
        <div className="space-y-4">
            {sizeEntries.length > 0 ? (
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    {sizeEntries.map((item, index) => {
                        const isChecked = checkedList.includes(item);
                        return (
                            <label
                                key={index}
                                className={`flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${
                                    isChecked
                                        ? 'border-red-300 bg-red-50'
                                        : 'border-gray-200 bg-gray-50 hover:bg-gray-100'
                                }`}
                            >
                                <input
                                    type="checkbox"
                                    className="w-4 h-4 rounded border-gray-300 text-red-500 focus:ring-red-500 cursor-pointer shrink-0"
                                    checked={isChecked}
                                    onChange={(e) => onCheckbox(e, item)}
                                />
                                <span className={`text-sm font-medium truncate ${isChecked ? 'text-red-700 line-through' : 'text-gray-900'}`}>
                                    {sizes[item]}
                                </span>
                            </label>
                        );
                    })}
                </div>
            ) : (
                <p className="text-sm text-gray-500">No registered image sizes found.</p>
            )}

            <div className="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                <svg className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <p className="text-sm text-amber-800 m-0!">
                    Disabled sizes will no longer be generated on new uploads. Only disable sizes you genuinely don't need.
                </p>
            </div>
        </div>
    );
}

export default DisableSize;
