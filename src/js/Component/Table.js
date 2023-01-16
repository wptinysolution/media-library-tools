// Table.js

import React from "react";
import { useTable } from "react-table";

export default function Table({ columns, data }) {
    // Use the useTable Hook to send the columns and data to build the table

    /*
      Render the UI for your table
      - react-table doesn't have UI, it's headless. We just need to put the react-table props from the Hooks, and it will do its magic automatically
    */
    return (
        <table className={`wp-list-table widefat fixed striped table-view-list media`}>
            <thead>
                 <tr>
                    {columns.map( ( column, i ) => (
                        <th width={ column.Width } className={`manage-column`} key={i}>{ column.Header }</th>
                    ))}
                 </tr>
            </thead>
            <tbody id="the-list">
                { data.map( ( item, i ) => (
                    <tr key={i} >
                        <td width={`50px`}>  { item.id } </td>
                        <td> <img width={`50`} src={item.source_url}/></td>
                        <td> { item.title.rendered } </td>
                        <td> { item.alt_text } </td>
                        <td> { item.caption.rendered } </td>
                        <td> { item.description.rendered } </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}