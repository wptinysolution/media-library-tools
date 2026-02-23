export default function SettingRow({ label, children, bordered = false }) {
    return (
        <div className={`flex items-start gap-8 ${bordered ? 'pt-6 border-t border-gray-200' : ''}`}>
            <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-[200px]">
                {label}
            </label>
            <div className="flex-1 space-y-2">
                {children}
            </div>
        </div>
    );
}
