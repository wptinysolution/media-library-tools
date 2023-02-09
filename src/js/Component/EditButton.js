import React from "react";

function EditButton( { text, hasButton, prevdata } ) {
    const { ColumnHandleClick, colsText, handleBulkClick, handleSortClick, bulkdata } = prevdata;
    return (
        <>
            <div className={`heading-title`}>
                { text }
                { 'image' != text.toLowerCase() && <span className={`on-hover`} sort-type={`${text.toLowerCase()}`} onClick={ ( event ) => handleSortClick( event, text.toLowerCase() ) }> Sort </span>  }
            </div>
            { hasButton &&
                <div className={`tttme-button-link`}>
                    <span className={ 'Unlocked Edit' === colsText[text.toLowerCase()] ? 'btn-active' : '' } onClick={ ( event) => ColumnHandleClick( event, text.toLowerCase() ) }>
                         { colsText[text.toLowerCase()] }
                    </span>
                    <span className={ bulkdata.type === text.toLowerCase() ? 'btn-active' : '' } onClick={ ( event ) => handleBulkClick( event, text.toLowerCase() ) }>Bulk Edit </span>
                </div>
            }
        </>
    );
}
export default EditButton