import React from 'react';

interface SearchInputProps {
    value?: string;
    placeholder?: string;
    onChange: React.ChangeEventHandler<HTMLInputElement>;
    onClear?: () => void;
}

export default function SearchInput({ value = '', placeholder = 'Keywords...', onChange, onClear }: SearchInputProps) {
    const hasValue = value.length > 0;

    return (
        <div className="relative group">
            {/* Search icon */}
            <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-blue-500 transition-colors duration-150 pointer-events-none">
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>

            <input
                type="text"
                value={value}
                className="pl-9! py-2! text-sm! text-gray-900! bg-white! border! border-gray-300! rounded-lg! shadow-none! placeholder-gray-400 transition-all duration-150 focus:outline-none! focus:border-blue-500! focus:ring-2! focus:ring-blue-500/20! focus:shadow-none! hover:border-gray-400!"
                style={{ paddingRight: hasValue ? '2rem' : '1rem' }}
                placeholder={placeholder}
                onChange={onChange}
            />

            {/* Clear button — visible only when there is text */}
            {hasValue && onClear && (
                <button
                    type="button"
                    aria-label="Clear search"
                    className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors duration-150 cursor-pointer"
                    onClick={onClear}
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            )}
        </div>
    );
}
