import React from "react";

function EditButton( { text, hasButton, prevdata } ) {
    const { ColumnHandleClick, colsText, handleBulkClick, bulkdata } = prevdata;
    console.log( bulkdata )
    return (
        <>
            <div className={`heading-title`}>
                { text }
                <span className={`on-hover`}> Sort </span>
            </div>
            { hasButton &&
                <div className={`tttme-button-link`}>
                    <span className={ 'Unlocked Edit' === colsText[text.toLowerCase()] ? 'btn-active' : '' } onClick={ ( event) => ColumnHandleClick( event, text.toLowerCase() ) }>
                         { colsText[text.toLowerCase()] }
                    </span>
                    <span className={ bulkdata.type === text.toLowerCase() ? 'btn-active' : '' }onClick={ ( event ) => handleBulkClick( event, text.toLowerCase() ) }>Bulk Edit </span>
                </div>
            }
        </>
    );
}
export default EditButton