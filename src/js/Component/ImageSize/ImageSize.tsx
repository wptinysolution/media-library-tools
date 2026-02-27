import { useStore } from "@/js/Utils/store";
import Loader from "@/js/Utils/Loader";
import RegisterSize from "./RegisterSize";
import DisableSize from "./DisableSize";
import SaveButton from '@/js/Component/SaveButton';

function ImageSize() {
    const { generalData } = useStore();

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
                                {!tsmltParams.hasExtended && (
                                    <span className="ml-2 text-xs font-semibold text-white bg-red-500 px-2 py-0.5 rounded-full align-middle">PRO</span>
                                )}
                            </h2>
                            <p className="text-sm text-gray-500 mt-0.5">Add custom sizes that WordPress will generate when images are uploaded.</p>
                        </div>
                        <div className="p-6">
                            <RegisterSize />
                        </div>
                    </div>

                    <div className="bg-white rounded-lg border border-gray-200">
                        <div className="px-6 py-4 border-b border-gray-200">
                            <h2 className="text-base font-semibold text-gray-900 m-0!">Disable Registered Image Sizes</h2>
                            <p className="text-sm text-gray-500 mt-0.5">Stop WordPress from generating image sizes you don't need.</p>
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
