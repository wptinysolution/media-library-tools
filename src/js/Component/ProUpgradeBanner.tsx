const proFeatures = [
    {
        icon: (
            <svg className="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
            </svg>
        ),
        title: 'AI Content Generation',
        desc: 'Generate titles, alt text, captions, descriptions, and file names using AI. Supports image vision and up to 10 suggestions at once.',
    },
    {
        icon: (
            <svg className="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
        ),
        title: 'Bulk Rename by Post Title / SKU / Alt Text',
        desc: 'Bulk rename files based on attached post title, WooCommerce product SKU, or existing alt text — with custom prefix & suffix support.',
    },
    {
        icon: (
            <svg className="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 10h16M4 14h16M4 18h7" />
            </svg>
        ),
        title: 'Bulk Edit by Post Title',
        desc: 'Bulk update alt text, caption, and description for selected images based on the attached post title — directly from the media table.',
    },
    {
        icon: (
            <svg className="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
            </svg>
        ),
        title: 'Auto Rename & Fill on Upload',
        desc: 'Automatically rename files and fill alt text, caption, and description from post title the moment media is uploaded.',
    },
    {
        icon: (
            <svg className="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
        ),
        title: 'Auto Inject Alt Text on Frontend',
        desc: 'Automatically fill missing alt text sitewide using post title, filename, or a custom fallback — no manual edits needed.',
    },
    {
        icon: (
            <svg className="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
        ),
        title: 'CSV Export / Import',
        desc: 'Export all media metadata to CSV and bulk-import updates. Perfect for large-scale editing and migration.',
    },
    {
        icon: (
            <svg className="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
        ),
        title: 'Add New Image Sizes',
        desc: 'Register and manage custom image sizes that WordPress generates automatically on upload. Edit or delete sizes anytime.',
    },
    {
        icon: (
            <svg className="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
        ),
        title: 'Merge Duplicate Files',
        desc: 'Keep one copy of duplicates and automatically update all references across posts, pages, and Elementor layouts.',
    },
    {
        icon: (
            <svg className="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
        ),
        title: 'Rubbish File Delete & Restore',
        desc: 'Bulk delete orphan files, ignore false positives, or restore files back to the media library. Supports instant delete during scan.',
    },
    {
        icon: (
            <svg className="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        ),
        title: 'Unused Image Detector — Delete',
        desc: 'Scan and identify unused images across your site. Pro lets you bulk delete detected unused images permanently.',
    },
    {
        icon: (
            <svg className="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
            </svg>
        ),
        title: 'Edit & Write EXIF Data',
        desc: 'Add or edit EXIF metadata (camera, date, GPS, exposure) directly on JPEG images. Bulk-edit multiple images at once from the EXIF Data panel.',
    },
    {
        icon: (
            <svg className="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
            </svg>
        ),
        title: 'Insert EXIF to Caption',
        desc: 'Bulk-insert EXIF metadata (camera model, date taken, aperture, shutter, ISO) as image captions. Automatically replaces captions across all content.',
    },
    {
        icon: (
            <svg className="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
        ),
        title: 'Bulk Strip & GPS Removal',
        desc: 'Batch-remove all EXIF data or GPS-only from multiple images. Auto-strip GPS on upload to protect user privacy sitewide.',
    },
    {
        icon: (
            <svg className="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
            </svg>
        ),
        title: 'EXIF Import / Export & History',
        desc: 'Export EXIF data to CSV/JSON and bulk-import from file. Full change history with one-click undo for every EXIF edit.',
    },
];

export default function ProUpgradeBanner() {
    if (tsmltParams.hasExtended) return null;

    return (
        <div className="mx-6 mb-8 mt-2">
            <div className="rounded-xl border border-blue-100 bg-linear-to-br from-blue-50 to-indigo-50 overflow-hidden">

                {/* Header */}
                <div className="px-6 py-5 border-b border-blue-100 flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <svg className="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                            <h3 className="text-base font-semibold text-gray-900 m-0!">Unlock the Pro Version</h3>
                        </div>
                        <p className="text-sm text-gray-500 m-0!">
                            Supercharge your media library with powerful pro-only features.
                        </p>
                    </div>
                    <a
                        href={`https://checkout.freemius.com/plugin/13159/plan/22377/licenses/5/`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white! text-sm! font-medium rounded-lg transition-colors no-underline! shrink-0"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Buy Now
                    </a>
                </div>

                {/* Features grid */}
                <div className="px-6 py-5">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        {proFeatures.map((feature, i) => (
                            <div key={i} className="flex items-start gap-3 bg-white rounded-lg border border-blue-100 px-4 py-3 shadow-sm">
                                {feature.icon}
                                <div className="min-w-0">
                                    <p className="text-[13px] font-semibold text-gray-800 m-0! mb-0.5 leading-snug">{feature.title}</p>
                                    <p className="text-[11px] text-gray-500 m-0! leading-relaxed">{feature.desc}</p>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* CTA row */}
                    <div className="mt-4 flex items-center justify-between gap-4 flex-wrap">
                        <p className="text-xs text-gray-400 m-0!">
                            Support our development efforts for the WordPress community by purchasing the Pro version.
                        </p>
                        <div className="flex items-center gap-3">
                            <a
                                href={tsmltParams.proLink}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-sm text-blue-600 hover:text-blue-700 no-underline! font-medium"
                            >
                                Learn more →
                            </a>
                            <a
                                href={`${tsmltParams.proLink}#tiny-pricing-plan`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white! text-sm! font-medium rounded-lg transition-colors no-underline!"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                Visit Our Website And Buy
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
