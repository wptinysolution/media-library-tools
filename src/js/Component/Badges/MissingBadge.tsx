const MissingBadge = () => (
    <span className="inline-flex items-center gap-1 py-0.5 px-2 text-[10px] font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded">
        <svg className="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        Missing
    </span>
);
export default MissingBadge;