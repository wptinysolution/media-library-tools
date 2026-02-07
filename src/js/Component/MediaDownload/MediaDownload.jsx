import React, { useState } from 'react';
import { useStateValue } from "@/js/Utils/StateProvider";
import MainHeader from "@/js/Component/MainHeader";
import Loader from "@/js/Utils/Loader";
import {CopyToClipboard} from "@/js/Component/CopyToClipboard.jsx";

function MediaDownload() {
    const [stateValue, dispatch] = useStateValue();
    const ShortcodeCard = ( props ) => {
        const {
            label,
            shortcode,
            phpCode,
            isPro = false,
        } = props;
        const isLocked = isPro && !cptwoointParams.hasExtended;

        return (
            <div
                className={`bg-white rounded-xl border transition-all duration-300 overflow-hidden ${
                    isLocked
                        ? 'border-gray-200 opacity-75 cursor-pointer hover:border-amber-300 hover:shadow-md'
                        : 'border-gray-100 hover:border-blue-200 hover:shadow-md'
                }`}
                onClick={() => openProModal(isPro)}
            >
                {/* Card Header */}
                <div className={`px-5 py-3 border-b flex items-center justify-between ${
                    isLocked ? 'bg-gradient-to-r from-amber-50 to-transparent border-amber-100' : 'bg-gradient-to-r from-blue-50 to-transparent border-gray-100'
                }`}>
                    <div className="flex items-center gap-3">
                        <div className={`w-8 h-8 rounded-lg flex items-center justify-center ${
                            isLocked ? 'bg-amber-100' : 'bg-blue-100'
                        }`}>
                            <svg
                                className={`w-4 h-4 ${isLocked ? 'text-amber-600' : 'text-blue-600'}`}
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"
                                />
                            </svg>
                        </div>
                        <h3 className="font-semibold m-0! text-gray-800">{label}</h3>
                    </div>
                    {isLocked && (
                        <span className="inline-flex items-center gap-1.5 px-2.5 py-1 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-xs font-bold rounded-full shadow-sm">
                        <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" />
                        </svg>
                        PRO
                    </span>
                    )}
                </div>

                {/* Card Body */}
                <div className="p-5 space-y-4">
                    {/* Shortcode Block */}
                    <div>
                        <div className="flex items-center gap-2 mb-2">
                            <span className="text-xs font-medium text-gray-500 uppercase tracking-wide">Shortcode</span>
                            <div className="flex-1 h-px bg-gray-100"></div>
                        </div>
                        <div className="flex gap-2">
                            <div className="flex-1 bg-gray-900 rounded-lg px-4 py-3 overflow-x-auto">
                                <code className="text-green-400 text-sm font-mono">
                                    {shortcode}
                                </code>
                            </div>
                            <CopyToClipboard text={shortcode} />
                        </div>
                    </div>

                    {/* PHP Code Block */}
                    <div>
                        <div className="flex items-center gap-2 mb-2">
                            <span className="text-xs font-medium text-gray-500 uppercase tracking-wide">PHP Code</span>
                            <div className="flex-1 h-px bg-gray-100"></div>
                        </div>
                        <div className="flex gap-2">
                            <div className="flex-1 bg-gray-900 rounded-lg px-4 py-3 overflow-x-auto">
                                <code className="text-blue-400 text-xs font-mono">
                                    {phpCode}
                                </code>
                            </div>
                            <CopyToClipboard text={phpCode} />
                        </div>
                    </div>
                </div>
            </div>
        );
    };

    return (
        <>
            <MainHeader />
            {stateValue.generalData.isLoading ? (
                <Loader />
            ) : (
                <div className="p-6 bg-white/35 rounded-lg shadow-sm">
                    <h3 className="text-2xl font-semibold m-0 mb-6">
                        Button For Download Media File
                    </h3>

                    <div className="border-t border-gray-200 my-6"></div>
                    <ShortcodeCard
                        label="Download By Id"
                        shortcode="[tsmlt_download_button id='11393' text='Download Now' class='my-custom-btn' /]"
                        phpCode={`<?php echo shortcode_exists('tsmlt_download_button') ? do_shortcode( "[tsmlt_download_button id='11393' text='Download Now' class='my-custom-btn' /]" ) : '' ; ?>`}
                    />

                    <ShortcodeCard
                        label="Download By URL"
                        shortcode="[tsmlt_download_button url='http://examole.local/image.jpg' text='Download Now' class='my-custom-btn' /]"
                        phpCode={`<?php echo shortcode_exists('tsmlt_download_button') ? do_shortcode( "[tsmlt_download_button url='http://examole.local/image.jpg' text='Download Now' class='my-custom-btn' /]" ) : '' ; ?>`}
                    />
                </div>
            )}
        </>
    );
}

export default MediaDownload;