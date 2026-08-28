import React from "react";
import { useStore } from "@/js/Utils/store";
import type { MediaPost, BulkSubmitData } from "@/js/Utils/store";
import * as Types from "@/js/Utils/actionType";
import AiButton from "@/js/Component/Common/AiButton";
import MediaThumbnail from "@/js/Component/Common/MediaThumbnail";

export interface ColumnDef<T = Record<string, unknown>> {
    title: React.ReactNode;
    key: string;
    dataIndex: string;
    width?: string;
    minWidth?: number;
    align?: 'left' | 'center' | 'top';
    fixed?: boolean;
    render?: (value: unknown, record: T, index: number) => React.ReactNode;
}

export const headerStyle: React.CSSProperties = {
    height: 'auto',
    paddingInline: 0,
    lineHeight: '1',
    backgroundColor: '#fff',
    padding: '15px 0'
};

export const defaultBulkSubmitData: BulkSubmitData = {
    bulkChecked: false,
    isModalOpen: false,
    progressBar: 0,
    progressTotal: 0,
    ids: [],
    type: '',
    data: {
        post_title: '',
        alt_text: '',
        caption: '',
        post_description: '',
        file_name: '',
    },
    will_attached_post_title: [],
    post_categories: [],
    post_categories_mode: 'add',
};

const theImage = (record: MediaPost): React.ReactElement => (
    <MediaThumbnail
        url={`${record.uploaddir}/${record.thefile.file}`}
        mimeType={record.post_mime_type}
        fileName={record.thefile?.file}
        alt={record.post_mime_type}
        width={80}
    />
);

const SortIcon = () => (
    <svg className="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
    </svg>
);

