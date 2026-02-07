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
            className={`${baseClasses} px-8 py-3 bg-blue-600 text-white text-sm font-medium rounded-lg shadow-lg hover:bg-blue-700 hover:shadow-xl focus:ring-4 focus:ring-blue-500 focus:ring-offset-2 transition-all disabled:opacity-50 disabled:cursor-not-allowed ${className}`}
            onClick={onClick}
            disabled={disabled || isLoading}
        >
            {isLoading ? (
                <span className="flex items-center gap-2">
                    <svg className="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {loadingText}
                </span>
            ) : isSaved ? (
                <span className="flex items-center gap-2">
                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    Saved!
                </span>
            ) : (
                defaultText
            )}
        </button>
    );
}

export default SaveButton;