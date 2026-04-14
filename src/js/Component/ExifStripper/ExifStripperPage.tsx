import React from "react";

export default function ExifStripperPage() {
    return (
        <div className="mx-auto px-6 py-8 min-h-screen bg-gray-50">
            {/* Header */}
            <div className="mb-8">
                <div className="flex items-center gap-3 mb-2">
                    <h1 className="text-2xl font-semibold text-gray-900 m-0!">EXIF Stripper</h1>
                    <span className="inline-flex items-center px-2.5 py-0.5 text-xs font-medium text-purple-800 bg-purple-100 rounded-full">
                        Pro Feature
                    </span>
                </div>
                <p className="text-sm text-gray-500">Remove EXIF metadata (GPS, camera info, author) from your images to protect privacy and reduce file size.</p>
            </div>

            {/* Pro Feature Notice */}
            <div className="bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-200 rounded-xl p-8 text-center">
                <div className="w-16 h-16 mx-auto mb-4 bg-purple-100 rounded-full flex items-center justify-center">
                    <svg className="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h2 className="text-xl font-semibold text-gray-900 mb-2">Unlock EXIF Stripper</h2>
                <p className="text-gray-600 mb-6 max-w-md mx-auto">
                    Remove EXIF metadata from your images to protect privacy, reduce file sizes, and comply with data regulations.
                </p>
                <div className="flex flex-wrap justify-center gap-3 mb-6">
                    <div className="flex items-center gap-2 px-3 py-1.5 bg-white rounded-full border border-gray-200 text-sm">
                        <svg className="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Remove GPS Location Data</span>
                    </div>
                    <div className="flex items-center gap-2 px-3 py-1.5 bg-white rounded-full border border-gray-200 text-sm">
                        <svg className="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Strip Camera Info</span>
                    </div>
                    <div className="flex items-center gap-2 px-3 py-1.5 bg-white rounded-full border border-gray-200 text-sm">
                        <svg className="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Batch Processing</span>
                    </div>
                </div>
                <button
                    type="button"
                    className="px-6 py-3 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 cursor-pointer transition-colors shadow-lg shadow-purple-200"
                    onClick={() => {
                        if (typeof tsmltParams !== 'undefined' && tsmltParams.hasExtended) {
                            window.location.href = tsmltParams.adminUrl + 'admin.php?page=mlt-pro-settings#pro';
                        } else {
                            alert('Please upgrade to Pro version to use this feature.');
                        }
                    }}
                >
                    {typeof tsmltParams !== 'undefined' && tsmltParams.hasExtended 
                        ? 'Configure in Pro Settings' 
                        : 'Upgrade to Pro'}
                </button>
            </div>

            {/* Feature Preview */}
            <div className="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="bg-white rounded-xl border border-gray-200 p-6">
                    <div className="w-10 h-10 mb-4 bg-red-100 rounded-lg flex items-center justify-center">
                        <svg className="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h3 className="text-base font-semibold text-gray-900 mb-2">GPS Location Removal</h3>
                    <p className="text-sm text-gray-500">Strip GPS coordinates and location data to protect user privacy when sharing images online.</p>
                </div>
                <div className="bg-white rounded-xl border border-gray-200 p-6">
                    <div className="w-10 h-10 mb-4 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg className="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h3 className="text-base font-semibold text-gray-900 mb-2">Camera Info Stripping</h3>
                    <p className="text-sm text-gray-500">Remove camera make, model, serial numbers, and other identifying information from photos.</p>
                </div>
                <div className="bg-white rounded-xl border border-gray-200 p-6">
                    <div className="w-10 h-10 mb-4 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 className="text-base font-semibold text-gray-900 mb-2">Batch Processing</h3>
                    <p className="text-sm text-gray-500">Process multiple images at once with automatic batching and progress tracking.</p>
                </div>
            </div>
        </div>
    );
}
