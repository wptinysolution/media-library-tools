import React, { useEffect } from 'react';
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

    const onCheckbox = (e: React.ChangeEvent<HTMLInputElement>, value: string) => {
        let val = e.target.checked ? [...checkedList, value] : checkedList.filter(i => i !== value);
        val = [...new Set(val)];
        setOptions({ deregistered_image_sizes: val });
    };

    useEffect(() => {
        getTheSizes();
    }, [saveType]);

    return (
        <div className="flex items-start gap-8">
            <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-[200px]">
                Disable Registered Image Size:
            </label>
            <div className="flex-1 space-y-4">
                <h5 className="text-base! font-medium text-gray-900 mt-0!">Image Size</h5>
                <div className="flex flex-col gap-3">
                    {Object.keys(sizes).map((item, index) => {
                        if (sizes[item].length === 0) return null;
                        return (
                            <label key={index} className="inline-flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                    checked={checkedList.includes(item)}
                                    onChange={(e) => onCheckbox(e, item)}
                                />
                                <span className="text-base text-gray-900">{sizes[item]}</span>
                            </label>
                        );
                    })}
                </div>
                <p className="text-sm text-red-600">
                    The selected image size will be deregistered and the image will not be generated. This will save server space. Please select only unnecessary image sizes.
                </p>
            </div>
        </div>
    );
}

export default DisableSize;
