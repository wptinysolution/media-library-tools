import React, { useState } from "react";
import { useStore } from "@/js/Utils/store";
import type { MediaPost, RubbishMediaFile, BulkSubmitData } from "@/js/Utils/store";
import * as Types from "@/js/Utils/actionType";
import { rubbishSingleDeleteAction, rubbishSingleIgnoreAction, rubbishSingleShowAction } from "./Data";
import { CopyToClipboard } from "@/js/Component/CopyToClipboard";
import type { ColumnDef } from "@/js/Component/Common/DataTable";

export const headerStyle: React.CSSProperties = {
    height: 'auto',
    paddingInline: 0,
    lineHeight: '1',
    backgroundColor: '#fff',
    padding: '15px 0'
};

export const selectStyle: React.CSSProperties = {
    width: 250,
    paddingInline: 0,
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
};

export const bulkOprions: Array<{ value: string; label: string }> = [
    { value: 'bulkedit', label: 'Bulk Edit' },
    { value: 'csv_export', label: 'CSV Export' },
    { value: 'bulkEditPostTitle', label: 'Edit Based on Attached Post Title' },
    { value: 'inherit', label: 'Restore' },
    { value: 'searchUses', label: 'Search Used Images (Attached Post)' },
    { value: 'trash', label: 'Move to Trash' },
    { value: 'delete', label: 'Delete Permanently ' },
];

export const columnList: Array<{ title: string; key: string }> = [
    { title: 'ID', key: 'ID' },
    { title: 'File', key: 'Image' },
    { title: 'Attached Post (Parent)', key: 'Parents' },
    { title: 'Title', key: 'Title' },
    { title: 'Alt', key: 'Alt' },
    { title: 'Caption', key: 'Caption' },
    { title: 'Description', key: 'Description' },
    { title: 'Groups', key: 'Category' },
];

