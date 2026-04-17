
interface ExifSummary {
    has_exif: boolean;
    camera?: Record<string, string>;
    gps?: Record<string, any>;
    other?: Record<string, string>;
}

interface ExifDetailPanelProps {
    exif: ExifSummary;
}

function ExifRow({ label, value }: { label: string; value: string | number | undefined | null }) {
    if (!value && value !== 0) return null;
    return (
        <div className="flex justify-between pb-1.5 border-b border-gray-100">
            <dt className="text-gray-400">{label}</dt>
            <dd className="font-medium text-gray-700 m-0!">{value}</dd>
        </div>
    );
}

export default function ExifDetailPanel({ exif }: ExifDetailPanelProps) {
    const hasCamera = Object.keys(exif.camera || {}).length > 0;
    const hasGps = exif.gps?.has_location;
    const hasOther = Object.keys(exif.other || {}).length > 0;
    const hasAny = hasCamera || hasGps || hasOther;

    if (!hasAny) {
        return (
            <p className="text-sm text-gray-400 text-center py-3 m-0!">No EXIF data available for this image.</p>
        );
    }

    return (
        <div className="grid grid-cols-1 space-y-3 max-w-150 ml-25">
            {hasCamera && (
                <div className="bg-white rounded-lg p-3.5 border border-gray-200">
                    <div className="flex items-center gap-1.5 mb-2.5">
                        <svg className="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <h4 className="text-xs font-semibold text-gray-500 uppercase m-0!">Camera</h4>
                    </div>
                    <dl className="space-y-1.5 text-xs">
                        <ExifRow label="Make" value={exif.camera?.make} />
                        <ExifRow label="Model" value={exif.camera?.model} />
                        <ExifRow label="ISO" value={exif.other?.iso} />
                        <ExifRow label="Exposure" value={exif.other?.exposure_time} />
                        <ExifRow label="Focal" value={exif.other?.focal_length} />
                        <ExifRow label="Software" value={exif.camera?.software} />
                    </dl>
                </div>
            )}

            {hasGps && (
                <div className="bg-white rounded-lg p-3.5 border border-gray-200">
                    <div className="flex items-center gap-1.5 mb-2.5">
                        <svg className="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <h4 className="text-xs font-semibold text-gray-500 uppercase m-0!">GPS</h4>
                    </div>
                    <dl className="space-y-1.5 text-xs">
                        <ExifRow label="Latitude" value={exif.gps?.latitude} />
                        <ExifRow label="Longitude" value={exif.gps?.longitude} />
                        <ExifRow label="Altitude" value={exif.gps?.altitude} />
                    </dl>
                </div>
            )}

            {hasOther && (
                <div className="bg-white rounded-lg p-3.5 border border-gray-200">
                    <div className="flex items-center gap-1.5 mb-2.5">
                        <svg className="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h4 className="text-xs font-semibold text-gray-500 uppercase m-0!">Other</h4>
                    </div>
                    <dl className="space-y-1.5 text-xs">
                        <ExifRow label="Date" value={exif.other?.date_time_original} />
                        <ExifRow label="Width" value={exif.other?.image_width} />
                        <ExifRow label="Height" value={exif.other?.image_height} />
                        <ExifRow label="Orientation" value={exif.other?.orientation} />
                        <ExifRow label="Aperture" value={exif.other?.f_number} />
                    </dl>
                </div>
            )}
        </div>
    );
}
