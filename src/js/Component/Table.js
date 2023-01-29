// Table.js

import React, {useContext, useEffect} from "react";
import {  useState, useRef } from "react";

import SystemContext from '../SystemContext';
import {bulkUpdateMedia, getMedia, upDateSingleMedia} from "../Utils/Data";

export default function Table() {

    const  { columns, defaultPosts }  = useContext( SystemContext );

    const [data, setData] = useState( defaultPosts );

    const [isUpdated, setIsUpdated] = useState(false );

    const [currentEdited, setCurrentEdited] = useState(false );

    const [ formEdited, setFormEdited ] = useState({
        titleEditing : false,
        altEditing : false,
        captionEditing : false,
        descriptionEditing : false,
    });
    const locakedText = 'Locked Edit';
    const unlocakedText = 'Unlocked Edit';
    const [ colsText, setColsText ] = useState({
        title : locakedText,
        alt : locakedText,
        caption : locakedText,
        description : locakedText,
    });

    const inputRef = useRef(null);

    const bulkUpdate = async () => {
        const response = await bulkUpdateMedia()

    }
    const getTheMedia = async () => {
        const response = await getMedia()
        setData( response );
    }

    useEffect(() => {
        getTheMedia( )
    }, [isUpdated]  );


    const { posts, total_post, max_pages, current_page } = data;

    const handleClick = ( event,editable ) => {
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

    const handleBulkClick = ( event ) => {

        event.currentTarget.classList.toggle('btn-active');
    }



    const getPaginationContent = () => {
        let content = [];
        for (let i = 1; i < max_pages; i++) {
            if( current_page === i ){
                content.push(<span key={ i } href={`/page=${ i }`}>{ i }</span>);
            }else{
                content.push(<a key={ i } href={`/page=${ i }`}>{ i }</a>);
            }
        }
        return content;
    };


    const handleChange = ( event ) => {
        const currentItem = parseInt( event.target.getAttribute('current') );
        const currentData = {
            ID: posts[currentItem].ID,
            [event.target.name] : event.target.value.trim()
        }
        posts[currentItem][event.target.name] = event.target.value;
        setCurrentEdited( currentData );

    }
    const handleFocusout = async ( event ) => {
        const response = await upDateSingleMedia( currentEdited );
        200 === parseInt( response.status ) && setIsUpdated( ! isUpdated );
    }

    const the_header = ( position = '' ) => (
            <tr>
                {columns.map( ( column, i ) => (
                    <th width={ column.Width } className={`manage-column`} key={i}>
                        {
                        'Id' === column.Header || 'Image' === column.Header ?
                            column.Header
                            :
                            <>
                                <div className={`heading-title`}>
                                    { column.Header }
                                    <span className={`on-hover`}> Sort </span>
                                </div>
                                { 'header' === position &&
                                    <div className={`tttme-button-link`}>
                                        <span onClick={ ( event) => handleClick( event, column.Header.toLowerCase() ) }>
                                             { colsText[column.Header.toLowerCase()] }
                                        </span>
                                        <span onClick={ ( event ) => handleBulkClick( event ) }>Bulk Edit </span>
                                    </div>
                                }
                            </>
                        }
                    </th>
                ))}
            </tr>
    )

    return (
        <>
                <table className={`wp-list-table widefat fixed striped table-view-list media`}>
                    <thead>
                        { the_header('header') }
                    </thead>
                    <tbody id="the-list">
                        { posts && posts.map( ( item, i ) => (
                                <tr key={i} >
                                    <td width={`50px`}>  { item.ID } </td>
                                    <td dataid={item.ID}><div className={`image`}>  <img width={`50`} src={item.guid}/></div></td>
                                    <td dataid={item.ID}>
                                        <div className={`title`}  >
                                            { formEdited.titleEditing ? (
                                                <textarea
                                                    dataid={item.ID}
                                                    current={i}
                                                    name={`post_title`}
                                                    ref={inputRef} // Set the Ref
                                                    value={ item.post_title }
                                                    onChange={handleChange}
                                                    onBlur={handleFocusout}
                                                />
                                            ) : item.post_title
                                            }
                                        </div>
                                    </td>
                                    <td dataid={item.ID}>
                                        <div className={`alt-text`} >
                                            { formEdited.altEditing ? (
                                                <textarea
                                                    dataid={item.ID}
                                                    current={i}
                                                    name={`alt_text`}
                                                    ref={inputRef} // Set the Ref
                                                    value={ item.alt_text }
                                                    onChange={handleChange}
                                                    onBlur={handleFocusout}
                                                />
                                            ) : item.alt_text
                                            }
                                        </div>
                                    </td>
                                    <td dataid={item.ID}>
                                        <div className={`caption`} >
                                            { formEdited.captionEditing ? (
                                                <textarea
                                                    dataid={item.ID}
                                                    current={i}
                                                    name={`post_excerpt`}
                                                    ref={inputRef} // Set the Ref
                                                    value={ item.post_excerpt }
                                                    onChange={handleChange}
                                                    onBlur={handleFocusout}
                                                />
                                            ) : item.post_excerpt
                                            }
                                        </div>
                                    </td>
                                    <td dataid={item.ID}>
                                        <div className={`description`} >
                                            { formEdited.descriptionEditing ? (
                                                <textarea
                                                    dataid={item.ID}
                                                    current={i}
                                                    name={`post_content`}
                                                    ref={inputRef} // Set the Ref
                                                    value={ item.post_content }
                                                    onChange={handleChange}
                                                    onBlur={handleFocusout}
                                                />
                                            ) : item.post_content
                                            }
                                        </div>
                                    </td>
                                </tr>
                            ))
                        }
                    </tbody>
                    <tfoot>
                        { the_header() }
                    </tfoot>

                </table>
                <div className={`post-pagination`}>
                    Page {  getPaginationContent() }
                </div>
        </>
    );
}