const theImage = (record: MediaPost): React.ReactElement => {
    const typeParts = record.post_mime_type.split('/');
    const type = Array.isArray(typeParts) ? typeParts[0] : '';
    const width = 80;
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
        case 'text':
            url = `${tsmltParams.includesUrl}/images/media/text.png`;
            break;
        default:
            url = `${tsmltParams.includesUrl}/images/media/text.png`;
    }

    return <img width={width} src={url} />;
};

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

    const SortIcon = () => (
        <svg className="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
        </svg>
    );

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
            render: (text, record) => <span className="inline-flex items-center">{theImage(record)}</span>,
        },
        {
            title: (
                <button className="inline-flex items-center gap-1.5 group cursor-pointer hover:text-blue-600 transition-colors" onClick={() => handleSortClick('post_parents')}>
                    Attached Post (Parent) <SortIcon />
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
                        ? <textarea className="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none" rows={2} name="title" placeholder="Title Shouldn't leave empty" data-current={i} onBlur={handleFocusout} onChange={handleChange} value={text as string} />
                        : <a className="w-[200px] flex overflow-x-auto" target="_blank" href={`${record.uploaddir}/${record.thefile.file}`}>{text as string}</a>
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
                        ? <textarea className="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none" rows={2} name="alt_text" placeholder="Alt Text Shouldn't leave empty" data-current={i} onBlur={handleFocusout} onChange={handleChange} value={text as string} />
                        : <span className="w-[200px] flex overflow-x-auto">{text as string}</span>
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
                        ? <textarea className="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none" rows={2} name="caption" placeholder="Caption Text" data-current={i} onBlur={handleFocusout} onChange={handleChange} value={text as string} />
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
                        ? <textarea className="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none" rows={2} name="description" placeholder="Description Text" data-current={i} onBlur={handleFocusout} onChange={handleChange} value={text as string} />
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
            render: (text, record) => {
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

export function renamerColumns(): ColumnDef<MediaPost>[] {
    const { mediaData, bulkSubmitData, setBulkSubmitData, rename, setSaveType } = useStore();

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
            progressTotal: checkedCount,
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
            title: 'File',
            key: 'Image',
            dataIndex: 'guid',
            width: '100px',
            align: 'top',
            render: (text, record) => <span className="inline-flex items-center">{theImage(record)}</span>,
        },
        {
            title: <span className="inline-flex items-center">Attached Post (Parent)</span>,
            key: 'Parents',
            dataIndex: 'post_parents',
            width: '150px',
            render: (text) => {
                const parent = text as { title?: string; permalink?: string };
                return <>{parent.title ? <a target="_blank" href={parent.permalink}>{parent.title}</a> : ''}</>;
            },
        },
        {
            title: 'File Name',
            key: 'Image',
            dataIndex: 'guid',
            width: '350px',
            align: 'top',
            render: (text, record, i) => (
                <>
                    {rename.formEdited
                        ? (
                            <div className="flex items-center gap-1 bg-transparent">
                                <input
                                    type="text"
                                    className="w-[350px] h-[38px] px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    name="filebasename"
                                    placeholder="The name Shouldn't leave empty"
                                    data-current={i}
                                    onBlur={() => setSaveType(Types.UPDATE_RENAMER_MEDIA)}
                                    onChange={(event: React.ChangeEvent<HTMLInputElement>) => {
                                        const { setMediaData, setRename } = useStore.getState();
                                        const currentItem = parseInt(event.target.getAttribute('data-current') ?? '0', 10);
                                        if ('filebasename' === event.target.name) {
                                            const posts = [...mediaData.posts];
                                            const pnlname = { ...posts[currentItem].thefile };
                                            posts[currentItem] = {
                                                ...posts[currentItem],
                                                thefile: { ...posts[currentItem].thefile, filebasename: event.target.value },
                                            };
                                            setMediaData({ posts });
                                            setRename({
                                                postsdata: pnlname,
                                                ID: posts[currentItem].ID,
                                                newname: event.target.value,
                                            });
                                        }
                                    }}
                                    value={record.thefile.filebasename}
                                />
                                <span className="text-sm text-gray-600">{`.${record.thefile.fileextension}`}</span>
                            </div>
                        )
                        : <a className="max-w-[300px] flex overflow-x-auto" target="_blank" href={`${record.uploaddir}/${record.thefile.file}`}>{record.thefile.mainfilename}</a>
                    }
                </>
            ),
        },
        {
            title: 'URL',
            key: 'Image',
            dataIndex: 'guid',
            align: 'top',
            minWidth: 300,
            render: (text, record) => (
                <span className="inline-flex items-center gap-1">
                    <code className="text-sm text-gray-500 bg-gray-100 px-2 py-0.5 rounded w-[300px] inline-flex overflow-x-auto">
                        {record.uploaddir + '/' + record.thefile.file}
                    </code>
                    <CopyToClipboard text={`${record.uploaddir + '/' + record.thefile.file}`} />
                </span>
            ),
        },
        {
            title: <span className="inline-flex items-center">Title</span>,
            key: 'Title',
            dataIndex: 'title',
            align: 'top',
            render: (text) => <span className="text-sm text-gray-500">{text as string}</span>,
        },
    ];
}

export function RubbishFileColumns(): ColumnDef<RubbishMediaFile>[] {
    const { rubbishMedia, setRubbishMedia, bulkRubbishData, setBulkRubbishData, setGeneralData } = useStore();

    const [deleteCurrentItem, setDeleteCurrentItem] = useState<string | number | null>(null);
    const [ignoreCurrentItem, setIgnoreCurrentItem] = useState<string | number | null>(null);

    const onRubbishBulkCheck = (event: React.ChangeEvent<HTMLInputElement>) => {
        const postsId = event.target.checked ? rubbishMedia.mediaFile.map(item => item.id) : [];
        const files = event.target.checked
            ? rubbishMedia.mediaFile.map(item => ({ id: item.id, path: item.file_path }))
            : [];
        setBulkRubbishData({
            bulkChecked: !!postsId.length,
            ids: postsId,
            files,
            progressTotal: files.length,
        });
    };

    const onCheckboxChange = (event: React.ChangeEvent<HTMLInputElement>, record: RubbishMediaFile) => {
        const value = event.target.value;
        const changeData = event.target.checked
            ? [...bulkRubbishData.ids, value]
            : bulkRubbishData.ids.filter(item => item !== value);

        const changePath = event.target.checked
            ? [...bulkRubbishData.files, { id: record.id, path: record.file_path }]
            : bulkRubbishData.files.filter(item => item.id !== record.id);

        const checkedCount = changeData.length;
        const postCount = rubbishMedia.mediaFile.length;

        setBulkRubbishData({
            bulkChecked: !!(checkedCount && checkedCount === postCount),
            ids: changeData,
            files: changePath,
            progressTotal: checkedCount,
        });
    };

    const onRubbishSingleAction = async (data: RubbishMediaFile, action: string) => {
        if (tsmltParams.hasExtended) {
            let response: { status: number | string; data: { updated: boolean } } | undefined;
            if ('ignore' === action) {
                setIgnoreCurrentItem(data.id);
                response = await rubbishSingleIgnoreAction(data) as typeof response;
            } else if ('delete' === action) {
                setDeleteCurrentItem(data.id);
                response = await rubbishSingleDeleteAction(data) as typeof response;
            } else if ('show' === action) {
                response = await rubbishSingleShowAction(data) as typeof response;
            }
            if (200 === parseInt(String(response?.status))) {
                const mediaFile = response?.data.updated
                    ? rubbishMedia.mediaFile.filter(item => data.id !== item.id)
                    : rubbishMedia.mediaFile;
                setRubbishMedia({ mediaFile });
                setIgnoreCurrentItem(null);
                setDeleteCurrentItem(null);
            }
            return;
        }
        setGeneralData({ openProModal: true });
    };

    return [
        {
            title: (
                <input
                    type="checkbox"
                    className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                    checked={bulkRubbishData.bulkChecked}
                    onChange={onRubbishBulkCheck}
                />
            ),
            key: 'CheckboxID',
            dataIndex: 'id',
            width: '50px',
            align: 'center',
            fixed: true,
            render: (id, record) => (
                <input
                    type="checkbox"
                    className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                    checked={-1 !== bulkRubbishData.ids.indexOf(id as string | number)}
                    name="item_id"
                    value={String(id)}
                    onChange={(event) => onCheckboxChange(event, record)}
                />
            ),
        },
        {
            title: 'File',
            key: 'Image',
            dataIndex: 'file_path',
            width: '150px',
            align: 'top',
            render: (file_path) => (
                <span className="inline-flex items-center">
                    <img width={50} src={`${tsmltParams.uploadUrl}/${file_path as string}`} />
                </span>
            ),
        },
        {
            title: 'File URL',
            key: 'FileType',
            dataIndex: 'file_path',
            align: 'top',
            render: (file_path) => (
                <span className="text-sm">{`${tsmltParams.uploadUrl}/${file_path as string}`}</span>
            ),
        },
        {
            title: 'Actions',
            key: 'FileType',
            dataIndex: 'file_path',
            align: 'top',
            width: '450px',
            render: (text, record) => (
                <span className="flex flex-wrap gap-2">
                    {'ignore' === rubbishMedia.postQuery.fileStatus ? (
                        <button
                            className="px-3 py-1.5 text-sm font-medium border border-gray-300 rounded-md hover:bg-gray-100 cursor-pointer transition-colors disabled:opacity-50"
                            onClick={() => onRubbishSingleAction(record, 'show')}
                            disabled={record.id === deleteCurrentItem}
                        >
                            {record.id === deleteCurrentItem ? 'Processing...' : 'Mark As Unnecessary File'}
                        </button>
                    ) : (
                        <>
                            <button
                                className="px-3 py-1.5 text-sm font-medium text-red-600 border border-red-300 rounded-md hover:bg-red-50 cursor-pointer transition-colors disabled:opacity-50"
                                onClick={() => onRubbishSingleAction(record, 'delete')}
                                disabled={record.id === deleteCurrentItem}
                            >
                                {record.id === deleteCurrentItem ? 'Deleting...' : 'Delete Unnecessary File'}
                            </button>
                            <button
                                className="px-3 py-1.5 text-sm font-medium border border-gray-300 rounded-md hover:bg-gray-100 cursor-pointer transition-colors disabled:opacity-50"
                                onClick={() => onRubbishSingleAction(record, 'ignore')}
                                disabled={record.id === ignoreCurrentItem}
                            >
                                {record.id === ignoreCurrentItem ? 'Processing...' : 'Ignore Important File'}
                            </button>
                        </>
                    )}
                </span>
            ),
        },
    ];
}

export const functionDebounce = (func: (...args: unknown[]) => void, delay: number) => {
    let timeoutId: ReturnType<typeof setTimeout>;
    return function (...args: unknown[]) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            func.apply(null, args);
        }, delay);
    };
};

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
