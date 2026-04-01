interface ProLabelProps {
    text?: string;
    className?: string;
}
function ProLabel({ text = 'PRO', className = '' }: ProLabelProps) {
    return (
        <span
            className={`tsmlt-pro-label ml-2 inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-bold text-amber-600 bg-amber-50 border border-amber-200 rounded uppercase tracking-wide align-middle ${className}`}>
            <svg className="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
            </svg>
            {text}
        </span>
    );
}

export default ProLabel;
