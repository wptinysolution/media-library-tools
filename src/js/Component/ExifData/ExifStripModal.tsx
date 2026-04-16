import Modal from "@/js/Component/Common/Modal";

interface ExifStripModalProps {
    isOpen: boolean;
    selectedCount: number;
    onClose: () => void;
    onConfirm: () => void;
}

export default function ExifStripModal({ isOpen, selectedCount, onClose, onConfirm }: ExifStripModalProps) {
    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title={
                <div className="flex items-center gap-2">
                    <div className="flex items-center justify-center w-8 h-8 rounded-full bg-red-100 shrink-0">
                        <svg className="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                    </div>
                    <h3 className="text-lg font-semibold text-gray-900 m-0!">Delete EXIF from Selected Images</h3>
                </div>
            }
            maxWidth="max-w-[520px]"
            closeOnBackdrop={false}
            footer={
                <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                    <button
                        type="button"
                        className="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                        onClick={onClose}
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        className="px-5 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 cursor-pointer transition-colors"
                        onClick={onConfirm}
                    >
                        Yes, Delete EXIF from {selectedCount} Image{selectedCount !== 1 ? 's' : ''}
                    </button>
                </div>
            }
        >
            <div className="px-6 py-5 space-y-4">
                <p className="text-sm text-gray-700 mt-0!">
                    You are about to delete EXIF metadata from <strong>{selectedCount} image{selectedCount !== 1 ? 's' : ''}</strong>.
                </p>
                <div className="bg-amber-50 border border-amber-200 rounded-md px-4 py-3">
                    <p className="text-sm font-semibold text-amber-800 m-0! mb-1">This action cannot be undone.</p>
                    <p className="text-xs text-amber-700 m-0!">EXIF data will be permanently removed from the original image files on your server.</p>
                </div>
            </div>
        </Modal>
    );
}
