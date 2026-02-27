import React from 'react';
import { useWpMenuWidth } from '@/js/Utils/Hooks';

function TopBar() {
    const wpMenuWidth = useWpMenuWidth();

    return (
        <div
            style={{ left: wpMenuWidth, top: 32 }}
            className="fixed right-0 z-[9100] h-12 bg-white border-b border-gray-200 px-5 flex items-center justify-between shadow-sm"
        >
            {/* Plugin name */}
            <div className="flex items-center gap-2.5">
                <img
                    src={tsmltParams.iconUrl}
                    alt="Media Library Tools"
                    className="w-6 h-6 rounded"
                />
                <span className="font-semibold text-gray-900 text-sm">Media Library Tools</span>
                {tsmltParams.hasExtended && (
                    <span className="px-1.5 py-0.5 text-[10px] font-bold text-white bg-blue-600 rounded uppercase tracking-wide leading-none">
                        Pro
                    </span>
                )}
            </div>

            {/* Action links */}
            <div className="flex items-center gap-1">
                <a
                    href="https://help.wptinysolutions.com/"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors no-underline"
                >
                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    Support
                </a>

                <a
                    href="https://wordpress.org/support/plugin/media-library-tools/reviews/#new-post"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] text-gray-600 hover:text-amber-500 hover:bg-amber-50 rounded-md transition-colors no-underline"
                >
                    <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                    </svg>
                    Rate Us
                </a>

                {!tsmltParams.hasExtended && (
                    <a
                        href={`${tsmltParams.proLink}#tiny-pricing-plan`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1.5 ml-2 px-3 py-1.5 text-[13px] font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors no-underline"
                    >
                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 3l14 9-14 9V3z" />
                        </svg>
                        Get Pro
                    </a>
                )}
            </div>
        </div>
    );
}

export default TopBar;
