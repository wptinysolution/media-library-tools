import React from "react";

export default function SearchInput({ placeholder = 'Keywords...', onChange }) {
    return (
        <div className="relative">
            <input
                type="text"
                className="px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder={placeholder}
                onChange={onChange}
            />
            <svg
                className="w-5 h-5 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
            </svg>
        </div>
    );
}
