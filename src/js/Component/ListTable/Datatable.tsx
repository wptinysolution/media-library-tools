import { useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { defaultBulkSubmitData } from '@/js/Utils/UtilData';
import Loader from "@/js/Utils/Loader";
import TheHeader from "@/js/Component/ListTable/TheHeader";
import { useStore } from "@/js/Utils/store";
import type { MediaPost } from "@/js/Utils/store";
import BulkModal from "@/js/Component/ListTable/BulkModal";
import BulkModalForCSV from "@/js/Component/ListTable/BulkModalForCSV";
import Pagination from "@/js/Component/Common/Pagination";
import AiButton from "@/js/Component/Common/AiButton";
import * as Types from "@/js/Utils/actionType";

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

export default function Datatable() {
    const {
        mediaData, setMediaData,
        singleMedia, setSingleMedia,
        bulkSubmitData, setBulkSubmitData,
        options, setOptions,
        bulkExport, generalData,
        setSaveType,
    } = useStore();
    const { page: pageParam } = useParams<{ page?: string }>();

    const handlePagination = (current: number) => {
        setMediaData({
            isLoading: true,
            postQuery: {
                ...mediaData.postQuery,
                paged: current,
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

    const renderModal = () => {
        if (bulkSubmitData.isModalOpen) return <BulkModal />;
        if (bulkExport.isModalOpen) return <BulkModalForCSV />;
        return null;
    };

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
        });
    };

    const handleChange = (event: React.ChangeEvent<HTMLTextAreaElement>, currentItem: number) => {
        const posts = [...mediaData.posts];
        const currentData: Record<string, unknown> = {
            ID: posts[currentItem].ID,
            [event.target.name]: event.target.value.trim(),
        };
        posts[currentItem] = { ...posts[currentItem], [event.target.name]: event.target.value };
        setMediaData({ posts, isLoading: false });
        setSingleMedia({
            alt_text: null,
            post_content: null,
            post_excerpt: null,
            post_title: null,
            ...currentData,
        });
    };

    const handleFocusout = () => {
        setSaveType(Types.UPDATE_SINGLE_MEDIA);
    };

    const totalPosts = mediaData.total_post || 0;
    const postsPerPage = mediaData.posts_per_page || 20;
    const currentPage = mediaData.paged || 1;
    const totalPages = Math.ceil(totalPosts / postsPerPage);
    const posts = mediaData.posts || [];
    const formEdited = singleMedia.formEdited;

    return (
        <div className="min-h-screen bg-gray-50">
            <TheHeader />
            {generalData.isLoading ? <Loader /> : (
                <>
                    {/* Per page control */}
                    <div className="mx-3 mt-3 px-4 py-3 bg-white border border-gray-200 rounded-lg flex flex-wrap items-center gap-x-3 gap-y-2">
                        <label className="inline-flex items-center gap-1.5 cursor-pointer">
                            <span className="text-sm font-medium text-gray-900 whitespace-nowrap">Per page:</span>
                            <input
                                type="number"
                                className="w-16 px-2! py-1.5! text-sm! text-gray-900! bg-white! border! border-gray-300! rounded-md! shadow-none! focus:outline-none! focus:border-blue-500! focus:ring-2! focus:ring-blue-500/20! focus:shadow-none! hover:border-gray-400!"
                                value={options.media_per_page as number | string}
                                onChange={(event) => { localStorage.setItem('mlt_media_per_page', event.target.value); setOptions({ media_per_page: event.target.value }); }}
                                onBlur={() => setMediaData({ postQuery: { ...mediaData.postQuery, media_per_page: parseInt(String(options.media_per_page || 20), 10), paged: 1 } })}
                            />
                        </label>
                    </div>

                    {/* Card list */}
                    <div className="p-3">
                        {mediaData.isLoading ? (
                            <div className="space-y-3">
                                {Array.from({ length: 5 }).map((_, i) => (
                                    <div key={i} className="bg-white rounded-lg border border-gray-200 p-4 animate-pulse">
                                        <div className="flex items-start gap-4">
                                            <div className="w-4 h-4 bg-gray-200 rounded mt-1" />
                                            <div className="w-16 h-16 bg-gray-200 rounded-lg" />
                                            <div className="flex-1 space-y-2">
                                                <div className="h-4 bg-gray-200 rounded w-1/3" />
                                                <div className="h-3 bg-gray-200 rounded w-1/2" />
                                                <div className="h-3 bg-gray-200 rounded w-2/3" />
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : posts.length === 0 ? (
                            <div className="text-center py-16 bg-white rounded-lg border border-gray-200">
                                <svg className="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
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
                                    let categories: Array<{ id?: string | number; name?: string }> = [];
                                    try {
                                        categories = JSON.parse(record.categories);
                                    } catch { /* ignore */ }

                                    return (
                                        <div key={record.ID} className="bg-white rounded-lg border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                                            <div className="flex items-start gap-4 p-4">
                                                {/* Checkbox */}
                                                <input
                                                    type="checkbox"
                                                    className="w-4 h-4 mt-1 shrink-0 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                                    checked={bulkSubmitData.ids.includes(record.ID)}
                                                    name="item_id"
                                                    value={record.ID}
                                                    onChange={onCheckboxChange}
                                                />

                                                {/* Thumbnail */}
                                                <div className="shrink-0 w-16 h-16 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
                                                    {theImage(record)}
                                                </div>

                                                {/* Content */}
                                                <div className="flex-1 min-w-0 space-y-2">
                                                    {/* Title row */}
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-xs text-gray-400">#{record.ID}</span>
                                                        {formEdited ? (
                                                            <div className="relative flex-1 max-w-sm">
                                                                <textarea
                                                                    className="w-full pl-13 pr-2 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                                                                    rows={1}
                                                                    name="title"
                                                                    placeholder="Title"
                                                                    data-current={i}
                                                                    onBlur={handleFocusout}
                                                                    onChange={(e) => handleChange(e, i)}
                                                                    value={record.title}
                                                                />
                                                                <AiButton
                                                                    className="absolute left-1.5 top-1.5"
                                                                    attachmentId={record.ID}
                                                                    fieldType="title"
                                                                    onSuccess={(value) => {
                                                                        const updatedPosts = [...mediaData.posts];
                                                                        updatedPosts[i] = { ...updatedPosts[i], title: value };
                                                                        setMediaData({ posts: updatedPosts });
                                                                        setSingleMedia({ alt_text: null, post_content: null, post_excerpt: null, post_title: null, ID: record.ID, title: value });
                                                                        setSaveType(Types.UPDATE_SINGLE_MEDIA);
                                                                    }}
                                                                />
                                                            </div>
                                                        ) : (
                                                            <a className="text-sm font-medium text-gray-900 truncate" target="_blank" href={`${record.uploaddir}/${record.thefile.file}`}>
                                                                {record.title}
                                                            </a>
                                                        )}
                                                    </div>

                                                    {/* Alt text */}
                                                    {formEdited ? (
                                                        <div className="relative max-w-sm">
                                                            <textarea
                                                                className="w-full pl-13 pr-2 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                                                                rows={1}
                                                                name="alt_text"
                                                                placeholder="Alt Text"
                                                                data-current={i}
                                                                onBlur={handleFocusout}
                                                                onChange={(e) => handleChange(e, i)}
                                                                value={record.alt_text}
                                                            />
                                                            <AiButton
                                                                className="absolute left-1.5 top-1.5"
                                                                attachmentId={record.ID}
                                                                fieldType="alt_text"
                                                                onSuccess={(value) => {
                                                                    const updatedPosts = [...mediaData.posts];
                                                                    updatedPosts[i] = { ...updatedPosts[i], alt_text: value };
                                                                    setMediaData({ posts: updatedPosts });
                                                                    setSingleMedia({ alt_text: value, post_content: null, post_excerpt: null, post_title: null, ID: record.ID });
                                                                    setSaveType(Types.UPDATE_SINGLE_MEDIA);
                                                                }}
                                                            />
                                                        </div>
                                                    ) : record.alt_text ? (
                                                        <p className="text-xs text-gray-500 m-0!">
                                                            <span className="text-gray-400">Alt:</span> {record.alt_text}
                                                        </p>
                                                    ) : null}

                                                    {/* Caption */}
                                                    {formEdited ? (
                                                        <div className="relative max-w-sm">
                                                            <textarea
                                                                className="w-full pl-13 pr-2 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                                                                rows={1}
                                                                name="caption"
                                                                placeholder="Caption"
                                                                data-current={i}
                                                                onBlur={handleFocusout}
                                                                onChange={(e) => handleChange(e, i)}
                                                                value={record.caption}
                                                            />
                                                            <AiButton
                                                                className="absolute left-1.5 top-1.5"
                                                                attachmentId={record.ID}
                                                                fieldType="caption"
                                                                onSuccess={(value) => {
                                                                    const updatedPosts = [...mediaData.posts];
                                                                    updatedPosts[i] = { ...updatedPosts[i], caption: value };
                                                                    setMediaData({ posts: updatedPosts });
                                                                    setSingleMedia({ alt_text: null, post_content: null, post_excerpt: null, post_title: null, ID: record.ID, caption: value });
                                                                    setSaveType(Types.UPDATE_SINGLE_MEDIA);
                                                                }}
                                                            />
                                                        </div>
                                                    ) : record.caption ? (
                                                        <p className="text-xs text-gray-500 m-0!">
                                                            <span className="text-gray-400">Caption:</span> {record.caption}
                                                        </p>
                                                    ) : null}

                                                    {/* Description */}
                                                    {formEdited ? (
                                                        <div className="relative max-w-sm">
                                                            <textarea
                                                                className="w-full pl-13 pr-2 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                                                                rows={1}
                                                                name="description"
                                                                placeholder="Description"
                                                                data-current={i}
                                                                onBlur={handleFocusout}
                                                                onChange={(e) => handleChange(e, i)}
                                                                value={record.description}
                                                            />
                                                            <AiButton
                                                                className="absolute left-1.5 top-1.5"
                                                                attachmentId={record.ID}
                                                                fieldType="description"
                                                                onSuccess={(value) => {
                                                                    const updatedPosts = [...mediaData.posts];
                                                                    updatedPosts[i] = { ...updatedPosts[i], description: value };
                                                                    setMediaData({ posts: updatedPosts });
                                                                    setSingleMedia({ alt_text: null, post_content: null, post_excerpt: null, post_title: null, ID: record.ID, description: value });
                                                                    setSaveType(Types.UPDATE_SINGLE_MEDIA);
                                                                }}
                                                            />
                                                        </div>
                                                    ) : record.description ? (
                                                        <p className="text-xs text-gray-500 m-0!">
                                                            <span className="text-gray-400">Desc:</span> {record.description}
                                                        </p>
                                                    ) : null}
                                                </div>

                                                {/* Right side info */}
                                                <div className="shrink-0 flex flex-col items-end gap-2">
                                                    {parent?.title && (
                                                        <a
                                                            target="_blank"
                                                            href={parent.permalink}
                                                            className="inline-flex items-center px-2 py-1 text-xs font-medium text-purple-700 bg-purple-50 rounded hover:bg-purple-100 transition-colors"
                                                        >
                                                            {parent.title}
                                                        </a>
                                                    )}
                                                    {categories.length > 0 && (
                                                        <div className="flex flex-wrap gap-1 justify-end">
                                                            {categories.map(item => item.id && (
                                                                <span key={String(item.id)} className="inline-flex px-2 py-0.5 text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-200 rounded-full">
                                                                    {item.name}
                                                                </span>
                                                            ))}
                                                        </div>
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
                    {renderModal()}
                </>
            )}
        </div>
    );
}
