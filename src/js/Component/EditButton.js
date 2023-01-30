import React from "react";

function EditButton( { text, hasButton, prevdata } ) {
    const { ColumnHandleClick, colsText, handleBulkClick } = prevdata;
    return (
        <>
            <div className={`heading-title`}>
                { text }
                <span className={`on-hover`}> Sort </span>
            </div>
            { hasButton &&
                <div className={`tttme-button-link`}>
                    <span onClick={ ( event) => ColumnHandleClick( event, text.toLowerCase() ) }>
                         { colsText[text.toLowerCase()] }
                    </span>
                    <span onClick={ ( event ) => handleBulkClick( event ) }>Bulk Edit </span>
                </div>
            }
        </>
    );
}
export default EditButton