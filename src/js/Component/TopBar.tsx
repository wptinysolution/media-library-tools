import React from 'react';
import { useWpMenuWidth } from '@/js/Utils/Hooks';

function TopBar() {
    const wpMenuWidth = useWpMenuWidth();

    return (
        <div
            style={{ left: wpMenuWidth, top: 32 }}
            className="fixed right-0 z-9100 h-14 bg-white border-b border-gray-200 flex items-center justify-between px-6 shadow-sm"
        >
            {/* Left: Logo + Plugin name */}
            <div className="flex items-center gap-3">
                <div className="w-9 h-9 rounded-xl overflow-hidden shrink-0 shadow-sm ring-1 ring-gray-100">
                    <img
                        src={tsmltParams.iconUrl}
                        alt="Media Library Tools"
                        className="w-full h-full object-cover"
                    />
                </div>
                <div className="leading-none">
                    <div className="flex items-center gap-2">
                        <span className="font-bold text-gray-900 text-[15px]">Media Library Tools</span>
                        {tsmltParams.hasExtended ? (
                            <span className="px-1.5 py-0.5 text-[10px] font-bold text-white bg-blue-600 rounded-full uppercase tracking-wide leading-none">
                                Pro
                            </span>
                        ) : (
                            <span className="px-1.5 py-0.5 text-[10px] font-semibold text-gray-500 bg-gray-100 rounded-full uppercase tracking-wide leading-none">
                                Free
                            </span>
                        )}
                    </div>
                    <p className="text-[11px] text-gray-400 mt-0.5! m-0!">
                        by{' '}
                        <a
                            href="https://www.wptinysolutions.com/"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-gray-400 hover:text-blue-600 no-underline transition-colors"
                        >
                            WP Tiny Solutions
                        </a>
                    </p>
                </div>
            </div>

            {/* Right: Action links */}
            <div className="flex items-center gap-1">
                <a
                    href="https://help.wptinysolutions.com/"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors no-underline"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    Support
                </a>

                <a
                    href="https://wordpress.org/support/plugin/media-library-tools/reviews/#new-post"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] text-gray-600 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-colors no-underline"
                >
                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                    </svg>
                    Rate Us
                </a>

                {!tsmltParams.hasExtended && (
                    <>
                        <span className="w-px h-5 bg-gray-200 mx-1" />
                        <a
                            href={`${tsmltParams.proLink}#tiny-pricing-plan`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1.5 px-4 py-1.5 text-[13px] font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors no-underline shadow-sm"
                        >
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Upgrade to Pro
                        </a>
                    </>
                )}
            </div>
        </div>
    );
}

export default TopBar;
