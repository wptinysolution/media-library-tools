// Table.js

import React from "react";
import {  useState, useRef } from "react";

export default function Table({ columns, data }) {
    // Use the useTable Hook to send the columns and data to build the table
    const [ titleEditing, setTitleEditing ] = useState(false);
    const [ altEditing, setAltEditing ] = useState(false);
    const [ captionEditing, setCaptionEditing ] = useState(false);
    const [ descriptionEditing, setDescriptionEditing ] = useState(false);
    const inputRef = useRef(null);
    // This syntax ensures `this` is bound within handleClick.
    const handleClick = ( editable ) => {
        if( 'title' === editable ){
            setTitleEditing( true ) ;
        }
    }

    /*
      Render the UI for your table
      - react-table doesn't have UI, it's headless. We just need to put the react-table props from the Hooks, and it will do its magic automatically
    */
    return (
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
                                        <span onClick={ ( ) => handleClick( 'title' ) }>Make Editable</span>
                                        <span>Bulk Edit </span>
                                    </div>
                                </>
                            }
                        </th>
                    ))}
                 </tr>
            </thead>
            <tbody id="the-list">
                { data.map( ( item, i ) => (
                    <tr key={i} >
                        <td width={`50px`}>  { item.ID } </td>
                        <td dataid={item.ID}><div className={`image`}>  <img width={`50`} src={item.guid}/></div></td>
                        <td dataid={item.ID}>
                            <div className={`title`}  >
                                    {titleEditing ? (
                                        <textarea
                                            ref={inputRef} // Set the Ref
                                            value={ item.title }
                                            // onChange={this.handleChange}
                                        />
                                    ) : item.title
                                    }
                            </div>
                        </td>
                        <td dataid={item.ID}><div className={`alt-text`} contentEditable={altEditing} >  { item.alt_text } </div></td>
                        <td dataid={item.ID}><div className={`caption`} contentEditable={captionEditing} > { item.post_excerpt }</div> </td>
                        <td dataid={item.ID}><div className={`description`} contentEditable={descriptionEditing} > { item.post_content } </div> </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
