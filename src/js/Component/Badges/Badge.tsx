type BadgeVariant = 'beta' | 'camera' | 'gps' | 'meta' | 'success' | 'danger' | 'gray';

interface BadgeProps {
    variant?: BadgeVariant;
    label?: string;
    className?: string;
}

const variantStyles: Record<BadgeVariant, string> = {
    beta:    'text-amber-700 bg-amber-100 border-amber-300',
    camera:  'text-blue-700 bg-blue-50 border-blue-200',
    gps:     'text-red-700 bg-red-50 border-red-200',
    meta:    'text-amber-700 bg-amber-50 border-amber-200',
    success: 'text-emerald-700 bg-emerald-50 border-emerald-200',
    danger:  'text-red-700 bg-red-50 border-red-200',
    gray:    'text-gray-500 bg-gray-100 border-gray-200',
};

const defaultLabels: Record<BadgeVariant, string> = {
    beta:    'Beta',
    camera:  'Camera',
    gps:     'GPS',
    meta:    'Meta',
    success: 'Success',
    danger:  'Error',
    gray:    'Label',
};

export default function Badge({ variant = 'gray', label, className = '' }: BadgeProps) {
    const text = label ?? defaultLabels[variant];
    return (
        <span
            className={`tsmlt-badge inline-flex items-center px-2 py-0.5 text-[10px] font-semibold border rounded uppercase tracking-wide leading-none ${variantStyles[variant]} ${className}`}
        >
            {text}
        </span>
    );
}
