export default function Textarea({ value, onChange, placeholder = '', rows = 3, name, className = '' }) {
    return (
        <textarea
            name={name}
            className={`w-full max-w-2xl px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none ${className}`}
            rows={rows}
            placeholder={placeholder}
            onChange={onChange}
            value={value}
        />
    );
}
