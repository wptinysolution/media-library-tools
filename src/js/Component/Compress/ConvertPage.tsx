import ConvertTab from '@/js/Component/Compress/ConvertTab';

/**
 * Convert Images page.
 *
 * Page shell around `ConvertTab`, which holds the whole feature. Kept as a thin
 * wrapper so the conversion UI itself stays independent of how it is reached —
 * it was previously mounted as a tab inside Compress Images and could be again
 * without touching the component.
 */
export default function ConvertPage() {
    return (
        <div className="min-h-screen bg-gray-50 overflow-y-auto pb-32">
            <div className="max-w-4xl mx-auto px-3 py-3">
                <div className="bg-white rounded-lg border border-gray-200">
                    <div className="px-6 py-5 border-b border-gray-200">
                        <h3 className="text-xl m-0! font-semibold text-gray-900">Convert to WebP/AVIF</h3>
                        <p className="text-sm text-gray-500 mt-1! m-0!">
                            Generate modern WebP and AVIF copies alongside your original images.
                        </p>
                    </div>

                    <ConvertTab />
                </div>
            </div>
        </div>
    );
}
