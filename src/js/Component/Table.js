// Table.js

import React, {useContext, useEffect} from "react";
import {  useState, useRef } from "react";
import SystemContext from '../SystemContext';
import {getData} from "../Utils/Url";

export default function Table() {

    const  { columns, defaultPosts, DefaultFormData }  = useContext( SystemContext );

    const [formData , setFormData ] = useState( {} );

    // data state to store the TV Maze API data. Its initial value is an empty array
    const [data, setData] = useState( defaultPosts  );

    const [params, setParams] = useState('');

    // console.log( posts, total_post, max_pages, current_page );
    // Use the useTable Hook to send the columns and data to build the table
    const [ titleEditing, setTitleEditing ] = useState(false);

    const [ altEditing, setAltEditing ] = useState(false);

    const [ captionEditing, setCaptionEditing ] = useState(false);

    const [ descriptionEditing, setDescriptionEditing ] = useState(false);

    const inputRef = useRef(null);

    const getTheData = async () => {
        const response = await getData( params )
        setData( response );
    }

    useEffect(() => {
        getTheData()
    }, [params]);

    const { posts, total_post, max_pages, current_page } = data;

    const handleClick = ( editable ) => {
        if( 'title' === editable ){
            setTitleEditing( ! titleEditing ) ;
        }
        if( 'alt' === editable ){
            setAltEditing( ! altEditing ) ;
        }
        if( 'caption' === editable ){
            setCaptionEditing( ! captionEditing ) ;
        }
        if( 'description' === editable ){
            setDescriptionEditing( ! descriptionEditing ) ;
        }
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
        const changeData = {
            ...DefaultFormData,
            [event.target.name] : [ ]
        }
        setFormData( changeData );
    }
    console.log( formData );
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
                                        {titleEditing ? (
                                            <textarea
                                                dataid={item.ID}
                                                name={`title`}
                                                ref={inputRef} // Set the Ref
                                                value={ `Data` }
                                                onChange={handleChange}
                                            />
                                        ) : item.post_title
                                        }
                                    </div>
                                </td>
                                <td dataid={item.ID}>
                                    <div className={`alt-text`} >
                                        {altEditing ? (
                                            <textarea
                                                dataid={item.ID}
                                                name={`alt_text`}
                                                ref={inputRef} // Set the Ref
                                                value={ `alt-text` }
                                                onChange={handleChange}
                                            />
                                        ) : item.alt_text
                                        }
                                    </div>
                                </td>
                                <td dataid={item.ID}>
                                    <div className={`caption`} > { item.post_excerpt }
                                        {captionEditing ? (
                                            <textarea
                                                dataid={item.ID}
                                                name={`caption`}
                                                ref={inputRef} // Set the Ref
                                                value={ `caption` }
                                                onChange={handleChange}
                                            />
                                        ) : item.post_excerpt
                                        }
                                    </div>
                                </td>
                                <td dataid={item.ID}>
                                    <div className={`description`} >
                                        {descriptionEditing ? (
                                            <textarea
                                                dataid={item.ID}
                                                name={`description`}
                                                ref={inputRef} // Set the Ref
                                                value={ `description` }
                                                onChange={handleChange}
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
