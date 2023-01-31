// Table.js

import React, {useState, useEffect} from "react";

import { Pagination, Table, Input, Modal } from 'antd';

import {bulkUpdateMedia, getMedia, upDateSingleMedia} from "../Utils/Data";
import EditButton from "./EditButton";

const { TextArea } = Input;

const defaultPosts = {
    posts : [],
    total_post: 0,
    max_pages: 0,
    current_pag: 0,
    posts_per_page: 0,
}
const locakedText = 'Locked Edit';
const unlocakedText = 'Unlocked Edit';

export default function DataTable() {

    const [data, setData] = useState( defaultPosts );

    const [isUpdated, setIsUpdated] = useState(false );

    const [currentEdited, setCurrentEdited] = useState(false );

    const [isModalOpen, setIsModalOpen] = useState(false);

    const [bulkdata, setBulkdata] = useState({
        ids: [],
        type: '',
        data: '',
    });

    const { posts, total_post, max_pages, current_page, posts_per_page } = data;

    const [ formEdited, setFormEdited ] = useState({
        titleEditing : false,
        altEditing : false,
        captionEditing : false,
        descriptionEditing : false,
    });

    const [ colsText, setColsText ] = useState({
        title : locakedText,
        alt : locakedText,
        caption : locakedText,
        description : locakedText,
    });

    const modalClose = () => {
        setBulkdata({
            ...bulkdata,
            data: ''
        });
        setIsModalOpen(false);
    };

    const handleBulkClick = ( event, type ) => {
        const ids = posts.map( x => x['ID']);
        event.currentTarget.classList.toggle('btn-active');
        setIsModalOpen(true);
        // bulkdata,
        setBulkdata({
            ...bulkdata,
            ids,
            type
        });
    }


    const handleCancel = () => {
        modalClose();
    };

    const getTheMedia = async () => {
        const response = await getMedia()
        setData( response );
    }

    const bulkUpdate = async () => {
        const response = await bulkUpdateMedia( bulkdata )
        200 === parseInt( response.status ) && response.data.updated && setIsUpdated( ! isUpdated );
    }

    const balkChange = ( event ) => {
        setBulkdata({
            ...bulkdata,
            data : event.target.value
        });
    };

    const handleOk = () => {
        bulkUpdate( bulkdata );
        modalClose();
        // setIsUpdated( ! isUpdated )
    };


    useEffect(() => {
        getTheMedia( )
    }, [isUpdated]  );

    // event.currentTarget.classList.toggle('btn-active');
    useEffect(() => {
        if( ! isModalOpen ) {
            const allWithClass = Array.from(
                document.querySelectorAll('.btn-active')
            );
            for (const element of allWithClass) {
                element.classList.remove('btn-active');
            }
        }
    }, [isModalOpen]);


    const ColumnHandleClick = ( event, editable ) => {
        let formEditing = {};
        let colsTextEditing = {};
        switch ( editable ) {
            case 'title':
                formEditing = {
                    ...formEdited,
                    titleEditing : ! formEdited.titleEditing
                }
                colsTextEditing = {
                    ...colsText,
                    title : formEditing.titleEditing ? unlocakedText : locakedText,
                }
                break;
            case 'alt':
                formEditing = {
                    ...formEdited,
                    altEditing : ! formEdited.altEditing
                }
                colsTextEditing = {
                    ...colsText,
                    alt : formEditing.altEditing ? unlocakedText : locakedText,
                }
                break;
            case 'caption':
                formEditing = {
                    ...formEdited,
                    captionEditing : ! formEdited.captionEditing
                }
                colsTextEditing = {
                    ...colsText,
                    caption : formEditing.captionEditing ? unlocakedText : locakedText,
                }
                break;
            case 'description':
                formEditing = {
                    ...formEdited,
                    descriptionEditing : ! formEdited.descriptionEditing
                }
                colsTextEditing = {
                    ...colsText,
                    description : formEditing.descriptionEditing ? unlocakedText : locakedText,
                }
                break;
            default:
                formEditing = { ...formEdited }
                colsTextEditing = { ...colsText }
        }
        setFormEdited( formEditing );
        setColsText( colsTextEditing );
        event.currentTarget.classList.toggle('btn-active');
    }


    const handleChange = ( event ) => {
        const currentItem = parseInt( event.target.getAttribute('current') );
        const currentData = {
            ID: posts[currentItem].ID,
            [event.target.name] : event.target.value.trim()
        }
        posts[currentItem][event.target.name] = event.target.value;
        setCurrentEdited( currentData );
        setData( {
            ...data,
            posts
        } );
    }

    const handleFocusout = async ( event ) => {
        const response = await upDateSingleMedia( currentEdited );
        200 === parseInt( response.status ) && setIsUpdated( ! isUpdated );
    }

    const columns = [
        {
            title: <EditButton prevdata={{ ColumnHandleClick, colsText, handleBulkClick }} text={'Id'} hasButton={false}/>,
            key: 'Id',
            dataIndex: 'ID',
            width: '100px',
            align: 'top',
        },
        {
            title: <EditButton prevdata={{ ColumnHandleClick, colsText, handleBulkClick }} text={'Image'} hasButton={false}/>,
            key: 'Image',
            dataIndex: 'guid',
            width: '150px',
            align: 'top',
            render:  ( text, record ) =>  <img width={`80`} src={text}  />
        },
        {
            title: <EditButton prevdata={{ ColumnHandleClick, colsText, handleBulkClick }} text={'Title'} hasButton={true}/>,
            key: 'Title',
            dataIndex: 'post_title',
            align: 'top',
            width: '250px',
            render: ( text, record, i ) => <> { formEdited.titleEditing ? <TextArea name={`post_title`} placeholder={`Title Shouldn't leave empty`} current={i} onBlur={handleFocusout}  onChange={handleChange} value={ text } /> : text }   </>
        },
        {
            title: <EditButton prevdata={{ ColumnHandleClick, colsText, handleBulkClick }} text={'Alt'} hasButton={true}/>,
            key: 'Alt',
            dataIndex: 'alt_text',
            align: 'top',
            width: '300px',
            render: ( text, record, i ) => <> { formEdited.altEditing ? <TextArea name={`alt_text`} placeholder={`Alt Text Shouldn't leave empty`} current={i} onBlur={handleFocusout}  onChange={handleChange} value={ text } /> : text }   </>
        },
        {
            title: <EditButton prevdata={{ ColumnHandleClick, colsText, handleBulkClick }} text={'Caption'} hasButton={true}/>,
            key: 'Caption',
            dataIndex: 'post_excerpt',
            width: '300px',
            render: ( text, record, i ) => <> { formEdited.captionEditing ? <TextArea name={`post_excerpt`} placeholder={`Caption Text`} current={i} onBlur={handleFocusout}  onChange={handleChange} value={ text } /> : text }   </>
        },
        {
            title: <EditButton prevdata={{ ColumnHandleClick, colsText, handleBulkClick }} text={'Description'} hasButton={true}/>,
            key: 'Description',
            dataIndex: 'post_content',
            width: '350px',
            render: ( text, record, i ) => <> { formEdited.descriptionEditing ? <TextArea name={`post_content`} placeholder={`Description Text`} current={i} onBlur={handleFocusout}  onChange={handleChange} value={ text } /> : text }   </>
        },
    ];


    return (
            <>
                { posts &&
                    <>
                    <Table
                        pagination={false}
                        columns={columns}
                        dataSource={posts}
                        scroll={{
                            x: 1300,
                        }}
                    />
                    <div className={`post-pagination`}>
                       {
                            posts_per_page &&
                            <Pagination
                                showTitle={true}
                                showTotal={(total, range) => `${range[0]}-${range[1]} of ${total} items`}
                                defaultPageSize={posts_per_page}
                                total={total_post}
                                current={current_page}
                            />
                       }
                    </div>
                    <Modal title="Basic Modal" open={isModalOpen} onOk={handleOk} onCancel={handleCancel}>
                        <TextArea onChange={balkChange} name={`modal_content`} value={bulkdata.data} placeholder={`Field Shouldn't leave empty`} />
                    </Modal>
                    </>
                }
            </>
    );
}
