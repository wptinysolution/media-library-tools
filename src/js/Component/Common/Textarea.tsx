import React from 'react';

interface TextareaProps {
    value: string | number | undefined;
    onChange: React.ChangeEventHandler<HTMLTextAreaElement>;
    placeholder?: string;
    rows?: number;
    name?: string;
    className?: string;
}

export default function Textarea({ value, onChange, placeholder = '', rows = 3, name, className = '' }: TextareaProps) {
    return (
        <textarea
            name={name}
            className={`w-full max-w-2xl !px-3.5 !py-2.5 !text-sm !text-gray-900 !bg-white !border !border-gray-300 !rounded-lg !shadow-none placeholder-gray-400 transition-all duration-150 focus:!outline-none focus:!border-blue-500 focus:!ring-2 focus:!ring-blue-500/20 focus:!shadow-none hover:!border-gray-400 resize-y ${className}`}
            rows={rows}
            placeholder={placeholder}
            onChange={onChange}
            value={value}
        />
    );
}
