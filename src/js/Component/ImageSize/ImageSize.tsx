import { useNavigate } from "react-router-dom";
import { useStore } from "@/js/Utils/store";
import Loader from "@/js/Utils/Loader";
import RegisterSize from "./RegisterSize";
import DisableSize from "./DisableSize";
import SaveButton from '@/js/Component/SaveButton';
import ProLabel from "@/js/Component/Badges/ProLabel";

function ImageSize() {
    const { generalData } = useStore();
    const navigate = useNavigate();

    return generalData.isLoading ? <Loader /> : (
        <div className="min-h-screen bg-gray-50 overflow-y-auto pb-32">
            <div className="max-w-5xl mx-auto px-6 py-8">
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-gray-900 m-0!">Image Size Settings</h1>
                    <p className="text-sm text-gray-500 mt-1">Register custom image sizes and disable unused ones to save server space.</p>
                </div>

                <div className="space-y-6">
                    <div className="bg-white rounded-lg border border-gray-200">
                        <div className="px-6 py-4 border-b border-gray-200">
                            <h2 className="text-base font-semibold text-gray-900 m-0!">
                                Register Custom Image Sizes
                                {!tsmltParams.hasExtended && <ProLabel /> }
                            </h2>
                            <p className="text-sm text-gray-500 mt-0.5">Add custom sizes that WordPress will generate when images are uploaded.</p>
                        </div>
                        <div className="p-6">
                            <RegisterSize />
                        </div>
                    </div>

                    <div className="bg-white rounded-lg border border-gray-200">
                        <div className="px-6 py-4 border-b border-gray-200 flex items-start justify-between gap-4">
                            <div>
                                <h2 className="text-base font-semibold text-gray-900 m-0!">Disable Registered Image Sizes</h2>
                                <p className="text-sm text-gray-500 mt-0.5">Stop WordPress from generating image sizes you don't need.</p>
                            </div>
                            <div className={'flex gap-5 items-center '}>
                                After Change
                                <button
                                    type="button"
                                    onClick={() => navigate('/regenerate')}
                                    className="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors cursor-pointer whitespace-nowrap"
                                >
                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                   Regenerate Thumbnails Required
                                </button>
                            </div>
                        </div>
                        <div className="p-6">
                            <DisableSize />
                        </div>
                    </div>
                </div>

                <SaveButton />
            </div>
        </div>
    );
}

export default ImageSize;
