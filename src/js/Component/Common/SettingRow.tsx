import React from 'react';

interface SettingRowProps {
    label: string;
    children: React.ReactNode;
    bordered?: boolean;
}

export default function SettingRow({ label, children, bordered = false }: SettingRowProps) {
    return (
        <div className={`flex items-start gap-8 ${bordered ? 'pt-6 border-t border-gray-100' : ''}`}>
            <label className="text-sm font-semibold text-gray-700 whitespace-nowrap pt-1 min-w-50">
                {label}
            </label>
            <div className="flex-1 space-y-3">
                {children}
            </div>
        </div>
    );
}
