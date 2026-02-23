export default function CheckboxField({ name, value, checked, onChange, label, isPro = false }) {
    return (
        <label className={`inline-flex items-center gap-3 group ${isPro ? 'cursor-pointer opacity-75' : 'cursor-pointer'}`}>
            {/* Custom checkbox box — a <span> so WordPress form CSS won't touch it */}
            <span className={`relative flex-shrink-0 w-[18px] h-[18px] !rounded border-2 transition-all duration-150 ${
                checked
                    ? '!bg-blue-600 !border-blue-600 shadow-sm'
                    : '!bg-white !border-gray-300 group-hover:!border-blue-400'
            }`}>
                {checked && (
                    <svg
                        className="absolute inset-0 w-full h-full p-[2px] text-white"
                        viewBox="0 0 12 12"
                        fill="none"
                    >
                        <path
                            d="M2 6l3 3 5-5"
                            stroke="currentColor"
                            strokeWidth="2.2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                    </svg>
                )}
                {/* Native input — hidden behind custom box, handles all events */}
                <input
                    type="checkbox"
                    className="!absolute !inset-0 !w-full !h-full !opacity-0 !m-0 !p-0 !border-0 cursor-pointer"
                    onChange={onChange}
                    name={name}
                    value={value}
                    checked={checked}
                />
            </span>

            <span className="text-sm text-gray-700 leading-snug select-none">
                {label}
                {isPro && (
                    <span className="ml-2 inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-bold text-amber-600 bg-amber-50 border border-amber-200 rounded uppercase tracking-wide align-middle">
                        <svg className="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" />
                        </svg>
                        PRO
                    </span>
                )}
            </span>
        </label>
    );
}
