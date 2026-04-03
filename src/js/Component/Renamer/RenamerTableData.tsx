import { useEffect } from "react";
import { useParams } from 'react-router-dom';
import { defaultBulkSubmitData } from '@/js/Utils/UtilData';
import RenamerMainHeader from "./RenamerMainHeader";
import { useStore } from "@/js/Utils/store";
import type { MediaPost } from "@/js/Utils/store";
import Pagination from "@/js/Component/Common/Pagination";
import AiButton from "@/js/Component/Common/AiButton";
import { CopyToClipboard } from "@/js/Component/CopyToClipboard";
import * as Types from "@/js/Utils/actionType";
import MissingBadge from "@/js/Component/Badges/MissingBadge";

const theImage = (record: MediaPost) => {
    const typeParts = record.post_mime_type.split('/');
    const type = Array.isArray(typeParts) ? typeParts[0] : '';
    let url: string;
    switch (type) {
        case 'image':
            url = record.uploaddir + '/' + record.thefile.file;
            break;
        case 'audio':
            url = `${tsmltParams.includesUrl}/images/media/audio.png`;
            break;
        case 'video':
            url = `${tsmltParams.includesUrl}/images/media/video.png`;
            break;
        case 'application':
            if ('application/zip' === record.post_mime_type) {
                url = `${tsmltParams.includesUrl}/images/media/archive.png`;
            } else if ('application/pdf' === record.post_mime_type) {
                url = `${tsmltParams.includesUrl}/images/media/document.png`;
            } else {
                url = `${tsmltParams.includesUrl}/images/media/text.png`;
            }
            break;
        default:
            url = `${tsmltParams.includesUrl}/images/media/text.png`;
    }
    return <img className="w-full h-full object-cover" src={url} alt={record.post_mime_type} />;
};

