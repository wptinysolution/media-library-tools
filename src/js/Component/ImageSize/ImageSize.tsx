import React from 'react';
import { useStore } from "@/js/Utils/store";
import Loader from "@/js/Utils/Loader";
import RegisterSize from "./RegisterSize";
import DisableSize from "./DisableSize";
import SaveButton from '@/js/Component/SaveButton';

function ImageSize() {
    const { generalData } = useStore();

    return generalData.isLoading ? <Loader /> : (
        <div className="min-h-screen bg-gray-50 overflow-y-auto pb-32">
            <div className="max-w-7xl mx-auto px-3 py-3">
                <div className="space-y-8">
                    <div className="bg-white rounded-lg border border-gray-200">
                        <div className="px-6 py-5 border-b border-gray-200">
                            <h3 className="text-xl m-0! font-semibold text-gray-900">Image Size Settings</h3>
                        </div>
                        <div className="p-6 space-y-6">
                            <RegisterSize />
                        </div>
                        <div className="p-6 space-y-6 border-t border-gray-200">
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
