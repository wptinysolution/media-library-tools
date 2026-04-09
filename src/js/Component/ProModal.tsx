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
        { title: 'All Free Features Included', desc: 'Everything in the free version, plus all premium features below.' },
        { title: 'AI-Powered Content Generation', desc: 'Generate titles, alt text, captions, descriptions, and file names using AI.' },
        { title: 'Bulk Edit by Post Title', desc: 'Bulk add alt text, caption, and description based on the attached post title.' },
        { title: 'Bulk Rename by Post Title / SKU', desc: 'Rename media files in bulk based on attached post title or WooCommerce product SKU.' },
        { title: 'Auto Rename on Upload', desc: 'Automatically rename media files when uploaded, based on attached post title or custom name.' },
        { title: 'Media CSV Export / Import', desc: 'Export and import media metadata via CSV for bulk editing and backup.' },
        { title: 'Register Custom Image Sizes', desc: 'Register custom image sizes that WordPress generates on upload.' },
        { title: 'Merge Duplicate Files', desc: 'Detect duplicates, keep one copy, and update all references across posts, pages, and Elementor.' },
        { title: 'Rubbish File Delete & Restore', desc: 'Delete unnecessary files or restore them back into the WordPress media library.' },
        { title: 'Find Where Images Are Used', desc: 'Search selected images and find which posts, pages, or custom post types use them.' },
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
                        className="px-5 py-2 text-sm! font-medium text-white! bg-blue-600 rounded-md hover:bg-blue-700 transition-colors no-underline"
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
                <p className="text-sm text-gray-700 mb-2 mt-0!">
                    Pro Feature offers a range of enhanced functionalities and benefits...
                </p>
                <hr className="border-gray-200 my-2" />
                <div className="space-y-1">
                    {data.map((item, index) => (
                        <div key={index} className="flex items-start gap-3 py-2">
                            <svg className="w-10 h-10 text-blue-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9 14l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                            </svg>
                            <div>
                                <span className="text-[15px] font-medium text-blue-600">{item.title}</span>
                                <p className="text-sm text-gray-700 mb-0! mt-0.5!">{item.desc}</p>
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
