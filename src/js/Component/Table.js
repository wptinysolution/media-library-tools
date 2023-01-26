// Table.js

import React, {useContext, useEffect} from "react";
import {  useState, useRef } from "react";
import SystemContext from '../SystemContext';
import {getData, upDateSingleData} from "../Utils/Data";

export default function Table() {

    const  { columns, defaultPosts }  = useContext( SystemContext );

    // data state to store the TV Maze API data. Its initial value is an empty array
    const [data, setData] = useState( defaultPosts );

    const [formData, setFormData] = useState( data.posts );

    const [isUpdated, setIsUpdated] = useState(false );

    const [currentEdited, setCurrentEdited] = useState(false );

    const [ formEdited, setFormEdited ] = useState({
        titleEditing : false,
        altEditing : false,
        captionEditing : false,
        descriptionEditing : false,
    });

    const inputRef = useRef(null);

    const getTheData = async () => {
        const response = await getData()
        setData( response );
        setFormData( response.posts );
    }

    useEffect(() => {
        getTheData( )
    }, [isUpdated]);


    const { posts, total_post, max_pages, current_page } = data;

    const handleClick = ( editable ) => {
        let formEditing = {};
        switch ( editable ) {
            case 'title':
                formEditing = {
                    ...formEdited,
                    titleEditing : ! formEdited.titleEditing
                }  ;
                break;
            case 'alt':
                formEditing = {
                    ...formEdited,
                    altEditing :  ! formEdited.altEditing
                } ;
                break;
            case 'caption':
                formEditing = {
                    ...formEdited,
                    captionEditing : ! formEdited.captionEditing
                };
                break;
            case 'description':
                formEditing = {
                    ...formEdited,
                    descriptionEditing : ! formEdited.descriptionEditing
                };
                break;
            default:
                formEditing = { ...formEdited };
        }
        setFormEdited( formEditing );


    }

    const getAnimalsContent = () => {
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
            [event.target.name] : event.target.value
        }
        setCurrentEdited( currentData );
    }
    const handleFocusout = async ( event ) => {
        const response = await upDateSingleData( currentEdited );
        200 === parseInt( response.status ) ? setIsUpdated( true ) : '';
    }

    return (
        <>
            { posts &&
                <table className={`wp-list-table widefat fixed striped table-view-list media`}>
                    <thead>
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
                                                <div className={`tttme-button-link`}>
                                                    <span onClick={ ( ) => handleClick( column.Header.toLowerCase() ) }>Make Editable</span>
                                                    <span>Bulk Edit </span>
                                                </div>
                                            </>
                                    }
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody id="the-list">
                        { posts.map( ( item, i ) => (
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
                                                value={ formData.title }
                                                onChange={handleChange}
                                                onBlur={handleFocusout}
                                            />
                                        ) : item.post_title
                                        }
                                    </div>
                                </td>
                                <td dataid={item.ID}>
                                    <div className={`alt-text`} >
                                        {formEdited.altEditing ? (
                                            <textarea
                                                dataid={item.ID}
                                                current={i}
                                                name={`alt_text`}
                                                ref={inputRef} // Set the Ref
                                                value={ formData.alt_text }
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
                                                value={ formData.post_excerpt }
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
                                                value={ formData.post_content }
                                                onChange={handleChange}
                                                onBlur={handleFocusout}
                                            />
                                        ) : item.post_content
                                        }
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td colSpan={`2`}>
                                <div className={`post-pagination`}>
                                   Page {  getAnimalsContent() }
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            }
        </>
    );
}
