import { useStore } from "@/js/Utils/store";
import { CopyToClipboard } from "@/js/Component/CopyToClipboard";

const defaultSize = [{ sizeKey: '', width: '', height: '', hardCrop: true }];

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
        const updatedSizes = sizes.map((size, i) => i === index ? { ...size, [key]: val } : size);
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
        <div className="space-y-3">
            {sizes.map((_item, index) => {
                const sizeKey = sizes[index]?.sizeKey || '';
                const fullKey = `tsmlt_${sizeKey}`;
                return (
                    <div key={index} className="flex items-center gap-3 flex-wrap p-4 bg-gray-50 rounded-lg border border-gray-200">
                        {/* Size key */}
                        <div className="flex items-center">
                            <span className="h-9 px-3 flex items-center text-sm bg-gray-100 border border-r-0 border-gray-300 rounded-l-md text-gray-500 whitespace-nowrap select-none">
                                tsmlt_
                            </span>
                            <input
                                type="text"
                                className="h-9 w-36 px-3 text-sm border border-gray-300 rounded-r-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                value={String(sizeKey)}
                                placeholder="size-name"
                                title={fullKey}
                                onChange={(event) => registerImageSize(index, 'sizeKey', event.target.value)}
                            />
                            <span className="ml-1.5">
                                <CopyToClipboard text={fullKey} />
                            </span>
                        </div>

                        {/* Width */}
                        <div className="flex items-center">
                            <span className="h-9 px-3 flex items-center text-sm bg-gray-100 border border-r-0 border-gray-300 rounded-l-md text-gray-500 select-none">
                                W
                            </span>
                            <input
                                type="number"
                                min={0}
                                className="w-18 h-9 px-3 text-sm border-y border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                value={sizes[index]?.width || ''}
                                onChange={(event) => registerImageSize(index, 'width', Number(event.target.value))}
                            />
                            <span className="h-9 px-3 flex items-center text-sm bg-gray-100 border border-l-0 border-gray-300 rounded-r-md text-gray-500 select-none">
                                px
                            </span>
                        </div>

                        {/* Height */}
                        <div className="flex items-center">
                            <span className="h-9 px-3 flex items-center text-sm bg-gray-100 border border-r-0 border-gray-300 rounded-l-md text-gray-500 select-none">
                                H
                            </span>
                            <input
                                type="number"
                                min={0}
                                className="w-18 h-9 px-3 text-sm border-y border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                value={sizes[index]?.height || ''}
                                onChange={(event) => registerImageSize(index, 'height', Number(event.target.value))}
                            />
                            <span className="h-9 px-3 flex items-center text-sm bg-gray-100 border border-l-0 border-gray-300 rounded-r-md text-gray-500 select-none">
                                px
                            </span>
                        </div>

                        {/* Hard crop toggle */}
                        <label className="inline-flex items-center gap-2 cursor-pointer">
                            <div
                                className={`relative w-10 h-5.5 rounded-full transition-colors ${sizes[index]?.hardCrop ? 'bg-blue-600' : 'bg-gray-300'}`}
                                onClick={() => registerImageSize(index, 'hardCrop', !sizes[index]?.hardCrop)}
                            >
                                <span className={`absolute top-0.5 left-0.5 w-4.5 h-4.5 bg-white rounded-full shadow transition-transform ${sizes[index]?.hardCrop ? 'translate-x-4.5' : 'translate-x-0'}`} />
                            </div>
                            <span className="text-sm text-gray-700 select-none">Hard Crop</span>
                        </label>

                        {/* Delete */}
                        <button
                            type="button"
                            className="ml-auto p-2 text-gray-400 hover:text-red-600 cursor-pointer transition-colors rounded-md hover:bg-red-50"
                            title="Delete this size"
                            onClick={() => deleteImageSize(sizes[index]?.sizeKey)}
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                );
            })}

            <button
                type="button"
                onClick={addNewImageSize}
                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md cursor-pointer transition-colors"
            >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                </svg>
                Add New Size
            </button>
        </div>
    );
}

export default RegisterSize;
