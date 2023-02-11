import React from "react";
import {
    Button,
    Typography,
    Space,
    Layout
} from 'antd';

const { Title } = Typography;

function EditButton( { text, hasButton, prevdata } ) {
    const { ColumnHandleClick, colsText, handleBulkClick, handleSortClick, bulkdata } = prevdata;
    return (
        <>
            <Title>
                <Space wrap>
                    { text }
                    { 'image' != text.toLowerCase() && <Button size={`small`}  className={`on-hover`} sort-type={`${text.toLowerCase()}`} onClick={ ( event ) => handleSortClick( event, text.toLowerCase() ) }> Sort </Button>  }
                </Space>
            </Title>
            { hasButton &&
                <Space wrap>
                    <Button type="primary" onClick={ ( event) => ColumnHandleClick( event, text.toLowerCase() ) } ghost={ 'Unlocked Edit' != colsText[text.toLowerCase()] }>  { colsText[text.toLowerCase()] } </Button>
                    <Button type="primary" onClick={ ( event ) => handleBulkClick( event, text.toLowerCase() ) } ghost={ bulkdata.type != text.toLowerCase() } > Bulk Edit </Button>
                </Space>
            }
        </>
    );
}
export default EditButton