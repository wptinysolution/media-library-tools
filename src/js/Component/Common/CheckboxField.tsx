import React from 'react';
import ProLabel from "@/js/Component/Badges/ProLabel";

interface CheckboxFieldProps {
    name?: string;
    value?: string;
    checked: boolean;
    onChange: React.ChangeEventHandler<HTMLInputElement>;
    label: React.ReactNode;
    isPro?: boolean;
}

export default function CheckboxField({ name, value, checked, onChange, label, isPro = false }: CheckboxFieldProps) {
    return (
        <label className={`inline-flex items-center gap-3 group ${isPro ? 'cursor-pointer opacity-75' : 'cursor-pointer'}`}>
            <span className={`relative shrink-0 w-4.5 h-4.5 rounded! border-2 transition-all duration-150 ${
                checked
                    ? 'bg-blue-600! border-blue-600! shadow-sm'
                    : 'bg-white! border-gray-300! group-hover:border-blue-400!'
            }`}>
                {checked && (
                    <svg
                        className="absolute inset-0 w-full h-full p-0.5 text-white"
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
                <input
                    type="checkbox"
                    className="absolute! inset-0! w-full! h-full! opacity-0! m-0! p-0! border-0! cursor-pointer"
                    onChange={onChange}
                    name={name}
                    value={value}
                    checked={checked}
                />
            </span>

            <span className="text-sm text-gray-700 leading-snug select-none">
                {label}
                {isPro && <ProLabel /> }
            </span>
        </label>
    );
}
