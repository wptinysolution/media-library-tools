import ExifDetailPanel from "@/js/Component/ExifData/ExifDetailPanel";
import ProLabel from "@/js/Component/Badges/ProLabel";

interface ExifSummary {
    has_exif: boolean;
    camera?: Record<string, string>;
    gps?: Record<string, any>;
    other?: Record<string, string>;
}

interface ExifImage {
    attachment_id: number;
    title: string;
    url: string;
    has_exif: boolean;
    exif_summary: ExifSummary;
    stripped: boolean;
}

interface ExifImageRowProps {
    image: ExifImage;
    isSelected: boolean;
    isExpanded: boolean;
    isStripping: boolean;
    isLast: boolean;
    isPro: boolean;
    onToggleSelect: () => void;
    onToggleExpand: () => void;
    onEdit: () => void;
    onStrip: () => void;
}

export default function ExifImageRow({
    image,
    isSelected,
    isExpanded,
    isStripping,
    isLast,
    onToggleSelect,
    onToggleExpand,
    onEdit,
    onStrip,
    isPro
}: ExifImageRowProps) {
    const exif = image.exif_summary;
    const hasGps = exif.gps?.has_location;
    const hasCamera = Object.keys(exif.camera || {}).length > 0;
    const hasOther = Object.keys(exif.other || {}).length > 0;

    return (
        <div className={!isLast ? 'border-b border-gray-100' : ''}>
            {/* Row */}
            <div
                className={`flex items-center gap-4 px-5 py-3 cursor-pointer transition-colors ${
                    isSelected ? 'bg-blue-50/50' : 'hover:bg-gray-50/60'
                }`}
                onClick={onToggleExpand}
            >
                {/* Checkbox */}
                <div className="shrink-0" onClick={(e) => e.stopPropagation()}>
                    <input
                        type="checkbox"
                        className="w-4 h-4 rounded border-gray-300 text-blue-600 cursor-pointer"
                        checked={isSelected}
                        onChange={onToggleSelect}
                    />
                </div>

                {/* Expand arrow */}
                <svg
                    className={`w-4 h-4 shrink-0 text-gray-400 transition-transform duration-200 ${isExpanded ? 'rotate-90' : ''}`}
                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                </svg>

                {/* Thumbnail */}
                <div className="shrink-0 w-12 h-12 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
                    {image.url ? (
                        <img src={image.url} alt={image.title} className="w-full h-full object-cover" />
                    ) : (
                        <svg className="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    )}
                </div>

                {/* Title + badges */}
                <div className="flex-1 min-w-0">
                    <h3 className="text-sm mt-0! mb-0.5 font-medium text-gray-900 truncate">
                        {image.title || `Untitled (ID: ${image.attachment_id})`}
                    </h3>
                    {image.url && (
                        <p className="text-[11px] text-gray-400 truncate mb-1 m-0!">
                            {image.url}
                        </p>
                    )}
                    <div className="flex flex-wrap gap-1.5">
                        {image.stripped ? (
                            <span className="inline-flex items-center px-2 py-0.5 text-[10px] font-medium text-emerald-700 bg-emerald-50 rounded">
                                Stripped
                            </span>
                        ) : !image.has_exif ? (
                            <span className="inline-flex items-center px-2 py-0.5 text-[10px] font-medium text-gray-500 bg-gray-100 rounded">
                                No EXIF
                            </span>
                        ) : (
                            <>
                                {hasCamera && (
                                    <span className="inline-flex items-center px-2 py-0.5 text-[10px] font-medium text-blue-700 bg-blue-50 rounded">
                                        Camera
                                    </span>
                                )}
                                {hasGps && (
                                    <span className="inline-flex items-center px-2 py-0.5 text-[10px] font-medium text-red-700 bg-red-50 rounded">
                                        GPS
                                    </span>
                                )}
                                {hasOther && (
                                    <span className="inline-flex items-center px-2 py-0.5 text-[10px] font-medium text-amber-700 bg-amber-50 rounded">
                                        Meta
                                    </span>
                                )}
                            </>
                        )}
                    </div>
                </div>

                {/* ID badge */}
                <span className="shrink-0 hidden sm:inline-flex items-center px-2 py-0.5 text-[10px] font-medium text-gray-500 bg-gray-100 rounded">
                    #{image.attachment_id}
                </span>

                {/* Row actions */}
                <div className="shrink-0 flex items-center gap-2" onClick={(e) => e.stopPropagation()}>
                    <button
                        type="button"
                        onClick={onEdit}
                        className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-200 rounded-md hover:bg-gray-50 hover:border-gray-300 cursor-pointer transition-colors"
                    >
                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit
                        {!isPro && <ProLabel /> }
                    </button>
                    {image.has_exif && !image.stripped && (
                        <button
                            type="button"
                            disabled={isStripping}
                            onClick={onStrip}
                            className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-red-600 bg-white border border-red-200 rounded-md hover:bg-red-50 hover:border-red-300 disabled:opacity-50 cursor-pointer transition-colors"
                        >
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            {isStripping ? 'Removing...' : 'Remove EXIF Data'}
                        </button>
                    )}
                </div>
            </div>

            {/* Expanded EXIF details */}
            {isExpanded && (
                <div className="px-5 py-4 bg-gray-50/80 border-t border-gray-100">
                    <ExifDetailPanel exif={exif} />
                </div>
            )}
        </div>
    );
}
