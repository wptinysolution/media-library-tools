
import React from "react";

function EditButton( { text, hasButton } ) {
    return (
        <>
            <div className={`heading-title`}>
                { text }
                <span className={`on-hover`}> Sort </span>
            </div>
            { hasButton &&
                <div className={`tttme-button-link`}>
                    <span> Make Editable </span>
                    <span> Bulk Edit </span>
                </div>
            }

        </>
    );
}
export default EditButton