export function columns(): ColumnDef<MediaPost>[] {
    const { mediaData, setMediaData, singleMedia, setSingleMedia, bulkSubmitData, setBulkSubmitData, setSaveType } = useStore();

    const onCheckboxChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const value = parseInt(event.target.value, 10);
        const changeData = event.target.checked
            ? [...bulkSubmitData.ids, value]
            : bulkSubmitData.ids.filter(item => item !== value);

        const checkedCount = changeData.length;
        const postCount = mediaData.posts.length;

        setBulkSubmitData({
            bulkChecked: !!(checkedCount && checkedCount === postCount),
            ids: changeData,
        });
    };

    const onBulkCheck = (event: React.ChangeEvent<HTMLInputElement>) => {
        const postsId = event.target.checked ? mediaData.posts.map(item => item.ID) : [];
        setBulkSubmitData({
            bulkChecked: !!postsId.length,
            progressTotal: postsId.length,
            ids: postsId,
        });
    };

    const handleSortClick = (odrby: string) => {
        const { orderby, order } = mediaData.postQuery;
        setMediaData({
            postQuery: {
                ...mediaData.postQuery,
                orderby: odrby,
                paged: 1,
                order: odrby === orderby && 'DESC' === order ? 'ASC' : 'DESC',
            }
        });
    };

    const handleChange = (event: React.ChangeEvent<HTMLTextAreaElement>) => {
        const currentItem = parseInt(event.target.getAttribute('data-current') ?? '0', 10);
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

    const formEdited = singleMedia.formEdited;
    const hasIds = bulkSubmitData.ids.length > 0;


    return [
        {
            title: (
                <input
                    type="checkbox"
                    className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                    ref={(el) => { if (el) el.indeterminate = hasIds && !bulkSubmitData.bulkChecked; }}
                    checked={bulkSubmitData.bulkChecked}
                    onChange={onBulkCheck}
                />
            ),
            key: 'CheckboxID',
            dataIndex: 'ID',
            width: '50px',
            align: 'center',
            fixed: true,
            render: (id) => (
                <input
                    type="checkbox"
                    className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                    checked={-1 !== bulkSubmitData.ids.indexOf(id as number)}
                    name="item_id"
                    value={id as number}
                    onChange={onCheckboxChange}
                />
            ),
        },
        {
            title: (
                <button className="inline-flex items-center gap-1.5 group cursor-pointer hover:text-blue-600 transition-colors" onClick={() => handleSortClick('id')}>
                    ID <SortIcon />
                </button>
            ),
            key: 'ID',
            dataIndex: 'ID',
            width: '150px',
            align: 'top',
        },
        {
            title: 'File',
            key: 'Image',
            dataIndex: 'guid',
            width: '150px',
            align: 'top',
            render: (_text, record) => <span className="inline-flex items-center">{theImage(record)}</span>,
        },
        {
            title: (
                <button className="inline-flex items-center gap-1.5 group cursor-pointer hover:text-blue-600 transition-colors" onClick={() => handleSortClick('post_parents')}>
                    Attached Post<SortIcon />
                </button>
            ),
            key: 'Parents',
            dataIndex: 'post_parents',
            align: 'top',
            width: '300px',
            render: (text) => {
                const parent = text as { title?: string; permalink?: string };
                return <>{parent.title ? <a target="_blank" href={parent.permalink}>{parent.title}</a> : ''}</>;
            },
        },
        {
            title: (
                <button className="inline-flex items-center gap-1.5 group cursor-pointer hover:text-blue-600 transition-colors" onClick={() => handleSortClick('title')}>
                    Title <SortIcon />
                </button>
            ),
            key: 'Title',
            dataIndex: 'title',
            align: 'top',
            width: '300px',
            render: (text, record, i) => (
                <>
                    {formEdited
                        ? (
                            <div className="relative">
                                <textarea className="w-full pl-13 pr-2 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none" rows={2} name="title" placeholder="Title Shouldn't leave empty" data-current={i} onBlur={handleFocusout} onChange={handleChange} value={text as string} />
                                <AiButton
                                        className="absolute left-1.5 top-1.5"
                                        attachmentId={record.ID}
                                        fieldType="title"
                                        onSuccess={(value) => {
                                            const posts = [...mediaData.posts];
                                            posts[i] = { ...posts[i], title: value };
                                            setMediaData({ posts });
                                            setSingleMedia({ alt_text: null, post_content: null, post_excerpt: null, post_title: null, ID: record.ID, title: value });
                                            setSaveType(Types.UPDATE_SINGLE_MEDIA);
                                        }}
                                    />
                            </div>
                        )
                        : <a className="w-50 flex overflow-x-auto" target="_blank" href={`${record.uploaddir}/${record.thefile.file}`}>{text as string}</a>
                    }
                </>
            ),
        },
        {
            title: (
                <button className="inline-flex items-center gap-1.5 group cursor-pointer hover:text-blue-600 transition-colors" onClick={() => handleSortClick('alt')}>
                    Alt <SortIcon />
                </button>
            ),
            key: 'Alt',
            dataIndex: 'alt_text',
            align: 'top',
            width: '300px',
            render: (text, record, i) => (
                <>
                    {formEdited
                        ? (
                            <div className="relative">
                                <textarea className="w-full pl-13 pr-2 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none" rows={2} name="alt_text" placeholder="Alt Text Shouldn't leave empty" data-current={i} onBlur={handleFocusout} onChange={handleChange} value={text as string} />
                                <AiButton
                                        className="absolute left-1.5 top-1.5"
                                        attachmentId={record.ID}
                                        fieldType="alt_text"
                                        onSuccess={(value) => {
                                            const posts = [...mediaData.posts];
                                            posts[i] = { ...posts[i], alt_text: value };
                                            setMediaData({ posts });
                                            setSingleMedia({ alt_text: value, post_content: null, post_excerpt: null, post_title: null, ID: record.ID });
                                            setSaveType(Types.UPDATE_SINGLE_MEDIA);
                                        }}
                                    />
                            </div>
                        )
                        : <span className="w-50 flex overflow-x-auto">{text as string}</span>
                    }
                </>
            ),
        },
        {
            title: (
                <button className="inline-flex items-center gap-1.5 group cursor-pointer hover:text-blue-600 transition-colors" onClick={() => handleSortClick('caption')}>
                    Caption <SortIcon />
                </button>
            ),
            key: 'Caption',
            dataIndex: 'caption',
            width: '300px',
            render: (text, record, i) => (
                <>
                    {formEdited
                        ? (
                            <div className="relative">
                                <textarea className="w-full pl-13 pr-2 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none" rows={2} name="caption" placeholder="Caption Text" data-current={i} onBlur={handleFocusout} onChange={handleChange} value={text as string} />
                                <AiButton
                                        className="absolute left-1.5 top-1.5"
                                        attachmentId={record.ID}
                                        fieldType="caption"
                                        onSuccess={(value) => {
                                            const posts = [...mediaData.posts];
                                            posts[i] = { ...posts[i], caption: value };
                                            setMediaData({ posts });
                                            setSingleMedia({ alt_text: null, post_content: null, post_excerpt: null, post_title: null, ID: record.ID, caption: value });
                                            setSaveType(Types.UPDATE_SINGLE_MEDIA);
                                        }}
                                    />
                            </div>
                        )
                        : <>{text as string}</>
                    }
                </>
            ),
        },
        {
            title: (
                <button className="inline-flex items-center gap-1.5 group cursor-pointer hover:text-blue-600 transition-colors" onClick={() => handleSortClick('description')}>
                    Description <SortIcon />
                </button>
            ),
            key: 'Description',
            dataIndex: 'description',
            width: '350px',
            render: (text, record, i) => (
                <>
                    {formEdited
                        ? (
                            <div className="relative">
                                <textarea className="w-full pl-13 pr-2 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none" rows={2} name="description" placeholder="Description Text" data-current={i} onBlur={handleFocusout} onChange={handleChange} value={text as string} />
                                <AiButton
                                        className="absolute left-1.5 top-1.5"
                                        attachmentId={record.ID}
                                        fieldType="description"
                                        onSuccess={(value) => {
                                            const posts = [...mediaData.posts];
                                            posts[i] = { ...posts[i], description: value };
                                            setMediaData({ posts });
                                            setSingleMedia({ alt_text: null, post_content: null, post_excerpt: null, post_title: null, ID: record.ID, description: value });
                                            setSaveType(Types.UPDATE_SINGLE_MEDIA);
                                        }}
                                    />
                            </div>
                        )
                        : <>{text as string}</>
                    }
                </>
            ),
        },
        {
            title: 'Groups',
            key: 'Category',
            dataIndex: 'categories',
            width: '250px',
            render: (_text, record) => {
                const items = JSON.parse(record.categories) as Array<{ id?: string | number; name?: string }>;
                return (
                    <span className="flex flex-wrap gap-1">
                        {items.map(item => item.id && (
                            <span key={Math.random().toString(36).substr(2, 9)} className="inline-flex px-2 py-0.5 text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200 rounded-full">
                                {item.name}
                            </span>
                        ))}
                    </span>
                );
            },
        },
    ];
}

export function localStoreData(key: string, value: unknown): void {
    const expirationTime = Date.now() + (60 * 60 * 1000 * 24);
    const dataObject = { value, expirationTime };
    localStorage.setItem(key, JSON.stringify(dataObject));
}

export function localRetrieveData(key: string): unknown {
    const data = localStorage.getItem(key);
    if (data) {
        const dataObject = JSON.parse(data) as { value: unknown; expirationTime: number };
        if (Date.now() <= dataObject.expirationTime) {
            return dataObject.value;
        } else {
            localStorage.removeItem(key);
        }
    }
    return null;
}