function RenamerTableData() {
    const { mediaData, setMediaData, bulkSubmitData, setBulkSubmitData, rename, setRename, setSaveType } = useStore();
    const { page: pageParam } = useParams<{ page?: string }>();

    const handlePagination = (current: number) => {
        setMediaData({
            postQuery: {
                ...mediaData.postQuery,
                paged: current,
                orderby: 'id',
                order: 'DESC',
            }
        });
        setBulkSubmitData(defaultBulkSubmitData);
    };

    useEffect(() => {
        const pageFromUrl = parseInt(pageParam || '1', 10);
        if (pageFromUrl !== (mediaData.postQuery.paged || 1)) {
            handlePagination(pageFromUrl);
        }
    }, [pageParam]);

    const setRenamerMainQuery = () => {
        if (mediaData.postQuery.filtering) {
            setMediaData({
                postQuery: {
                    status: null,
                    filtering: false,
                    media_per_page: mediaData.postQuery.media_per_page,
                    searchKeyWords: null,
                    order: 'DESC',
                    orderby: 'id',
                    paged: 1,
                    isUpdate: false,
                }
            });
        }
    };

    useEffect(() => {
        setRenamerMainQuery();
    }, []);

    const onBulkCheck = (event: React.ChangeEvent<HTMLInputElement>) => {
        const postsId = event.target.checked ? posts.map(item => item.ID) : [];
        setBulkSubmitData({
            bulkChecked: !!postsId.length,
            progressTotal: postsId.length,
            ids: postsId,
        });
    };

    const onCheckboxChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const value = parseInt(event.target.value, 10);
        const changeData = event.target.checked
            ? [...bulkSubmitData.ids, value]
            : bulkSubmitData.ids.filter(item => item !== value);
        setBulkSubmitData({
            bulkChecked: !!(changeData.length && changeData.length === posts.length),
            ids: changeData,
            progressTotal: changeData.length,
        });
    };

    const totalPosts = mediaData.total_post || 0;
    const postsPerPage = mediaData.posts_per_page || 20;
    const currentPage = mediaData.paged || 1;
    const totalPages = Math.ceil(totalPosts / postsPerPage);
    const posts = mediaData.posts || [];

    const isLoading = mediaData.isLoading || mediaData.total_post < 0;

    return (
        <div className="min-h-screen bg-gray-50">
            <RenamerMainHeader />
            <div className="p-3">
                {isLoading ? (
                    <div className="space-y-3">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <div key={i} className="bg-white rounded-lg border border-gray-200 p-4 animate-pulse">
                                <div className="flex items-center gap-4">
                                    <div className="w-4 h-4 bg-gray-200 rounded" />
                                    <div className="w-14 h-14 bg-gray-200 rounded-lg" />
                                    <div className="flex-1 space-y-2">
                                        <div className="h-4 bg-gray-200 rounded w-1/3" />
                                        <div className="h-3 bg-gray-200 rounded w-2/3" />
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : posts.length === 0 ? (
                    <div className="text-center py-16 bg-white rounded-lg border border-gray-200">
                        <svg className="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        <p className="text-gray-500 text-sm">No media files found.</p>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {/* Select all */}
                        <div className="flex items-center gap-3 px-4 py-2 bg-white rounded-lg border border-gray-200">
                            <input
                                type="checkbox"
                                className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                ref={(el) => { if (el) el.indeterminate = bulkSubmitData.ids.length > 0 && !bulkSubmitData.bulkChecked; }}
                                checked={bulkSubmitData.bulkChecked}
                                onChange={onBulkCheck}
                            />
                            <span className="text-sm text-gray-600">
                                {bulkSubmitData.ids.length > 0
                                    ? `${bulkSubmitData.ids.length} selected`
                                    : 'Select all'}
                            </span>
                        </div>

                        {posts.map((record, i) => {
                            const parent = record.post_parents;
                            const fullUrl = `${record.uploaddir}/${record.thefile.file}`;

                            return (
                                <div key={record.ID} className="bg-white rounded-lg border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                                    <div className="flex items-center gap-4 p-4">
                                        {/* Checkbox */}
                                        <input
                                            type="checkbox"
                                            className="w-4 h-4 shrink-0 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                            checked={bulkSubmitData.ids.includes(record.ID)}
                                            name="item_id"
                                            value={record.ID}
                                            onChange={onCheckboxChange}
                                        />

                                        {/* Thumbnail */}
                                        <div className="shrink-0 w-14 h-14 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
                                            {theImage(record)}
                                        </div>

                                        {/* Content */}
                                        <div className="flex-1 min-w-0">
                                            {/* File name */}
                                            {rename.formEdited ? (
                                                <div className="flex items-center gap-1.5 mb-1">
                                                    <div className="relative flex-1 max-w-full">
                                                        <input
                                                            type="text"
                                                            className="w-full min-h-auto h-8.5! pl-13 pr-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                            name="filebasename"
                                                            placeholder="File name"
                                                            data-current={i}
                                                            onBlur={() => setSaveType(Types.UPDATE_RENAMER_MEDIA)}
                                                            onChange={(event: React.ChangeEvent<HTMLInputElement>) => {
                                                                const currentItem = parseInt(event.target.getAttribute('data-current') ?? '0', 10);
                                                                const updatedPosts = [...mediaData.posts];
                                                                const pnlname = { ...updatedPosts[currentItem].thefile };
                                                                updatedPosts[currentItem] = {
                                                                    ...updatedPosts[currentItem],
                                                                    thefile: { ...updatedPosts[currentItem].thefile, filebasename: event.target.value },
                                                                };
                                                                setMediaData({ posts: updatedPosts });
                                                                setRename({
                                                                    postsdata: pnlname,
                                                                    ID: updatedPosts[currentItem].ID,
                                                                    newname: event.target.value,
                                                                });
                                                            }}
                                                            value={record.thefile.filebasename}
                                                        />
                                                        <AiButton
                                                            className="absolute left-0 top-0 bottom-0 ring-0"
                                                            attachmentId={record.ID}
                                                            fieldType="filename"
                                                            onSuccess={(value) => {
                                                                const updatedPosts = [...mediaData.posts];
                                                                const pnlname = { ...updatedPosts[i].thefile };
                                                                updatedPosts[i] = {
                                                                    ...updatedPosts[i],
                                                                    thefile: { ...updatedPosts[i].thefile, filebasename: value },
                                                                };
                                                                setMediaData({ posts: updatedPosts });
                                                                setRename({
                                                                    postsdata: pnlname,
                                                                    ID: record.ID,
                                                                    newname: value,
                                                                });
                                                                setSaveType(Types.UPDATE_RENAMER_MEDIA);
                                                            }}
                                                        />
                                                    </div>
                                                    <span className="text-sm text-gray-500">.{record.thefile.fileextension}</span>
                                                </div>
                                            ) : (
                                                <a
                                                    className="text-sm font-medium text-blue-600 hover:text-blue-700 truncate block max-w-md"
                                                    target="_blank"
                                                    href={fullUrl}
                                                >
                                                    {record.thefile.mainfilename}
                                                </a>
                                            )}

                                            {/* URL */}
                                            <div className="flex items-center gap-1.5 mt-0!">
                                                <code className="text-xs text-gray-500 bg-gray-50 pl-0! pr-2 py-0.5 rounded break-all">
                                                    {fullUrl}
                                                </code>
                                                <CopyToClipboard text={fullUrl} />
                                            </div>
                                            <div className="flex items-center gap-1.5 mt-0!">
                                                <div className={'flex items-center'}>
                                                    Attached Post :
                                                    {parent?.title ? (
                                                            <a
                                                                target="_blank"
                                                                href={parent.permalink}
                                                                className="inline-flex items-center px-2 py-1 text-xs font-medium text-purple-700 bg-purple-50 rounded hover:bg-purple-100 transition-colors"
                                                            >
                                                                {parent.title}
                                                            </a>
                                                        ) : <MissingBadge />
                                                    }
                                                </div>
                                            </div>
                                        </div>

                                        {/* Right side info */}
                                        <div className="shrink-0 flex items-center gap-3">
                                            <span className="text-xs text-gray-400">ID: #{record.ID}</span>
                                            {record.title && (
                                                <span className="flex text-sm text-gray-600 max-w-40 truncate">Title: {record.title}</span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}

                        <Pagination
                            currentPage={currentPage}
                            totalPages={totalPages}
                            totalPosts={totalPosts}
                            postsPerPage={postsPerPage}
                            onPageChange={handlePagination}
                        />
                    </div>
                )}
            </div>
        </div>
    );
}

export default RenamerTableData;
