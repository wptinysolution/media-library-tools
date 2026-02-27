import { useStore } from "@/js/Utils/store";
import { localStoreData } from "@/js/Utils/UtilData";
import Modal from "@/js/Component/Common/Modal";

function RubbishNotice() {
    const { rubbishMedia, setRubbishMedia } = useStore();

    const handleNoticeModalHide = () => {
        setRubbishMedia({ showRubbishNotice: false });
    };

    return (
        <Modal
            isOpen={rubbishMedia.showRubbishNotice}
            onClose={handleNoticeModalHide}
            title="Notice"
            maxWidth="max-w-[950px]"
        >
            <div className="px-6 py-5 h-125 overflow-y-auto">
                <h5 className="text-[15px] font-semibold text-red-600 mb-4">
                    Important Notice: Prioritize data safety. Always back up files before deletion to avoid irreversible loss.
                </h5>
                <hr className="border-gray-200 my-3" />

                <h5 className="text-[15px] font-semibold text-green-600 mb-4">What is Rubbish File?</h5>
                <p className="text-sm text-gray-700 mt-0! mb-3">
                    "Rubbish File" is a file that physically exists within a directory but is excluded from being indexed or included in the media library or database of an application or system.
                </p>
                <hr className="border-gray-200 my-3" />

                <h5 className="text-[15px] font-semibold text-green-600 mb-4">Why Need Delete Rubbish File?</h5>
                <p className="text-sm text-gray-700 mb-2">
                    <strong>Freeing up storage space</strong>: Delete rubbish files to create more available storage space on your device.
                </p>
                <p className="text-sm text-gray-700 mb-2">
                    <strong>Improving performance</strong>: Removing unnecessary files can lead to a faster and more efficient system.
                </p>
                <p className="text-sm text-gray-700 mb-2">
                    <strong>Enhancing security and organization</strong>: Deleting rubbish files can help keep your data secure and your file system organized.
                </p>
                <hr className="border-gray-200 my-3" />

                <h5 className="text-[15px] font-semibold text-green-600 mb-4">Delete File Can be Restore?</h5>
                <p className="text-sm text-gray-700 mb-2">
                    No. You can't get back that file.
                    <span> That's Why Before deleting any file search this file in you media library
                    And Re-check the url and be sure before deleting.</span>
                </p>
                <p className="text-sm text-gray-700 mt-0! mb-3">
                    Thank you for your cooperation and understanding.
                </p>
                <hr className="border-gray-200 my-3" />

                <label className="inline-flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                        onChange={(event) => event.target.checked ? localStoreData("showRubbishNotice", 'disable') : localStorage.removeItem("showRubbishNotice")}
                    />
                    <span className="text-sm text-green-600">Hide Notice For Today</span>
                </label>
            </div>
        </Modal>
    );
}

export default RubbishNotice;
