import React from 'react';
import { useStore } from "@/js/Utils/store";
import { CopyToClipboard } from "@/js/Component/CopyToClipboard";

const defaultSize = [
    {
        sizeKey: '',
        width: '',
        height: '',
        hardCrop: true,
    }
];

function RegisterSize() {
    const { options, setOptions, setGeneralData } = useStore();
    let sizes = options?.custom_image_sizes || defaultSize;
    sizes = sizes.length > 0 ? sizes : defaultSize;

    const registerImageSize = (index: number, key: string, value: string | number | boolean) => {
        if (!tsmltParams.hasExtended) {
            setGeneralData({ openProModal: true });
            return;
        }
        const val = 'sizeKey' === key && typeof value === 'string' ? value.replace(/\s+/g, '_') : value;
        const updatedSizes = sizes.map((size, i) => {
            return i === index ? { ...size, [key]: val } : size;
        });
        setOptions({ custom_image_sizes: updatedSizes });
    };

    const addNewImageSize = () => {
        if (!tsmltParams.hasExtended) {
            setGeneralData({ openProModal: true });
            return;
        }
        const validSizes = sizes.filter(size => size?.sizeKey);
        setOptions({ custom_image_sizes: [...validSizes, ...defaultSize] });
    };

    const deleteImageSize = (sizeKey: string | number) => {
        if (!tsmltParams.hasExtended) {
            setGeneralData({ openProModal: true });
            return;
        }
        const validSizes = sizes.filter(size => sizeKey !== size?.sizeKey);
        setOptions({ custom_image_sizes: validSizes });
    };

    return (
        <div className="flex items-start gap-8">
            <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-[200px]">
                Register New Image Size:
                {!tsmltParams.hasExtended && <span className="text-red-600 font-bold"> - PRO</span>}
            </label>
            <div className="flex-1 space-y-4">
                {Object.keys(sizes).map((item, index) => {
                    const sizeKey = sizes[index]?.sizeKey || '';
                    const fullKey = `tsmlt_${sizeKey}`;
                    return (
                        <div key={index}>
                            <div className="flex items-center gap-3 flex-wrap">
                                <div className="flex items-center">
                                    <span className="h-[35px] px-3 py-2 text-sm bg-gray-100 border border-r-0 border-gray-300 rounded-l-md text-gray-600 whitespace-nowrap">
                                        tsmlt_
                                    </span>
                                    <input
                                        type="text"
                                        className="h-[35px] w-40 px-3 py-2 text-sm border border-gray-300 rounded-r-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        value={String(sizeKey)}
                                        placeholder="size-name"
                                        title={fullKey}
                                        onChange={(event) => registerImageSize(index, 'sizeKey', event.target.value)}
                                    />
                                </div>

                                <CopyToClipboard text={fullKey} />

                                <div className="flex items-center">
                                    <span className="px-3 py-2 text-sm h-[35px] bg-gray-100 border border-r-0 border-gray-300 rounded-l-md text-gray-600">
                                        Width
                                    </span>
                                    <input
                                        type="number"
                                        min={0}
                                        className="w-20 h-[35px] px-3 py-2 text-sm border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        value={sizes[index]?.width || ''}
                                        onChange={(event) => registerImageSize(index, 'width', Number(event.target.value))}
                                    />
                                    <span className="px-3 py-2 text-sm bg-gray-100 border border-l-0 border-gray-300 rounded-r-md text-gray-600">
                                        px
                                    </span>
                                </div>

                                <div className="flex items-center">
                                    <span className="px-3 py-2 h-[35px] text-sm bg-gray-100 border border-r-0 border-gray-300 rounded-l-md text-gray-600">
                                        Height
                                    </span>
                                    <input
                                        type="number"
                                        min={0}
                                        className="w-20 h-[35px] px-3 py-2 text-sm border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        value={sizes[index]?.height || ''}
                                        onChange={(event) => registerImageSize(index, 'height', Number(event.target.value))}
                                    />
                                    <span className="px-3 py-2 text-sm bg-gray-100 border border-l-0 border-gray-300 rounded-r-md text-gray-600">
                                        px
                                    </span>
                                </div>

                                <button
                                    type="button"
                                    className={`px-4 py-2 text-sm font-medium rounded-md cursor-pointer transition-colors ${
                                        sizes[index]?.hardCrop
                                            ? 'bg-blue-600 text-white hover:bg-blue-700'
                                            : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                                    }`}
                                    onClick={() => registerImageSize(index, 'hardCrop', !sizes[index]?.hardCrop)}
                                >
                                    {sizes[index]?.hardCrop ? 'Hard Crop' : 'Soft Crop'}
                                </button>

                                <button
                                    type="button"
                                    className="p-2 text-gray-400 hover:text-red-600 cursor-pointer transition-colors rounded-md hover:bg-red-50"
                                    title="Delete this size"
                                    onClick={() => deleteImageSize(sizes[index]?.sizeKey)}
                                >
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                            {index < sizes.length - 1 && (
                                <div className="border-b border-gray-200 mt-4"></div>
                            )}
                        </div>
                    );
                })}

                <button
                    type="button"
                    onClick={() => addNewImageSize()}
                    className="mt-2 px-5 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md cursor-pointer transition-colors"
                >
                    Add New Size
                </button>
            </div>
        </div>
    );
}

export default RegisterSize;
