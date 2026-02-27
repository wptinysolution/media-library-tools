import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';

interface ModalProps {
    isOpen: boolean;
    onClose: () => void;
    title: React.ReactNode;
    maxWidth?: string;
    children?: React.ReactNode;
    footer?: React.ReactNode;
    closeOnBackdrop?: boolean;
}

export default function Modal({ isOpen, onClose, title, maxWidth = 'max-w-[650px]', children, footer, closeOnBackdrop = true }: ModalProps) {

    useEffect(() => {
        if (!isOpen) return;
        const handleEsc = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
        };
        document.addEventListener('keydown', handleEsc);
        return () => document.removeEventListener('keydown', handleEsc);
    }, [isOpen, onClose]);

    if (!isOpen) return null;

    return createPortal(
        <div className="fixed inset-0 flex items-center justify-center" style={{ zIndex: 999999 }}>
            <div
                className="absolute inset-0 bg-black/45"
                onClick={closeOnBackdrop ? onClose : undefined}
            />
            <div className={`relative bg-white rounded-lg shadow-xl w-full ${maxWidth} mx-4`}>
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    {typeof title === 'string' ? (
                        <h3 className="text-lg font-semibold text-gray-900 m-0!">{title}</h3>
                    ) : title}
                    <button
                        type="button"
                        className="p-1 text-gray-400 hover:text-gray-600 cursor-pointer transition-colors"
                        onClick={onClose}
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {children}
                {footer}
            </div>
        </div>,
        document.body
    );
}
