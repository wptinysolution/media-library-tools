export default function TextInput({ value, onChange, placeholder = '', name, className = '' }) {
    return (
        <input
            type="text"
            name={name}
            className={`w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${className}`}
            placeholder={placeholder}
            onChange={onChange}
            value={value}
        />
    );
}
