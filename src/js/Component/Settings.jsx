import React from 'react';

import { useStore } from '@/js/Utils/store';

import Loader from '@/js/Utils/Loader';

import MainHeader from "@/js/Component/MainHeader";

import SaveButton from '@/js/Component/SaveButton';

import MediaTableSettings from '@/js/Component/Settings/MediaTableSettings';

import AltTextSettings from '@/js/Component/Settings/AltTextSettings';

import CaptionSettings from '@/js/Component/Settings/CaptionSettings';

import DescriptionSettings from '@/js/Component/Settings/DescriptionSettings';

import RenamerSettings from '@/js/Component/Settings/RenamerSettings';

function Settings() {
    const { options } = useStore();

    return (
        <>
            <MainHeader />
            <div className="min-h-screen bg-gray-50 overflow-y-auto pb-32">
                <div className="max-w-7xl mx-auto px-6 py-8">
                    {options.isLoading ? (
                        <Loader fullScreen />
                    ) : (
                        <div className="space-y-8">
                            <div className="bg-white rounded-lg border border-gray-200">
                                <div className="px-6 py-5 border-b border-gray-200">
                                    <h3 className="text-xl m-0! font-semibold text-gray-900">Media Table Settings</h3>
                                </div>
                                <MediaTableSettings />
                                <AltTextSettings />
                                <CaptionSettings />
                                <DescriptionSettings />
                            </div>
                            <RenamerSettings />
                        </div>
                    )}

                    <SaveButton />
                </div>
            </div>
        </>
    );
}

export default Settings;
