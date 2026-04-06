interface RegenerateInitProps {
    text?: string;
    className?: string;
}
function RegenerateInit({ className = '' }: RegenerateInitProps) {
    return (
            <>
            <div className={`min-h-screen bg-gray-50 ${className}`}>
                <div className="max-w-3xl mx-auto px-6 py-8">
                    <div className="mb-8">
                        <h1 className="text-2xl font-semibold text-gray-900 m-0! inline-flex items-center gap-2">
                            Regenerate Thumbnails
                        </h1>
                        <p className="text-sm text-gray-500 mt-1">Regenerate Thumbnails allows you to regenerate all thumbnail sizes for one or more images that have been uploaded to your Media Library.</p>
                    </div>
                    <div className="bg-white rounded-lg border border-gray-200 p-10 text-center">
                        Comming Soon
                    </div>
                </div>
            </div>
        </>
    );
}

export default RegenerateInit;
