import React, { useState } from 'react';
import { notifications } from '@/js/Utils/Data';
import Axios from 'axios';

interface AiButtonProps {
    attachmentId: number;
    fieldType: 'title' | 'alt_text' | 'caption' | 'description' | 'filename';
    onSuccess: (value: string) => void;
}

export default function AiButton({ attachmentId, fieldType, onSuccess }: AiButtonProps) {
    const [loading, setLoading] = useState(false);
    const [suggestions, setSuggestions] = useState<string[]>([]);

    const handleClick = async () => {
        setSuggestions([]);
        setLoading(true);
        try {
            const body = new URLSearchParams({
                action: 'tsmlt_ai_generate',
                nonce: tsmltParams.tsmlt_wpnonce,
                params: JSON.stringify({ attachment_id: attachmentId, field_type: fieldType }),
            });
            const response = await Axios.post(tsmltParams.ajaxUrl, body);
            const envelope = response.data;
            if (envelope && envelope.success && Array.isArray(envelope.data?.suggestions) && envelope.data.suggestions.length) {
                if (envelope.data.suggestions.length === 1) {
                    onSuccess(envelope.data.suggestions[0]);
                } else {
                    setSuggestions(envelope.data.suggestions);
                }
            } else {
                const message = envelope?.data?.message || 'AI generation failed. Check your API key.';
                notifications(false, message);
            }
        } catch {
            notifications(false, 'AI generation failed. Check your API key.');
        } finally {
            setLoading(false);
        }
    };

    const handleSelect = (value: string) => {
        onSuccess(value);
        setSuggestions([]);
    };

    return (
        <div className="relative">
            <button
                type="button"
                onClick={handleClick}
                disabled={loading}
                title={`Generate ${fieldType.replace('_', ' ')} with AI`}
                className="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-purple-700 bg-purple-50 border border-purple-200 rounded hover:bg-purple-100 disabled:opacity-50 cursor-pointer"
            >
                {loading ? (
                    <svg className="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                ) : (
                    <span>✨</span>
                )}
                {loading ? 'Generating...' : 'AI'}
            </button>

            {suggestions.length > 1 && (
                <div className="absolute z-50 mt-1 right-0 w-80 bg-white border border-purple-200 rounded-md shadow-lg">
                    <div className="flex items-center justify-between px-3 py-2 border-b border-gray-100">
                        <span className="text-xs font-semibold text-purple-700">Select a suggestion</span>
                        <button
                            type="button"
                            onClick={() => setSuggestions([])}
                            className="text-gray-400 hover:text-gray-600 text-xs cursor-pointer"
                        >
                            ✕
                        </button>
                    </div>
                    {suggestions.map((s, i) => (
                        <button
                            key={i}
                            type="button"
                            onClick={() => handleSelect(s)}
                            className="block w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-purple-50 border-b border-gray-100 last:border-b-0 cursor-pointer"
                        >
                            <span className="font-semibold text-purple-600 mr-2">{i + 1}.</span>{s}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
