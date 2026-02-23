export default function CheckboxField({ name, value, checked, onChange, label, isPro = false }) {
    return (
        <label className="inline-flex items-center gap-2 cursor-pointer">
            <input
                type="checkbox"
                className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                onChange={onChange}
                name={name}
                value={value}
                checked={checked}
            />
            <span className="text-base text-gray-900">
                {label}
                {isPro && <span className="text-red-600 font-bold"> - PRO</span>}
            </span>
        </label>
    );
}
