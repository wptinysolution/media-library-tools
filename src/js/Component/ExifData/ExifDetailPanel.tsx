import type { ReactNode } from "react";

interface ExifSummary {
    has_exif: boolean;
    camera?: Record<string, string>;
    gps?: Record<string, any>;
}

interface ExifDetailPanelProps {
    exif: ExifSummary;
    onEdit?: () => void;
    isPro?: boolean;
}

function ExifRow({ label, value }: { label: string; value: string | number | undefined | null }) {
    if (!value && value !== 0) return null;
    return (
        <div className="flex justify-between pb-1.5 border-b border-gray-100 last:border-0 last:pb-0">
            <dt className="text-gray-400 shrink-0 mr-3">{label}</dt>
            <dd className="font-medium text-gray-700 m-0! text-right truncate max-w-48" title={String(value)}>{value}</dd>
        </div>
    );
}

function SectionCard({ color, icon, title, children }: {
    color: 'blue' | 'red' | 'amber' | 'purple' | 'gray';
    icon: ReactNode;
    title: string;
    children: ReactNode;
}) {
    const colorMap = {
        blue:   { ring: 'border-blue-100',   bg: 'bg-blue-50',    text: 'text-blue-600',   heading: 'text-blue-500'   },
        red:    { ring: 'border-red-100',     bg: 'bg-red-50',     text: 'text-red-600',    heading: 'text-red-500'    },
        amber:  { ring: 'border-amber-100',   bg: 'bg-amber-50',   text: 'text-amber-600',  heading: 'text-amber-500'  },
        purple: { ring: 'border-purple-100',  bg: 'bg-purple-50',  text: 'text-purple-600', heading: 'text-purple-500' },
        gray:   { ring: 'border-gray-200',    bg: 'bg-gray-50',    text: 'text-gray-500',   heading: 'text-gray-400'   },
    };
    const c = colorMap[color];
    return (
        <div className="w-1/3 px-2">
            <div className={`rounded-xl border ${c.ring} overflow-hidden h-full`}>
                <div className={`flex items-center gap-2 px-3.5 py-2.5 ${c.bg}`}>
                    <span className={c.text}>{icon}</span>
                    <h4 className={`text-[11px] font-semibold uppercase tracking-wide m-0! ${c.heading}`}>{title}</h4>
                </div>
                <dl className="px-3.5 py-2.5 space-y-1.5 text-xs bg-white">
                    {children}
                </dl>
            </div>
        </div>
    );
}

const CameraIcon = () => (
    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
);

const GpsIcon = () => (
    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
);

const EditIcon = () => (
    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
    </svg>
);


export default function ExifDetailPanel({ exif, onEdit, isPro }: ExifDetailPanelProps) {
    const hasCamera = Object.keys(exif.camera || {}).length > 0;
    const hasGps = exif.gps?.has_location;

    const hasAny = hasCamera || hasGps;

    if (!hasAny) {
        return (
            <div className="flex flex-col items-center justify-center py-6 gap-2">
                <svg className="w-8 h-8 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p className="text-xs text-gray-400 m-0!">No EXIF data available for this image.</p>
                {onEdit && (
                    <button
                        type="button"
                        onClick={onEdit}
                        className="mt-1 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 cursor-pointer transition-colors"
                    >
                        <EditIcon /> {isPro ? 'Add EXIF Data' : 'Add EXIF Data (Pro)'}
                    </button>
                )}
            </div>
        );
    }

    return (
        <div className="flex flex-wrap justify-center gap-y-2 mx-auto max-w-200">
            {hasCamera && (
                <SectionCard color="blue" icon={<CameraIcon />} title="Camera">
                    <ExifRow label="Make" value={exif.camera?.make} />
                    <ExifRow label="Model" value={exif.camera?.model} />
                    <ExifRow label="Software" value={exif.camera?.software} />
                    <ExifRow label="ISO" value={exif.other?.iso} />
                    <ExifRow label="Exposure" value={exif.other?.exposure_time} />
                    <ExifRow label="Aperture" value={exif.other?.f_number} />
                    <ExifRow label="Focal Length" value={exif.other?.focal_length} />
                    <ExifRow label="Flash" value={exif.other?.flash} />
                    <ExifRow label="White Balance" value={exif.other?.white_balance} />
                    <ExifRow label="Exposure Mode" value={exif.other?.exposure_mode} />
                    <ExifRow label="Metering Mode" value={exif.other?.metering_mode} />
                </SectionCard>
            )}

            {hasGps && (
                <SectionCard color="red" icon={<GpsIcon />} title="GPS Location">
                    <ExifRow label="Latitude" value={exif.gps?.latitude} />
                    <ExifRow label="Longitude" value={exif.gps?.longitude} />
                    <ExifRow label="Altitude" value={exif.gps?.altitude} />
                </SectionCard>
            )}

        </div>
    );
}
