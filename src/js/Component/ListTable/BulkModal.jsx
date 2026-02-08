import React, { useState, useEffect, useRef } from "react";

import { useStateValue } from "@/js/Utils/StateProvider";

import * as Types from "@/js/Utils/actionType";

import { getMedia, singleUpDateApi } from "@/js/Utils/Data";

const checkboxOptions = [
    { label: 'File Title Based on Attached Post', value: 'post_title' },
    { label: 'Alt Text Based on Attached Post', value: 'alt_text' },
    { label: 'Caption Based on Attached Post', value: 'caption' },
    { label: 'Description Based on Attached Post', value: 'post_description' },
];

function BulkModal() {

    const [stateValue, dispatch] = useStateValue();
    const [IsButtonDisabled, setIsButtonDisabled] = useState(true);
    const modalRef = useRef(null);

    const bulkSubmitData = stateValue.bulkSubmitData;
    const isOpen = bulkSubmitData.isModalOpen;
    const isBulkAssign = 'bulkEditPostTitle' === stateValue.bulkSubmitData.type;

    /**
     * Re-evaluate button disabled state when modal opens
     */
    useEffect(() => {
        if (isOpen) {
            isTheButtonDisabled();
        }
    }, [isOpen]);

    /**
     * Close on Escape key
     */
    useEffect(() => {
        const handleEsc = (e) => {
            if (e.key === 'Escape' && isOpen) {
                handleBulkModalCancel();
            }
        };
        document.addEventListener('keydown', handleEsc);
        return () => document.removeEventListener('keydown', handleEsc);
    }, [isOpen]);

    /**
     * @param event
     */
    const balkModalDataChange = ( event ) => {
        const data = {
            ...bulkSubmitData.data,
            [event.target.name] : event.target.value
        }
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...bulkSubmitData,
                data
            },
        });
        const changeDetected = Object.values( data ).some(value => value !== '');
        const isDisable = ! stateValue.bulkSubmitData.ids.length || ! changeDetected;
        setIsButtonDisabled( isDisable );
    };
    /**
     * Button Disable
     */
    const isTheButtonDisabled = () => {
        let changeDetected = false;
        if( 'bulkEditPostTitle' === stateValue.bulkSubmitData.type ){
            changeDetected = stateValue.bulkSubmitData.will_attached_post_title.length;
        } else {
            changeDetected = Object.values(stateValue.bulkSubmitData.data).some(value => value !== '');
        }
        const isDisable = ! stateValue.bulkSubmitData.ids.length || ! changeDetected;
        setIsButtonDisabled( isDisable );
    };
    /**
     *
     * @param prams
     * @returns {Promise<axios.AxiosResponse<*>>}
     */
    const addDataRecursively = async ( prams ) => {
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...stateValue.bulkSubmitData,
                progressBar: Math.floor( 100 * ( stateValue.bulkSubmitData.progressTotal - prams.ids.length ) / stateValue.bulkSubmitData.progressTotal ),
            },
        });
        if ( prams.ids.length === 0) {
            // Base case: All renaming operations are completed
            return;
        }
        const id = prams.ids[0];
        // Simulate the renaming operation using an asynchronous function (e.g., API call)
        const response = await singleUpDateApi( { bulkEditPostTitle: stateValue.bulkSubmitData.will_attached_post_title, ID: id });
        // Recur with the rest of the IDs in the list
        if( prams.ids.length && response.status ){
            await addDataRecursively( { ...prams, ids: prams.ids.slice(1) } );
        }
        return response;
    }
    /**
     * @returns {Promise<void>}
     */
    const mediaHandleBulkModalOk = async () => {
        setIsButtonDisabled( true );
        const response = await addDataRecursively( stateValue.bulkSubmitData );
        if( 200 === response?.status ){
            // Close the modal after 1 second
            setTimeout(() => {
                dispatch({
                    type: Types.BULK_SUBMIT,
                    bulkSubmitData: {
                        ...stateValue.bulkSubmitData,
                        isModalOpen: false,
                    },
                });
            }, 1000);
            const response = await getMedia( stateValue.mediaData.postQuery );
            await dispatch({
                type: Types.GET_MEDIA_LIST,
                mediaData: {
                    ...stateValue.mediaData,
                    ...response,
                    isLoading: false
                },
            });
            setIsButtonDisabled( false );
        }
    };
    /**
     * @param event
     */
    const handleBulkModalOk = () => {
        if( 'bulkEditPostTitle' === stateValue.bulkSubmitData.type ){
            mediaHandleBulkModalOk();
        }else {
            dispatch({
                ...stateValue,
                type: Types.BULK_SUBMIT,
                saveType: Types.BULK_SUBMIT
            });
        }
    };
    /**
     * searchUses
     * @param event
     */
    const handleBulkModalCancel = () => {
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...bulkSubmitData,
                isModalOpen: false,
            },
        });
    };
    /**
     * @param value
     * @param checked
     */
    const onToggleCheckbox = (value, checked) => {
        const current = bulkSubmitData.will_attached_post_title || [];
        const list = checked
            ? [...current, value]
            : current.filter(v => v !== value);
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...bulkSubmitData,
                will_attached_post_title: list
            },
        });
        setIsButtonDisabled( !list.length );
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-[1000] flex items-center justify-center">
            {/* Backdrop */}
            <div
                className="absolute inset-0 bg-black/45"
                onClick={handleBulkModalCancel}
            />

            {/* Modal */}
            <div
                ref={modalRef}
                className="relative bg-white rounded-lg shadow-xl w-full max-w-[650px] mx-4"
            >
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h3 className="text-lg font-semibold text-gray-900 m-0!">
                        { isBulkAssign ? 'Bulk Assign' : 'Bulk Edit' }
                    </h3>
                    <button
                        type="button"
                        className="p-1 text-gray-400 hover:text-gray-600 cursor-pointer transition-colors"
                        onClick={handleBulkModalCancel}
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Body */}
                <div className="px-6 py-5">
                    { isBulkAssign ? (
                        <div>
                            <p className="text-sm text-gray-700">
                                Are you certain about performing a bulk assignment based on the associated post title?
                            </p>
                            <div className="border-t border-gray-200 my-5"></div>
                            <div className="flex items-start gap-6">
                                <h5 className="text-sm font-semibold text-gray-900 m-0! whitespace-nowrap pt-1">
                                    Select the checkbox
                                </h5>
                                <div className="flex flex-col gap-3">
                                    { checkboxOptions.map((option) => (
                                        <label key={option.value} className="inline-flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                                checked={(bulkSubmitData.will_attached_post_title || []).includes(option.value)}
                                                onChange={(e) => onToggleCheckbox(option.value, e.target.checked)}
                                            />
                                            <span className="text-sm text-gray-900">{option.label}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                            { stateValue.bulkSubmitData.progressBar >= 0 && (
                                <div className="mt-5">
                                    <h5 className="text-sm font-semibold text-gray-900 mb-2">Progress:</h5>
                                    <div className="w-full bg-gray-200 rounded-full h-5 overflow-hidden">
                                        <div
                                            className="bg-blue-600 h-5 rounded-full transition-all duration-300 flex items-center justify-center"
                                            style={{ width: `${stateValue.bulkSubmitData.progressBar}%` }}
                                        >
                                            <span className="text-xs font-medium text-white">
                                                {stateValue.bulkSubmitData.progressBar}%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <div>
                                <h5 className="text-sm font-semibold text-gray-900 m-0! mb-1.5">Title</h5>
                                <textarea
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                                    rows="2"
                                    onChange={balkModalDataChange}
                                    name="post_title"
                                    value={bulkSubmitData.data.post_title}
                                    placeholder="Title"
                                />
                            </div>
                            <div>
                                <h5 className="text-sm font-semibold text-gray-900 m-0! mb-1.5">Alt Text</h5>
                                <textarea
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                                    rows="2"
                                    onChange={balkModalDataChange}
                                    name="alt_text"
                                    value={bulkSubmitData.data.alt_text}
                                    placeholder="Alt text"
                                />
                            </div>
                            <div>
                                <h5 className="text-sm font-semibold text-gray-900 m-0! mb-1.5">Caption</h5>
                                <textarea
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                                    rows="2"
                                    onChange={balkModalDataChange}
                                    name="caption"
                                    value={bulkSubmitData.data.caption}
                                    placeholder="Caption"
                                />
                            </div>
                            <div>
                                <h5 className="text-sm font-semibold text-gray-900 m-0! mb-1.5">Description</h5>
                                <textarea
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                                    rows="2"
                                    onChange={balkModalDataChange}
                                    name="post_description"
                                    value={bulkSubmitData.data.post_description}
                                    placeholder="Description"
                                />
                            </div>
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                    <button
                        type="button"
                        className="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                        onClick={handleBulkModalCancel}
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        className={`px-5 py-2 text-sm font-medium text-white rounded-md transition-colors ${
                            IsButtonDisabled
                                ? 'bg-blue-400 cursor-not-allowed'
                                : 'bg-blue-600 hover:bg-blue-700 cursor-pointer'
                        }`}
                        disabled={IsButtonDisabled}
                        onClick={handleBulkModalOk}
                    >
                        Done
                    </button>
                </div>
            </div>
        </div>
    )
}
export default BulkModal;
