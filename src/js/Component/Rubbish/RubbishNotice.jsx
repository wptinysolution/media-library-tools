import React, { useEffect } from "react";

import { useStateValue } from "@/js/Utils/StateProvider";

import * as Types from "@/js/Utils/actionType";

import { localStoreData, localRetrieveData } from "@/js/Utils/UtilData";

function RubbishNotice() {

    const [stateValue, dispatch] = useStateValue();

    const handleNoticeModalHide = () => {
        dispatch({
            type: Types.RUBBISH_MEDIA,
            rubbishMedia: {
                ...stateValue.rubbishMedia,
                showRubbishNotice: false
            },
        });
    };

    useEffect(() => {
        const handleEsc = (e) => {
            if (e.key === 'Escape' && stateValue.rubbishMedia.showRubbishNotice) {
                handleNoticeModalHide();
            }
        };
        document.addEventListener('keydown', handleEsc);
        return () => document.removeEventListener('keydown', handleEsc);
    }, [stateValue.rubbishMedia.showRubbishNotice]);

    if (!stateValue.rubbishMedia.showRubbishNotice) return null;

    return (
        <div className="fixed inset-0 z-[1000] flex items-center justify-center">
            <div className="absolute inset-0 bg-black/45" onClick={handleNoticeModalHide} />
            <div className="relative bg-white rounded-lg shadow-xl w-full max-w-[950px] mx-4">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h3 className="text-lg font-semibold text-gray-900 m-0!">Notice</h3>
                    <button type="button" className="p-1 text-gray-400 hover:text-gray-600 cursor-pointer transition-colors" onClick={handleNoticeModalHide}>
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Body */}
                <div className="px-6 py-5 h-[500px] overflow-y-auto">
                    <h5 className="text-[15px] font-semibold text-red-600 mb-4">
                        Important Notice: Prioritize data safety. Always back up files before deletion to avoid irreversible loss.
                    </h5>
                    <hr className="border-gray-200 my-3" />

                    <h5 className="text-[15px] font-semibold text-green-600 mb-4">What is Rubbish File?</h5>
                    <p className="text-sm text-gray-700 mb-3">
                        "Rubbish File" is a file that physically exists within a directory but is excluded from being indexed or included in the media library or database of an application or system.
                    </p>
                    <hr className="border-gray-200 my-3" />

                    <h5 className="text-[15px] font-semibold text-green-600 mb-4">Why Need Delete Rubbish File?</h5>
                    <p className="text-sm text-gray-700 mb-2">
                        <strong>Freeing up storage space</strong>: Delete rubbish files to create more available storage space on your device.
                    </p>
                    <p className="text-sm text-gray-700 mb-2">
                        <strong>Improving performance</strong>: Removing unnecessary files can lead to a faster and more efficient system.
                    </p>
                    <p className="text-sm text-gray-700 mb-2">
                        <strong>Enhancing security and organization</strong>: Deleting rubbish files can help keep your data secure and your file system organized.
                    </p>
                    <hr className="border-gray-200 my-3" />

                    <h5 className="text-[15px] font-semibold text-green-600 mb-4">Delete File Can be Restore?</h5>
                    <p className="text-sm text-gray-700 mb-2">
                        No. You can't get back that file.
                        <span> That's Why Before deleting any file search this file in you media library
                        And Re-check the url and be sure before deleting.</span>
                    </p>
                    <p className="text-sm text-gray-700 mb-3">
                        Thank you for your cooperation and understanding.
                    </p>
                    <hr className="border-gray-200 my-3" />

                    <label className="inline-flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                            onChange={(event) => event.target.checked ? localStoreData("showRubbishNotice", 'disable') : localStorage.removeItem("showRubbishNotice")}
                        />
                        <span className="text-sm text-green-600">Hide Notice For Today</span>
                    </label>
                </div>
            </div>
        </div>
    );
}
export default RubbishNotice;
