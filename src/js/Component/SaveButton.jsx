import React, { useState } from 'react';
import { useStateValue } from '@/js/Utils/StateProvider';
import * as Types from "@/js/Utils/actionType";

function SaveButton({
                        loadingText = 'Saving...',
                        defaultText = 'Save Settings',
                        position = 'fixed',
                        className = '',
                        disabled = false
                    }) {
    const [stateValue, dispatch] = useStateValue();
    const [isLoading, setIsLoading] = useState(false);
    const [isSaved, setIsSaved] = useState(false);

    const baseClasses = position === 'fixed'
        ? 'fixed bottom-8 right-8 z-50'
        : '';

    const onClick = async () => {
        setIsLoading(true);
        setIsSaved(false);

        // Trigger save
        await dispatch({
            ...stateValue,
            type: Types.UPDATE_OPTIONS,
            saveType: Types.UPDATE_OPTIONS,
        });

        setIsLoading(false);
        setIsSaved(true);

        // Reset saved state after 2 seconds
        setTimeout(() => {
            setIsSaved(false);
        }, 2000);
    };

    return (
        <button
            onClick={onClick}
            disabled={disabled || isLoading}
            className={`
                ${baseClasses}
                group
                cursor-pointer
                ${isSaved
                ? 'bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700'
                : 'bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800'
            }
                disabled:from-blue-400
                disabled:to-blue-500
                disabled:cursor-not-allowed
                text-white
                font-semibold
                py-3.5
                px-7
                min-w-[180px]
                justify-center
                rounded-xl
                text-base
                shadow-lg
                ${isSaved ? 'shadow-green-500/30' : 'shadow-blue-500/30'}
                hover:shadow-xl
                hover:scale-105
                active:scale-100
                transition-all
                duration-200
                flex
                items-center
                gap-2.5
                ${className}
            `.trim()}
        >
            {isLoading ? (
                <>
                    <svg
                        className="animate-spin h-5 w-5 text-white"
                        viewBox="0 0 24 24"
                        fill="none"
                    >
                        <circle
                            className="opacity-30"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            strokeWidth="3"
                        />
                        <path
                            d="M22 12a10 10 0 00-10-10"
                            stroke="currentColor"
                            strokeWidth="3"
                            strokeLinecap="round"
                        />
                    </svg>
                    <span>{loadingText}</span>
                </>
            ) : isSaved ? (
                <>
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Saved!</span>
                </>
            ) : (
                <>
                    <svg className="w-5 h-5 transition-transform group-hover:-translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                    <span>{defaultText}</span>
                </>
            )}
        </button>
    );
}

export default SaveButton;