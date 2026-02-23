export default function TextInput({ value, onChange, placeholder = '', name, className = '' }) {
    return (
        <input
            type="text"
            name={name}
            className={`w-full max-w-md !px-3.5 !py-2.5 !text-sm !text-gray-900 !bg-white !border !border-gray-300 !rounded-lg !shadow-none placeholder-gray-400 transition-all duration-150 focus:!outline-none focus:!border-blue-500 focus:!ring-2 focus:!ring-blue-500/20 focus:!shadow-none hover:!border-gray-400 ${className}`}
            placeholder={placeholder}
            onChange={onChange}
            value={value}
        />
    );
}
