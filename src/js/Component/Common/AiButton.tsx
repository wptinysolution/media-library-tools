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

    const handleClick = async () => {
        setLoading(true);
        try {
            const body = new URLSearchParams({
                action: 'tsmlt_ai_generate',
                nonce: tsmltParams.tsmlt_wpnonce,
                params: JSON.stringify({ attachment_id: attachmentId, field_type: fieldType }),
            });
            const response = await Axios.post(tsmltParams.ajaxUrl, body);
            const envelope = response.data;
            if (envelope && envelope.success && envelope.data?.text) {
                onSuccess(envelope.data.text);
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

    return (
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
    );
}
