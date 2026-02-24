import React, { useState } from 'react';

interface CopyToClipboardProps {
    text: string;
    resetDelay?: number;
    className?: string;
}

export function CopyToClipboard({ text, resetDelay = 2000, className = '' }: CopyToClipboardProps) {
    const [copied, setCopied] = useState(false);

    const handleCopy = async (event: React.MouseEvent) => {
        event.preventDefault();
        event.stopPropagation();

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }

            setCopied(true);
            setTimeout(() => setCopied(false), resetDelay);
        } catch (error) {
            console.error('Copy failed:', error);
        }
    };

    return (
        <button
            type="button"
            onClick={handleCopy}
            className={`p-1.5 cursor-pointer hover:bg-gray-300 rounded transition-colors flex-shrink-0 ${className}`}
            title="Copy to clipboard"
        >
            {copied ? (
                <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                </svg>
            ) : (
                <svg className="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
            )}
        </button>
    );
}
