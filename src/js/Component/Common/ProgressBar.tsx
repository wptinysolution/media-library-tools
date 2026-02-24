import React from 'react';

interface ProgressBarProps {
    percent: number;
}

export default function ProgressBar({ percent }: ProgressBarProps) {
    return (
        <div className="w-full bg-gray-200 rounded-full h-5 overflow-hidden">
            <div
                className="bg-blue-600 h-5 rounded-full transition-all duration-300 flex items-center justify-center"
                style={{ width: `${percent}%` }}
            >
                <span className="text-xs font-medium text-white">{percent}%</span>
            </div>
        </div>
    );
}
