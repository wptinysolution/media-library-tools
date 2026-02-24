import React from 'react';
import { useStore } from '@/js/Utils/store';
import Modal from '@/js/Component/Common/Modal';

interface ProFeature {
    title: string;
    desc: string;
}

function ProModal() {
    const { generalData, setGeneralData } = useStore();

    const handleBulkModalCancel = () => {
        setGeneralData({ openProModal: false });
    };

    const data: ProFeature[] = [
        { title: 'All free Features Included', desc: 'All features available in the free version are included.' },
        { title: 'Search And Find Used Images (Attached Post)', desc: 'Identify where the media file is used and display the corresponding page link.' },
        { title: 'Media File CSV Export/Import', desc: 'This feature empowers users to efficiently transfer, backup, and manage their media files, enhancing both the convenience and accuracy of data handling.' },
        { title: 'Bulk Renaming File Based on Associated Post Title', desc: 'Automatic renaming of media files bulk mode.' },
        { title: 'Renaming File Prior to Uploading Based on Attached Posts Title', desc: 'Automatic renaming of media files prior to uploading based on attached posts.' },
        { title: 'Auto Rename Based on Custom Name', desc: 'Implement automatic renaming of media files based on custom text.' },
        { title: 'Bulk Add Alt Text, Caption, and Description Based on Associated Post Title', desc: 'Add Alt Text, Caption, and Description Based on Associated Post Title Bulk mode.' },
        { title: 'Register Custom Image Sizes', desc: 'Easily register custom image sizes as needed.' },
        { title: 'Find And Bulk Delete Unnecessary / Rubbish File', desc: 'Easily mass delete unnecessary files, optimizing storage space and simplifying clutter management with bulk deletion.' },
    ];

    return (
        <Modal
            isOpen={generalData.openProModal}
            onClose={handleBulkModalCancel}
            maxWidth="max-w-[630px]"
            title={
                <h5 className="text-lg font-semibold text-red-600 m-0!">
                    No Pro version or expired license. Please purchase or renew to access features.
                </h5>
            }
            footer={
                <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                    <button
                        type="button"
                        className="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                        onClick={handleBulkModalCancel}
                    >
                        Cancel
                    </button>
                    <a
                        target="_blank"
                        href={`${tsmltParams.proLink}#tiny-pricing-plan`}
                        className="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors no-underline"
                    >
                        Get Pro Version
                    </a>
                    <a
                        target="_blank"
                        href={tsmltParams.proLink}
                        className="px-5 py-2 text-sm font-medium text-blue-600 border border-blue-600 rounded-md hover:bg-blue-50 transition-colors no-underline"
                    >
                        Visit Websites
                    </a>
                </div>
            }
        >
            <div className="px-6 py-5 h-[550px] overflow-y-auto">
                <p className="text-sm text-gray-700 mb-2">
                    Pro Feature offers a range of enhanced functionalities and benefits...
                </p>
                <hr className="border-gray-200 my-2" />
                <div className="space-y-1">
                    {data.map((item, index) => (
                        <div key={index} className="flex items-start gap-3 py-2">
                            <svg className="w-10 h-10 text-blue-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9 14l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                            </svg>
                            <div>
                                <span className="text-[15px] font-medium text-blue-600">{item.title}</span>
                                <p className="text-sm text-gray-700 mt-0.5">{item.desc}</p>
                            </div>
                        </div>
                    ))}
                </div>
                <p className="text-sm text-red-600 mt-3">
                    Support our development efforts for the WordPress community by purchasing the Pro version, enabling us to create more innovative products.
                </p>
                <hr className="border-gray-200 my-3" />
            </div>
        </Modal>
    );
}

export default ProModal;
